import { useMemo, useState } from "react";
import { useQueries, useMutation, useQueryClient } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useAuditLog } from "@/hooks/useAuditLog";
import { AdminLayout } from "@/components/layouts/AdminLayout";
import { PageHeader } from "@/components/common/PageHeader";
import { DataTable, Column } from "@/components/common/DataTable";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Badge } from "@/components/ui/badge";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { toast } from "sonner";
import {
  AlertCircle,
  ArrowRight,
  CircleDot,
  Loader2,
  MoreHorizontal,
  Pencil,
} from "lucide-react";

interface OrderStatus {
  id: string;
  display_order: number;
  status_key: string;
  patient_status_label: string | null;
  patient_microcopy: string | null;
  patient_action_required: boolean;
  admin_status_label: string;
  admin_microcopy: string | null;
  next_step_owner: string;
  expiration_timer_hours: number | null;
  next_status_id: string | null;
  failure_status_id: string | null;
  is_terminal: boolean;
  is_active: boolean;
  is_patient_visible: boolean;
  created_at: string;
  updated_at: string;
}

interface StatusFormData {
  display_order: string;
  status_key: string;
  patient_status_label: string;
  patient_microcopy: string;
  patient_action_required: boolean;
  admin_status_label: string;
  admin_microcopy: string;
  next_step_owner: string;
  expiration_timer_hours: string;
  next_status_id: string;
  failure_status_id: string;
  is_terminal: boolean;
  is_patient_visible: boolean;
}

const emptyFormData: StatusFormData = {
  display_order: "0",
  status_key: "",
  patient_status_label: "",
  patient_microcopy: "",
  patient_action_required: false,
  admin_status_label: '',
  admin_microcopy: '',
  next_step_owner: 'system',
  expiration_timer_hours: '',
  next_status_id: '',
  failure_status_id: '',
  is_terminal: false,
  is_patient_visible: true,
};

const STATUS_SELECT_NONE = '__none__';

const NEXT_STEP_OWNERS = [
  { value: "system", label: "System" },
  { value: "patient", label: "Patient" },
  { value: "provider", label: "Provider" },
  { value: "pharmacy", label: "Pharmacy" },
  { value: "carrier", label: "Carrier" },
  { value: "ops", label: "Ops" },
  { value: "payment_provider", label: "Payment Provider" },
];

