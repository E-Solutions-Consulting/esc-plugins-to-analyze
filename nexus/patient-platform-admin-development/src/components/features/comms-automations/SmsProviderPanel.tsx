/** Tenant SMS (Twilio) configuration for Communications Automations. */
import { useEffect, useState } from "react";
import { CheckCircle2, Loader2, MessageSquare } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { toast } from "sonner";
import { useSetSmsProvider, useSmsProvider } from "@/hooks/useCommsAutomations";

export function SmsProviderPanel() {
  const { data, isLoading } = useSmsProvider();
  const save = useSetSmsProvider();

  const [accountSid, setAccountSid] = useState("");
  const [fromNumber, setFromNumber] = useState("");
  const [authToken, setAuthToken] = useState("");
  const [enabled, setEnabled] = useState(true);

  useEffect(() => {
    if (data) {
      setAccountSid(data.account_sid ?? "");
      setFromNumber(data.from_number ?? "");
      setEnabled(data.is_enabled);
    }
  }, [data]);

  const handleSave = async () => {
    try {
      await save.mutateAsync({
        account_sid: accountSid.trim(),
        from_number: fromNumber.trim(),
        ...(authToken.trim() ? { auth_token: authToken.trim() } : {}),
        is_enabled: enabled,
      });
      setAuthToken("");
      toast.success("SMS provider saved");
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Save failed");
    }
  };

  if (isLoading) return <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />;

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div>
            <CardTitle className="flex items-center gap-2">
              <MessageSquare className="h-5 w-5 text-green-600" /> SMS via Twilio
            </CardTitle>
            <CardDescription>
              Used by SMS steps in automations. Credentials are stored per tenant; the auth token is
              write-only and never shown back.
            </CardDescription>
          </div>
          {data?.has_auth_token ? (
            <Badge variant="default" className="gap-1">
              <CheckCircle2 className="h-3.5 w-3.5" /> Configured
            </Badge>
          ) : (
            <Badge variant="secondary">Not configured</Badge>
          )}
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="flex items-center justify-between rounded-md border p-3">
          <div>
            <p className="text-sm font-medium">Enable SMS</p>
            <p className="text-xs text-muted-foreground">When off, SMS steps are skipped.</p>
          </div>
          <Switch checked={enabled} onCheckedChange={setEnabled} />
        </div>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div className="space-y-1.5">
            <Label>Account SID</Label>
            <Input
              placeholder="ACxxxxxxxxxxxxxxxx"
              value={accountSid}
              onChange={(e) => setAccountSid(e.target.value)}
            />
          </div>
          <div className="space-y-1.5">
            <Label>From number</Label>
            <Input
              placeholder="+15550100"
              value={fromNumber}
              onChange={(e) => setFromNumber(e.target.value)}
            />
          </div>
        </div>

        <div className="space-y-1.5">
          <Label>Auth token {data?.has_auth_token && <span className="text-xs text-muted-foreground">(leave blank to keep current)</span>}</Label>
          <Input
            type="password"
            placeholder={data?.has_auth_token ? "•••••••• (unchanged)" : "Twilio auth token"}
            value={authToken}
            onChange={(e) => setAuthToken(e.target.value)}
          />
        </div>

        <Button onClick={handleSave} disabled={save.isPending}>
          {save.isPending && <Loader2 className="h-4 w-4 mr-1 animate-spin" />}
          Save SMS settings
        </Button>
      </CardContent>
    </Card>
  );
}
