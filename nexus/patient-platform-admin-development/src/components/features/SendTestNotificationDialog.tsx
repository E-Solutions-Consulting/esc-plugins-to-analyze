import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { supabase } from "@/integrations/supabase/client";
import { useMutation } from "@tanstack/react-query";
import { Bell, Loader2, SmartphoneNfc } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

async function readFunctionError(error: unknown): Promise<string> {
  if (
    error &&
    typeof error === "object" &&
    "context" in error &&
    error.context instanceof Response
  ) {
    const status = error.context.status;
    const payload = await error.context.clone().json().catch(async () => {
      const text = await error.context.clone().text().catch(() => "");
      return text ? { error: { message: text } } : null;
    });
    const errorPayload =
      payload && typeof payload === "object" && "error" in payload
        ? (payload.error as { code?: string; message?: string })
        : null;

    if (errorPayload?.code || errorPayload?.message) {
      return [
        errorPayload.code,
        errorPayload.message,
        `(HTTP ${status})`,
      ].filter(Boolean).join(": ");
    }

    return `Edge Function failed with HTTP ${status}`;
  }

  return error instanceof Error ? error.message : String(error);
}

interface SendTestNotificationDialogProps {
  patientId: string;
  patientName: string;
}

export function SendTestNotificationDialog({
  patientId,
  patientName,
}: SendTestNotificationDialogProps) {
  const [open, setOpen] = useState(false);
  const [title, setTitle] = useState("Test Notification");
  const [body, setBody] = useState("Push notifications are working!");

  const mutation = useMutation({
    mutationFn: async () => {
      // Supabase functions.invoke supports sub-paths: 'patient-api/path/...'
      // resolves to /functions/v1/patient-api/admin/test-push-notification
      const { data: result, error: fnError } = await supabase.functions.invoke<{
        data?: {
          sent: boolean;
          notification_id: string | null;
          device_registered: boolean;
          onesignal_status?: number | null;
          onesignal_response?: unknown;
          error?: string | null;
        };
        error?: { code: string; message: string };
      }>("patient-api/admin/test-push-notification", {
        body: { patient_id: patientId, title: title.trim(), body: body.trim() },
        method: "POST",
      });

      if (fnError) throw new Error(await readFunctionError(fnError));
      if (result?.error)
        throw new Error(`${result.error.code}: ${result.error.message}`);

      return result?.data;
    },
    onSuccess: (data) => {
      if (!data?.sent) {
        toast.warning("No device registered", {
          description:
            data?.error ||
            `${patientName} hasn't logged into the mobile app yet — no device is registered with OneSignal.`,
        });
      } else {
        toast.success("Test notification sent!", {
          description: `Notification delivered to ${patientName}'s device. Check the phone now.`,
        });
      }
      setOpen(false);
    },
    onError: (err: unknown) => {
      const message = err instanceof Error ? err.message : String(err);

      if (message.includes("ONESIGNAL_NOT_CONFIGURED")) {
        toast.error("OneSignal not configured", {
          description:
            "Add the OneSignal app_id and rest_api_key in Tenant → Integrations before testing notifications.",
        });
      } else {
        toast.error("Failed to send notification", { description: message });
      }
    },
  });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button variant="outline" size="sm" className="gap-2">
          <SmartphoneNfc className="h-4 w-4" />
          Test Push
        </Button>
      </DialogTrigger>

      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Bell className="h-5 w-5" />
            Send Test Push Notification
          </DialogTitle>
          <DialogDescription>
            Send an immediate push to{" "}
            <span className="font-medium">{patientName}</span>'s registered
            device. The patient must have logged into the mobile app at least
            once for their device to be registered.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-2">
          <div className="space-y-2">
            <Label htmlFor="notif-title">Title</Label>
            <Input
              id="notif-title"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              placeholder="Notification title"
              maxLength={100}
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="notif-body">Message</Label>
            <Textarea
              id="notif-body"
              value={body}
              onChange={(e) => setBody(e.target.value)}
              placeholder="Notification body"
              rows={3}
              maxLength={250}
            />
          </div>
        </div>

        <DialogFooter className="gap-2">
          <Button
            variant="outline"
            onClick={() => setOpen(false)}
            disabled={mutation.isPending}
          >
            Cancel
          </Button>
          <Button
            onClick={() => mutation.mutate()}
            disabled={mutation.isPending || !title.trim() || !body.trim()}
            className="gap-2"
          >
            {mutation.isPending ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
              <SmartphoneNfc className="h-4 w-4" />
            )}
            Send Notification
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