export function OrderStatusesContent() {
  const { logAction } = useAuditLog();
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
  const [selectedStatus, setSelectedStatus] = useState<OrderStatus | null>(
    null,
  );
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [formData, setFormData] = useState<StatusFormData>(emptyFormData);

  const [
    { data: statuses = [], isLoading },
    { data: orderedStatuses = [], isLoading: isOrderedStatusesLoading },
  ] = useQueries({
    queries: [
      {
        queryKey: ["order-statuses", search],
        queryFn: async () => {
          let query = supabase
            .from("order_statuses")
            .select("*")
            .order("display_order", { ascending: true });

          if (search) {
            query = query.or(
              `status_key.ilike.%${search}%,admin_status_label.ilike.%${search}%`,
            );
          }

          const { data, error } = await query;
          if (error) throw error;
          return (data ?? []) as OrderStatus[];
        },
      },
      {
        queryKey: ["order-statuses", "flow"],
        queryFn: async () => {
          const { data, error } = await supabase
            .from("order_statuses")
            .select("*")
            .order("display_order", { ascending: true });

          if (error) throw error;
          return (data ?? []) as OrderStatus[];
        },
      },
    ],
  });

  const statusFlow = useMemo(() => {
    const terminalIndex = orderedStatuses.findIndex(
      (status) => status.is_terminal,
    );
    if (terminalIndex < 0) {
      return orderedStatuses;
    }
    return orderedStatuses.slice(0, terminalIndex + 1);
  }, [orderedStatuses]);

  const statusLookup = useMemo(
    () => new Map(orderedStatuses.map((status) => [status.id, status])),
    [orderedStatuses],
  );

  const transitionStatusOptions = useMemo(
    () => orderedStatuses.filter((status) => status.id !== selectedStatus?.id),
    [orderedStatuses, selectedStatus],
  );

  const validateForm = (): boolean => {
    const errors: Record<string, string> = {};

    if (!formData.status_key.trim()) {
      errors.status_key = "Status key is required";
    } else if (!/^[a-z0-9_]+$/.test(formData.status_key)) {
      errors.status_key =
        "Key must contain only lowercase letters, numbers, and underscores";
    } else if (formData.status_key.length > 50) {
      errors.status_key = "Key must be 50 characters or less";
    }

    if (!formData.admin_status_label.trim()) {
      errors.admin_status_label = "Admin status label is required";
    } else if (formData.admin_status_label.length > 100) {
      errors.admin_status_label =
        "Admin status label must be 100 characters or less";
    }

    if (
      formData.patient_status_label &&
      formData.patient_status_label.length > 100
    ) {
      errors.patient_status_label =
        "Patient status label must be 100 characters or less";
    }

    if (formData.patient_microcopy && formData.patient_microcopy.length > 500) {
      errors.patient_microcopy =
        "Patient microcopy must be 500 characters or less";
    }

    if (formData.admin_microcopy && formData.admin_microcopy.length > 500) {
      errors.admin_microcopy = "Admin microcopy must be 500 characters or less";
    }

    const orderNum = parseInt(formData.display_order, 10);
    if (isNaN(orderNum) || orderNum < 0) {
      errors.display_order =
        "Display order must be a valid non-negative number";
    }

    if (formData.expiration_timer_hours) {
      const hours = parseInt(formData.expiration_timer_hours, 10);
      if (isNaN(hours) || hours < 0) {
        errors.expiration_timer_hours =
          "Expiration timer must be a valid non-negative number";
      }
    }

    setFormErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const updateMutation = useMutation({
    mutationFn: async ({ id, data }: { id: string; data: StatusFormData }) => {
      const beforeStatus = statuses.find((s) => s.id === id);

      const { data: status, error } = await supabase
        .from("order_statuses")
        .update({
          display_order: parseInt(data.display_order, 10) || 0,
          status_key: data.status_key.trim(),
          patient_status_label: data.patient_status_label.trim() || null,
          patient_microcopy: data.patient_microcopy.trim() || null,
          patient_action_required: data.patient_action_required,
          admin_status_label: data.admin_status_label.trim(),
          admin_microcopy: data.admin_microcopy.trim() || null,
          next_step_owner: data.next_step_owner,
          expiration_timer_hours: data.expiration_timer_hours ? parseInt(data.expiration_timer_hours, 10) : null,
          next_status_id: data.next_status_id || null,
          failure_status_id: data.failure_status_id || null,
          is_terminal: data.is_terminal,
          is_patient_visible: data.is_patient_visible,
        })
        .eq("id", id)
        .select()
        .single();

      if (error) throw error;
      return { status: status as OrderStatus, beforeData: beforeStatus };
    },
    onSuccess: ({ status, beforeData }) => {
      queryClient.invalidateQueries({ queryKey: ["order-statuses"] });
      logAction({
        action: "update",
        entityType: "order_status",
        entityId: status.id,
        beforeData: beforeData as unknown as Record<string, unknown>,
        afterData: status as unknown as Record<string, unknown>,
        tenantId: null,
      });
      toast.success("Order status updated successfully");
      setIsEditDialogOpen(false);
      setSelectedStatus(null);
      setFormData(emptyFormData);
      setFormErrors({});
    },
    onError: (error) => {
      if (error.message.includes("duplicate key")) {
        toast.error("An order status with this key already exists");
      } else {
        toast.error(
          error instanceof Error
            ? error.message
            : "Failed to update order status",
        );
      }
    },
  });

  const toggleMutation = useMutation({
    mutationFn: async ({
      id,
      is_active,
    }: {
      id: string;
      is_active: boolean;
    }) => {
      const beforeStatus = statuses.find((s) => s.id === id);

      const { data, error } = await supabase
        .from("order_statuses")
        .update({ is_active })
        .eq("id", id)
        .select()
        .single();

      if (error) throw error;
      return { status: data as OrderStatus, beforeData: beforeStatus };
    },
    onSuccess: ({ status, beforeData }) => {
      queryClient.invalidateQueries({ queryKey: ["order-statuses"] });
      logAction({
        action: "update",
        entityType: "order_status",
        entityId: status.id,
        beforeData: { is_active: beforeData?.is_active },
        afterData: { is_active: status.is_active },
        tenantId: null,
      });
      toast.success(
        `Order status ${status.is_active ? "activated" : "deactivated"}`,
      );
    },
    onError: (error) => {
      toast.error(
        error instanceof Error
          ? error.message
          : "Failed to update order status",
      );
    },
  });

  const handleOpenEdit = (status: OrderStatus) => {
    setSelectedStatus(status);
    setFormData({
      display_order: status.display_order.toString(),
      status_key: status.status_key,
      patient_status_label: status.patient_status_label || "",
      patient_microcopy: status.patient_microcopy || "",
      patient_action_required: status.patient_action_required,
      admin_status_label: status.admin_status_label,
      admin_microcopy: status.admin_microcopy || "",
      next_step_owner: status.next_step_owner,
      expiration_timer_hours: status.expiration_timer_hours?.toString() || '',
      next_status_id: status.next_status_id || '',
      failure_status_id: status.failure_status_id || '',
      is_terminal: status.is_terminal,
      is_patient_visible: status.is_patient_visible,
    });
    setFormErrors({});
    setIsEditDialogOpen(true);
  };

  const handleEditSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedStatus) return;
    if (!validateForm()) {
      toast.error("Please fix the validation errors");
      return;
    }
    updateMutation.mutate({ id: selectedStatus.id, data: formData });
  };

  const getOwnerBadgeVariant = (owner: string) => {
    switch (owner) {
      case "patient":
        return "default";
      case "provider":
        return "secondary";
      case "pharmacy":
        return "outline";
      case "system":
        return "outline";
      default:
        return "outline";
    }
  };

  const getLinkedStatusLabel = (statusId: string | null) => {
    if (!statusId) {
      return '—';
    }

    const linkedStatus = statusLookup.get(statusId);
    if (!linkedStatus) {
      return 'Unknown status';
    }

    return linkedStatus.admin_status_label;
  };

  const getFlowFailureStatus = (status: OrderStatus) => {
    if (!status.failure_status_id) {
      return null;
    }

    return statusLookup.get(status.failure_status_id) ?? null;
  };

  const columns: Column<OrderStatus>[] = [
    {
      key: "order",
      header: "#",
      cell: (status) => (
        <span className="text-sm font-mono text-muted-foreground">
          {status.display_order}
        </span>
      ),
      className: "w-12",
    },
    {
      key: "status",
      header: "Status",
      cell: (status) => (
        <div className="flex items-center gap-3">
          <div className="h-10 w-10 rounded-lg bg-primary/10 flex items-center justify-center">
            <CircleDot className="h-5 w-5 text-primary" />
          </div>
          <div>
            <p className="font-medium">{status.admin_status_label}</p>
            <p className="text-sm text-muted-foreground font-mono">
              {status.status_key}
            </p>
          </div>
        </div>
      ),
    },
    {
      key: "patient_label",
      header: "Patient View",
      cell: (status) => (
        <div className="max-w-xs">
          {status.is_patient_visible ? (
            <>
              <p className="text-sm font-medium">
                {status.patient_status_label || "—"}
              </p>
              {status.patient_action_required && (
                <Badge variant="destructive" className="mt-1 gap-1">
                  <AlertCircle className="h-3 w-3" />
                  Action Required
                </Badge>
              )}
            </>
          ) : (
            <Badge variant="outline" className="text-muted-foreground">
              Hidden
            </Badge>
          )}
        </div>
      ),
    },
    {
      key: "owner",
      header: "Next Step Owner",
      cell: (status) => (
        <Badge
          variant={getOwnerBadgeVariant(status.next_step_owner)}
          className="capitalize"
        >
          {status.next_step_owner.replace("_", " ")}
        </Badge>
      ),
    },
    {
      key: 'transitions',
      header: 'Transitions',
      cell: (status) => (
        <div className="space-y-1 text-sm">
          <p>Next: <span className="text-muted-foreground">{getLinkedStatusLabel(status.next_status_id)}</span></p>
          <p>Failure: <span className="text-muted-foreground">{getLinkedStatusLabel(status.failure_status_id)}</span></p>
        </div>
      ),
    },
    {
      key: 'terminal',
      header: 'Terminal',
      cell: (status) => (
        status.is_terminal ? (
          <Badge variant="secondary">Terminal</Badge>
        ) : (
          <span className="text-muted-foreground text-sm">—</span>
        )
      ),
      className: "w-24",
    },
    {
      key: "active",
      header: "Active",
      cell: (status) => (
        <Switch
          checked={status.is_active}
          onCheckedChange={(checked) => {
            toggleMutation.mutate({ id: status.id, is_active: checked });
          }}
          disabled={toggleMutation.isPending}
          aria-label={`Toggle ${status.admin_status_label} active state`}
        />
      ),
      className: "w-20",
    },
    {
      key: "actions",
      header: "",
      cell: (status) => (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem onClick={() => handleOpenEdit(status)}>
              <Pencil className="h-4 w-4 mr-2" />
              Edit
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      ),
      className: "w-12",
    },
  ];

  const renderEditForm = (onSubmit: (e: React.FormEvent) => void) => (
    <form onSubmit={onSubmit}>
      <DialogHeader>
        <DialogTitle>Edit Order Status</DialogTitle>
        <DialogDescription>
          Update the order status details.
        </DialogDescription>
      </DialogHeader>
      <div className="grid gap-4 py-4 max-h-[60vh] overflow-y-auto">
        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="display_order">Display Order</Label>
            <Input
              id="display_order"
              type="number"
              min="0"
              value={formData.display_order}
              onChange={(e) =>
                setFormData({ ...formData, display_order: e.target.value })
              }
              placeholder="0"
              className={formErrors.display_order ? "border-destructive" : ""}
            />
            {formErrors.display_order && (
              <p className="text-sm text-destructive">
                {formErrors.display_order}
              </p>
            )}
          </div>
          <div className="space-y-2">
            <Label htmlFor="status_key">Status Key *</Label>
            <Input
              id="status_key"
              value={formData.status_key}
              onChange={(e) =>
                setFormData({
                  ...formData,
                  status_key: e.target.value
                    .toLowerCase()
                    .replace(/[^a-z0-9_]/g, ""),
                })
              }
              maxLength={50}
              placeholder="e.g., payment_pending"
              className={formErrors.status_key ? "border-destructive" : ""}
              disabled
            />
            <p className="text-sm text-muted-foreground">
              Status key cannot be changed after creation
            </p>
            {formErrors.status_key && (
              <p className="text-sm text-destructive">
                {formErrors.status_key}
              </p>
            )}
          </div>
        </div>

        <div className="space-y-4 border rounded-lg p-4">
          <h4 className="font-medium text-sm">Admin View</h4>
          <div className="space-y-2">
            <Label htmlFor="admin_status_label">Admin Status Label *</Label>
            <Input
              id="admin_status_label"
              value={formData.admin_status_label}
              onChange={(e) => {
                setFormData({
                  ...formData,
                  admin_status_label: e.target.value,
                });
              }}
              maxLength={100}
              placeholder="e.g., Payment pending"
              className={
                formErrors.admin_status_label ? "border-destructive" : ""
              }
            />
            {formErrors.admin_status_label && (
              <p className="text-sm text-destructive">
                {formErrors.admin_status_label}
              </p>
            )}
          </div>
          <div className="space-y-2">
            <Label htmlFor="admin_microcopy">Admin Microcopy</Label>
            <Textarea
              id="admin_microcopy"
              value={formData.admin_microcopy}
              onChange={(e) =>
                setFormData({ ...formData, admin_microcopy: e.target.value })
              }
              maxLength={500}
              placeholder="Detailed description for admins"
              rows={2}
              className={formErrors.admin_microcopy ? "border-destructive" : ""}
            />
            {formErrors.admin_microcopy && (
              <p className="text-sm text-destructive">
                {formErrors.admin_microcopy}
              </p>
            )}
          </div>
        </div>

        <div className="space-y-4 border rounded-lg p-4">
          <div className="flex items-center justify-between">
            <h4 className="font-medium text-sm">Patient View</h4>
            <div className="flex items-center space-x-2">
              <Switch
                id="is_patient_visible"
                checked={formData.is_patient_visible}
                onCheckedChange={(checked) =>
                  setFormData({ ...formData, is_patient_visible: checked })
                }
              />
              <Label
                htmlFor="is_patient_visible"
                className="text-sm font-normal"
              >
                Visible to patients
              </Label>
            </div>
          </div>
          <div className="space-y-2">
            <Label htmlFor="patient_status_label">Patient Status Label</Label>
            <Input
              id="patient_status_label"
              value={formData.patient_status_label}
              onChange={(e) =>
                setFormData({
                  ...formData,
                  patient_status_label: e.target.value,
                })
              }
              maxLength={100}
              placeholder="e.g., Payment confirmed"
              className={
                formErrors.patient_status_label ? "border-destructive" : ""
              }
              disabled={!formData.is_patient_visible}
            />
            {formErrors.patient_status_label && (
              <p className="text-sm text-destructive">
                {formErrors.patient_status_label}
              </p>
            )}
          </div>
          <div className="space-y-2">
            <Label htmlFor="patient_microcopy">Patient Microcopy</Label>
            <Textarea
              id="patient_microcopy"
              value={formData.patient_microcopy}
              onChange={(e) =>
                setFormData({ ...formData, patient_microcopy: e.target.value })
              }
              maxLength={500}
              placeholder="Friendly message shown to patients"
              rows={2}
              className={
                formErrors.patient_microcopy ? "border-destructive" : ""
              }
              disabled={!formData.is_patient_visible}
            />
            {formErrors.patient_microcopy && (
              <p className="text-sm text-destructive">
                {formErrors.patient_microcopy}
              </p>
            )}
          </div>
          <div className="flex items-center space-x-2">
            <Checkbox
              id="patient_action_required"
              checked={formData.patient_action_required}
              onCheckedChange={(checked) =>
                setFormData({
                  ...formData,
                  patient_action_required: checked === true,
                })
              }
              disabled={!formData.is_patient_visible}
            />
            <Label
              htmlFor="patient_action_required"
              className="text-sm font-normal"
            >
              Patient action required
            </Label>
          </div>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="next_step_owner">Next Step Owner</Label>
            <Select
              value={formData.next_step_owner}
              onValueChange={(value) =>
                setFormData({ ...formData, next_step_owner: value })
              }
            >
              <SelectTrigger id="next_step_owner">
                <SelectValue placeholder="Select owner" />
              </SelectTrigger>
              <SelectContent>
                {NEXT_STEP_OWNERS.map((owner) => (
                  <SelectItem key={owner.value} value={owner.value}>
                    {owner.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-2">
            <Label htmlFor="expiration_timer_hours">
              Expiration Timer (hours)
            </Label>
            <Input
              id="expiration_timer_hours"
              type="number"
              min="0"
              value={formData.expiration_timer_hours}
              onChange={(e) =>
                setFormData({
                  ...formData,
                  expiration_timer_hours: e.target.value,
                })
              }
              placeholder="Optional"
              className={
                formErrors.expiration_timer_hours ? "border-destructive" : ""
              }
            />
            {formErrors.expiration_timer_hours && (
              <p className="text-sm text-destructive">
                {formErrors.expiration_timer_hours}
              </p>
            )}
          </div>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="next_status_id">Next Status</Label>
            <Select
              value={formData.next_status_id || STATUS_SELECT_NONE}
              onValueChange={(value) => setFormData({
                ...formData,
                next_status_id: value === STATUS_SELECT_NONE ? '' : value,
              })}
            >
              <SelectTrigger id="next_status_id">
                <SelectValue placeholder="Use display order fallback" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={STATUS_SELECT_NONE}>Use display order fallback</SelectItem>
                {transitionStatusOptions.map((status) => (
                  <SelectItem key={status.id} value={status.id}>
                    {status.display_order}. {status.admin_status_label}
                    {!status.is_active ? ' (inactive)' : ''}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-2">
            <Label htmlFor="failure_status_id">Failure Status</Label>
            <Select
              value={formData.failure_status_id || STATUS_SELECT_NONE}
              onValueChange={(value) => setFormData({
                ...formData,
                failure_status_id: value === STATUS_SELECT_NONE ? '' : value,
              })}
            >
              <SelectTrigger id="failure_status_id">
                <SelectValue placeholder="No failure status configured" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={STATUS_SELECT_NONE}>No failure status configured</SelectItem>
                {transitionStatusOptions.map((status) => (
                  <SelectItem key={status.id} value={status.id}>
                    {status.display_order}. {status.admin_status_label}
                    {!status.is_active ? ' (inactive)' : ''}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </div>

        <div className="flex items-center space-x-2">
          <Checkbox
            id="is_terminal"
            checked={formData.is_terminal}
            onCheckedChange={(checked) =>
              setFormData({ ...formData, is_terminal: checked === true })
            }
          />
          <Label htmlFor="is_terminal" className="text-sm font-normal">
            This is a terminal state (order cannot progress further)
          </Label>
        </div>
      </div>
      <DialogFooter>
        <Button
          type="button"
          variant="outline"
          onClick={() => {
            setIsEditDialogOpen(false);
            setFormData(emptyFormData);
            setFormErrors({});
          }}
        >
          Cancel
        </Button>
        <Button type="submit" disabled={updateMutation.isPending}>
          {updateMutation.isPending && (
            <Loader2 className="h-4 w-4 mr-2 animate-spin" />
          )}
          Save Changes
        </Button>
      </DialogFooter>
    </form>
  );

  return (
    <>
      <PageHeader
        title="Order Statuses"
        description="Manage the different states an order can be in throughout its lifecycle."
      />

      <div className="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Order Status Flow</CardTitle>
            <CardDescription>
              Default workflow based on display order. Explicit next and failure links can also be configured per status.
            </CardDescription>
          </CardHeader>
          <CardContent>
            {isOrderedStatusesLoading ? (
              <div className="flex items-center justify-center py-4">
                <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
              </div>
            ) : statusFlow.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                No order statuses configured yet.
              </p>
            ) : (
              <div className="space-y-3">
                <div className="overflow-x-auto pb-1">
                  <div className="flex w-max items-stretch gap-2">
                    {statusFlow.map((status, index) => {
                      const failureStatus = getFlowFailureStatus(status);

                      return (
                        <div key={status.id} className="flex items-center gap-2">
                          <div className="min-w-[260px] rounded-lg border p-3">
                            <div className="flex flex-wrap items-center gap-2">
                              <Badge variant="outline">#{status.display_order}</Badge>
                              {!status.is_active ? <Badge variant="secondary">Inactive</Badge> : null}
                              {status.is_terminal ? <Badge variant="destructive">Terminal</Badge> : null}
                            </div>
                            <p className="mt-2 text-sm font-medium">{status.admin_status_label}</p>
                            <p className="text-xs font-mono text-muted-foreground">{status.status_key}</p>
                            {failureStatus ? (
                              <div className="mt-3 border-t pt-3 text-xs">
                                <div className="flex items-start gap-2">
                                  <AlertCircle className="mt-0.5 h-3.5 w-3.5 shrink-0 text-destructive" />
                                  <div>
                                    <p className="font-medium text-foreground">Failure route</p>
                                    <p className="text-muted-foreground">
                                      {failureStatus.admin_status_label}
                                    </p>
                                  </div>
                                </div>
                              </div>
                            ) : null}
                          </div>
                          {index < statusFlow.length - 1 ? (
                            <ArrowRight className="h-4 w-4 shrink-0 text-muted-foreground" />
                          ) : null}
                        </div>
                      );
                    })}
                  </div>
                </div>
                {!statusFlow.some((status) => status.is_terminal) ? (
                  <p className="text-xs text-muted-foreground">
                    No terminal state found in the configured sequence.
                  </p>
                ) : null}
              </div>
            )}
          </CardContent>
        </Card>

        <DataTable
          columns={columns}
          data={statuses}
          isLoading={isLoading}
          searchValue={search}
          onSearchChange={setSearch}
          searchPlaceholder="Search order statuses..."
          emptyMessage={
            search
              ? "No order statuses found. Try adjusting your search terms."
              : "No order statuses found."
          }
        />
      </div>

      {/* Edit Dialog */}
      <Dialog open={isEditDialogOpen} onOpenChange={setIsEditDialogOpen}>
        <DialogContent className="max-w-2xl">
          {renderEditForm(handleEditSubmit)}
        </DialogContent>
      </Dialog>
    </>
  );
}

/** Original route entry point — keeps the standalone page working unchanged. */
export default function OrderStatuses() {
  return (
    <AdminLayout variant="platform">
      <OrderStatusesContent />
    </AdminLayout>
  );
}
