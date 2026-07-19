import { useState } from "react";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Key, Copy, Eye, EyeOff, Plus, Trash2 } from "lucide-react";
import { toast } from "sonner";
import { useAuditLog } from "@/hooks/useAuditLog";
import { dateTime } from "@/lib/dayjs";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";

interface ApiKey {
  id: string;
  name: string;
  prefix: string;
  createdAt: string;
  lastUsed: string | null;
  isActive: boolean;
}

// Mock API keys for demonstration - in production these would come from the database
const mockApiKeys: ApiKey[] = [
  {
    id: "1",
    name: "Production API Key",
    prefix: "pk_live_****",
    createdAt: "2024-01-15",
    lastUsed: "2024-01-20",
    isActive: true,
  },
  {
    id: "2",
    name: "Development API Key",
    prefix: "pk_test_****",
    createdAt: "2024-01-10",
    lastUsed: "2024-01-19",
    isActive: true,
  },
  {
    id: "3",
    name: "Legacy Integration",
    prefix: "pk_old_****",
    createdAt: "2023-12-01",
    lastUsed: null,
    isActive: false,
  },
];

export function ApiKeySettings() {
  const { logAction } = useAuditLog();
  const [apiKeys, setApiKeys] = useState<ApiKey[]>(mockApiKeys);
  const [showCreateDialog, setShowCreateDialog] = useState(false);
  const [showDeleteDialog, setShowDeleteDialog] = useState(false);
  const [selectedKey, setSelectedKey] = useState<ApiKey | null>(null);
  const [newKeyName, setNewKeyName] = useState("");
  const [visibleKeys, setVisibleKeys] = useState<Set<string>>(new Set());

  const toggleKeyVisibility = (id: string) => {
    setVisibleKeys((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    toast.success("Copied to clipboard");
  };

  const handleCreateKey = async () => {
    if (!newKeyName.trim()) {
      toast.error("Please enter a key name");
      return;
    }

    const newKey: ApiKey = {
      id: crypto.randomUUID(),
      name: newKeyName,
      prefix: `pk_${Date.now().toString(36)}_****`,
      createdAt: dateTime().utc().format("YYYY-MM-DD"),
      lastUsed: null,
      isActive: true,
    };

    setApiKeys((prev) => [...prev, newKey]);

    await logAction({
      action: "create",
      entityType: "api_key",
      entityId: newKey.id,
      afterData: { name: newKey.name, prefix: newKey.prefix },
      tenantId: null,
    });

    toast.success("API key created successfully");
    setShowCreateDialog(false);
    setNewKeyName("");
  };

  const handleRevokeKey = async () => {
    if (!selectedKey) return;

    setApiKeys((prev) => prev.filter((k) => k.id !== selectedKey.id));

    await logAction({
      action: "delete",
      entityType: "api_key",
      entityId: selectedKey.id,
      beforeData: { name: selectedKey.name, prefix: selectedKey.prefix },
      tenantId: null,
    });

    toast.success("API key revoked successfully");
    setShowDeleteDialog(false);
    setSelectedKey(null);
  };

  return (
    <>
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="h-10 w-10 rounded-lg bg-primary/10 flex items-center justify-center">
                <Key className="h-5 w-5 text-primary" />
              </div>
              <div>
                <CardTitle>API Keys</CardTitle>
                <CardDescription>
                  Manage API keys for platform integrations
                </CardDescription>
              </div>
            </div>
            <Button onClick={() => setShowCreateDialog(true)} size="sm">
              <Plus className="h-4 w-4 mr-2" />
              Create Key
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            {apiKeys.map((key) => (
              <div
                key={key.id}
                className="flex items-center justify-between p-4 border rounded-lg"
              >
                <div className="space-y-1">
                  <div className="flex items-center gap-2">
                    <span className="font-medium">{key.name}</span>
                    <Badge variant={key.isActive ? "default" : "secondary"}>
                      {key.isActive ? "Active" : "Inactive"}
                    </Badge>
                  </div>
                  <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <code className="bg-muted px-2 py-0.5 rounded">
                      {visibleKeys.has(key.id)
                        ? key.prefix.replace("****", "abcd1234")
                        : key.prefix}
                    </code>
                    <button
                      data-testid={`button-toggle-key-visibility-${key.id}`}
                      onClick={() => toggleKeyVisibility(key.id)}
                      className="hover:text-foreground"
                    >
                      {visibleKeys.has(key.id) ? (
                        <EyeOff className="h-4 w-4" />
                      ) : (
                        <Eye className="h-4 w-4" />
                      )}
                    </button>
                    <button
                      data-testid={`button-copy-key-${key.id}`}
                      onClick={() => copyToClipboard(key.prefix)}
                      className="hover:text-foreground"
                    >
                      <Copy className="h-4 w-4" />
                    </button>
                  </div>
                  <p className="text-xs text-muted-foreground">
                    Created: {key.createdAt} • Last used:{" "}
                    {key.lastUsed || "Never"}
                  </p>
                </div>
                <Button
                  variant="ghost"
                  size="sm"
                  className="text-destructive hover:text-destructive"
                  onClick={() => {
                    setSelectedKey(key);
                    setShowDeleteDialog(true);
                  }}
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Create Key Dialog */}
      <Dialog open={showCreateDialog} onOpenChange={setShowCreateDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Create API Key</DialogTitle>
            <DialogDescription>
              Create a new API key for platform integrations. Store the key
              securely as it won't be shown again.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label htmlFor="key-name">Key Name</Label>
              <Input
                id="key-name"
                placeholder="e.g., Production API Key"
                value={newKeyName}
                onChange={(e) => setNewKeyName(e.target.value)}
              />
            </div>
          </div>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setShowCreateDialog(false)}
            >
              Cancel
            </Button>
            <Button onClick={handleCreateKey}>Create Key</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <AlertDialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Revoke API Key</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to revoke "{selectedKey?.name}"? This action
              cannot be undone and any integrations using this key will stop
              working.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleRevokeKey}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              Revoke Key
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
