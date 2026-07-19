/** Create/edit a reusable email or SMS template, with placeholder picker + preview. */
import { useMemo, useRef, useState } from "react";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Loader2, Send } from "lucide-react";
import { toast } from "sonner";
import { PlaceholderPicker } from "./PlaceholderPicker";
import { useTestSend, useUpsertTemplate } from "@/hooks/useCommsAutomations";
import { PLACEHOLDER_GROUPS } from "@/lib/comms-automations/catalog";
import type { CommsChannel, CommsTemplate } from "@/lib/comms-automations/types";

interface TemplateEditorProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Existing template to edit, or null/undefined to create a new one. */
  template?: CommsTemplate | null;
}

/** Sample values for the live preview, drawn from the catalog. */
const SAMPLE: Record<string, string> = PLACEHOLDER_GROUPS.flatMap((g) => g.fields).reduce(
  (acc, f) => { acc[f.key] = f.sample; return acc; },
  {} as Record<string, string>,
);

function renderPreview(tpl: string): string {
  return tpl.replace(/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/g, (_, k: string) => SAMPLE[k] ?? `{{${k}}}`);
}

export function TemplateEditor({ open, onOpenChange, template }: TemplateEditorProps) {
  const upsert = useUpsertTemplate();
  const testSend = useTestSend();
  const [testTo, setTestTo] = useState("");
  const [channel, setChannel] = useState<CommsChannel>(template?.channel ?? "email");
  const [name, setName] = useState(template?.name ?? "");
  const [subject, setSubject] = useState(template?.subject ?? "");
  const [body, setBody] = useState(template?.body ?? "");
  const bodyRef = useRef<HTMLTextAreaElement>(null);

  // Re-seed local state when a different template is opened.
  const seedKey = template?.id ?? "new";
  const lastSeed = useRef(seedKey);
  if (lastSeed.current !== seedKey) {
    lastSeed.current = seedKey;
    setChannel(template?.channel ?? "email");
    setName(template?.name ?? "");
    setSubject(template?.subject ?? "");
    setBody(template?.body ?? "");
  }

  const insert = (token: string) => {
    const el = bodyRef.current;
    if (!el) { setBody((b) => b + token); return; }
    const start = el.selectionStart ?? el.value.length;
    const end = el.selectionEnd ?? el.value.length;
    setBody(`${el.value.slice(0, start)}${token}${el.value.slice(end)}`);
  };

  const preview = useMemo(() => ({
    subject: renderPreview(subject),
    body: renderPreview(body),
  }), [subject, body]);

  const handleTest = async () => {
    if (!testTo.trim()) { toast.error(`Enter a test ${channel === "email" ? "email" : "phone"}`); return; }
    try {
      await testSend.mutateAsync({
        channel,
        to: testTo.trim(),
        subject: channel === "email" ? subject : undefined,
        body,
      });
      toast.success(`Test ${channel} sent to ${testTo.trim()}`);
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Test send failed");
    }
  };

  const handleSave = async () => {
    if (!name.trim()) { toast.error("Template needs a name"); return; }
    try {
      await upsert.mutateAsync({
        ...(template?.id ? { id: template.id } : {}),
        channel,
        name: name.trim(),
        subject: channel === "email" ? subject : null,
        body,
      });
      toast.success(template?.id ? "Template updated" : "Template created");
      onOpenChange(false);
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Save failed");
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-3xl">
        <DialogHeader>
          <DialogTitle>{template?.id ? "Edit template" : "New template"}</DialogTitle>
        </DialogHeader>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-1.5">
            <Label>Channel</Label>
            <Select value={channel} onValueChange={(v) => setChannel(v as CommsChannel)} disabled={!!template?.id}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="email">Email</SelectItem>
                <SelectItem value="sms">SMS</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label>Name</Label>
            <Input placeholder="Renewal reminder" value={name} onChange={(e) => setName(e.target.value)} />
          </div>
        </div>

        {channel === "email" && (
          <div className="space-y-1.5">
            <Label>Subject</Label>
            <Input
              placeholder="Your plan renews soon"
              value={subject}
              onChange={(e) => setSubject(e.target.value)}
            />
          </div>
        )}

        <div className="space-y-1.5">
          <div className="flex items-center justify-between">
            <Label>{channel === "email" ? "Body (HTML)" : "Message"}</Label>
            <PlaceholderPicker onInsert={insert} />
          </div>
          <Textarea
            ref={bodyRef}
            rows={channel === "email" ? 8 : 4}
            className={channel === "email" ? "font-mono text-sm" : ""}
            placeholder={
              channel === "email"
                ? "<p>Hi {{patient.first_name}}, your plan renews on {{subscription.renewal_date}}.</p>"
                : "Hi {{patient.first_name}} — your order {{order.order_number}} shipped!"
            }
            value={body}
            onChange={(e) => setBody(e.target.value)}
          />
        </div>

        <div className="rounded-md border bg-muted/30 p-3">
          <p className="mb-1 text-xs font-medium text-muted-foreground">Preview (sample data)</p>
          {channel === "email" && preview.subject && (
            <p className="mb-1 text-sm font-medium">{preview.subject}</p>
          )}
          {channel === "email" ? (
            <div className="prose prose-sm max-w-none" dangerouslySetInnerHTML={{ __html: preview.body }} />
          ) : (
            <p className="text-sm whitespace-pre-wrap">{preview.body}</p>
          )}
        </div>

        <div className="flex items-center gap-2 rounded-md border bg-muted/30 p-2">
          <Send className="h-4 w-4 text-muted-foreground" />
          <Input
            className="h-8 max-w-xs"
            placeholder={channel === "email" ? "you@example.com" : "+15550100"}
            value={testTo}
            onChange={(e) => setTestTo(e.target.value)}
          />
          <Button variant="outline" size="sm" onClick={handleTest} disabled={testSend.isPending}>
            {testSend.isPending ? <Loader2 className="h-4 w-4 mr-1 animate-spin" /> : null}
            Send test
          </Button>
          <span className="text-xs text-muted-foreground">Renders with sample data.</span>
        </div>

        <DialogFooter>
          <Button variant="ghost" onClick={() => onOpenChange(false)}>Cancel</Button>
          <Button onClick={handleSave} disabled={upsert.isPending}>
            {upsert.isPending && <Loader2 className="h-4 w-4 mr-1 animate-spin" />}
            {template?.id ? "Save changes" : "Create template"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
