import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
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
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import {
  useCreateCoupon,
  useDeactivateCoupon,
  useProductCoupons,
  useToggleProductPromoCodes,
  useUpdateCoupon,
  type CreateCouponInput,
  type StripePromotionCode,
  type UpdateCouponInput,
} from "@/hooks/useProductCoupons";
import { dateTime } from "@/lib/dayjs";
import { cn } from "@/lib/utils";
import {
  AlertCircle,
  ArrowLeft,
  ArrowUpDown,
  CalendarIcon,
  Copy,
  Loader2,
  Pencil,
  Plus,
  Tag,
  X,
} from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

// ─── Types ─────────────────────────────────────────────────────────────────────

interface Props {
  productId: string;
  tenantId: string | null;
  /** Whether patients can manually enter a promo code on the Stripe checkout page. */
  allowPromoCodes: boolean;
  readOnly?: boolean;
}

interface FormState {
  name: string;
  coupon_type: "internal" | "marketing";
  code: string;
  discount_type: "percent" | "amount";
  percent_off: string;
  amount_off: string;
  currency: string;
  duration: "once" | "repeating" | "forever";
  duration_in_months: string;
  max_redemptions: string;
  expires_at: Date | undefined;
}

const DEFAULT_FORM: FormState = {
  name: "",
  coupon_type: "marketing",
  code: "",
  discount_type: "percent",
  percent_off: "",
  amount_off: "",
  currency: "usd",
  duration: "once",
  duration_in_months: "",
  max_redemptions: "",
  expires_at: undefined,
};

// ─── Helpers ───────────────────────────────────────────────────────────────────

function formatDiscount(promo: StripePromotionCode): string {
  const { coupon } = promo;
  if (coupon.percent_off != null) return `${coupon.percent_off}% off`;
  if (coupon.amount_off != null) {
    const amount = (coupon.amount_off / 100).toFixed(2);
    const currency = (coupon.currency ?? "usd").toUpperCase();
    return `-${currency} ${amount}`;
  }
  return "—";
}

function formatDuration(promo: StripePromotionCode): string {
  const { duration, duration_in_months } = promo.coupon;
  if (duration === "once") return "Once";
  if (duration === "forever") return "Forever";
  if (duration === "repeating" && duration_in_months != null) {
    return `${duration_in_months} month${duration_in_months > 1 ? "s" : ""}`;
  }
  return duration ?? "—";
}

function generateCode(): string {
  const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
  return Array.from(
    { length: 8 },
    () => chars[Math.floor(Math.random() * chars.length)],
  ).join("");
}

// ─── Component ─────────────────────────────────────────────────────────────────

export function ProductCouponsManager({
  productId,
  tenantId,
  allowPromoCodes,
  readOnly = false,
}: Props) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [dialogStep, setDialogStep] = useState<"form" | "preview">("form");
  const [form, setForm] = useState<FormState>(DEFAULT_FORM);
  const [formErrors, setFormErrors] = useState<
    Partial<Record<keyof FormState, string>>
  >({});
  const [calendarOpen, setCalendarOpen] = useState(false);
  const [editingPromo, setEditingPromo] = useState<StripePromotionCode | null>(
    null,
  );
  const [editForm, setEditForm] = useState<{
    name: string;
    coupon_type: "internal" | "marketing";
  }>({
    name: "",
    coupon_type: "marketing",
  });
  const [editFormErrors, setEditFormErrors] = useState<{ name?: string }>({});
  const [sortOrder, setSortOrder] = useState<"asc" | "desc">("desc");

  const {
    data: coupons = [],
    isLoading,
    error,
  } = useProductCoupons(productId, tenantId);
  const createCoupon = useCreateCoupon(productId, tenantId);
  const deactivateCoupon = useDeactivateCoupon(productId, tenantId);
  const togglePromoCodes = useToggleProductPromoCodes(productId, tenantId);
  const updateCoupon = useUpdateCoupon(productId, tenantId);

  function updateForm<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm((prev) => ({ ...prev, [key]: value }));
    setFormErrors((prev) => ({ ...prev, [key]: undefined }));
  }

  function validateForm(): boolean {
    const errors: Partial<Record<keyof FormState, string>> = {};

    if (!form.name.trim()) {
      errors.name = "Coupon name is required";
    }

    if (!form.code.trim()) {
      errors.code = "Promotion code is required";
    } else if (!/^[A-Z0-9\-_]+$/i.test(form.code.trim())) {
      errors.code =
        "Only letters, numbers, hyphens, and underscores are allowed";
    }

    if (form.discount_type === "percent") {
      const val = parseFloat(form.percent_off);
      if (!form.percent_off || isNaN(val) || val < 1 || val > 100) {
        errors.percent_off = "Enter a percentage between 1 and 100";
      }
    } else {
      const val = parseFloat(form.amount_off);
      if (!form.amount_off || isNaN(val) || val <= 0) {
        errors.amount_off = "Enter a positive amount";
      }
    }

    if (form.duration === "repeating") {
      const val = parseInt(form.duration_in_months, 10);
      if (!form.duration_in_months || isNaN(val) || val < 1) {
        errors.duration_in_months = "Enter a positive number of months";
      }
    }

    if (form.max_redemptions) {
      const val = parseInt(form.max_redemptions, 10);
      if (isNaN(val) || val < 1) {
        errors.max_redemptions = "Must be a positive integer";
      }
    }

    setFormErrors(errors);
    return Object.keys(errors).length === 0;
  }

  function handleOpenDialog() {
    if (readOnly) return;
    setForm(DEFAULT_FORM);
    setFormErrors({});
    setDialogStep("form");
    setDialogOpen(true);
  }

  function handleCloseDialog() {
    if (createCoupon.isPending) return;
    setDialogOpen(false);
    setDialogStep("form");
  }

  function handleOpenEdit(promo: StripePromotionCode) {
    if (readOnly) return;
    setEditingPromo(promo);
    setEditForm({
      name: promo.coupon.name ?? "",
      coupon_type: promo.coupon_type ?? "marketing",
    });
    setEditFormErrors({});
  }

  function handleCloseEdit() {
    if (updateCoupon.isPending) return;
    setEditingPromo(null);
  }

  async function handleEditSubmit() {
    if (!editingPromo) return;
    if (!editForm.name.trim()) {
      setEditFormErrors({ name: "Coupon name is required" });
      return;
    }
    const input: UpdateCouponInput = {
      promotion_code_id: editingPromo.id,
      coupon_id: editingPromo.coupon.id,
      name: editForm.name.trim(),
      coupon_type: editForm.coupon_type,
    };
    await updateCoupon.mutateAsync(input);
    setEditingPromo(null);
  }

  function handleClipboardCopy(code: string) {
    navigator.clipboard.writeText(code).then(() => {
      toast.success(`Copied "${code}" to clipboard`);
    });
  }

  async function handleSubmit() {
    if (!validateForm()) return;
    setDialogStep("preview");
  }

  async function handleConfirmCreate() {
    const input: CreateCouponInput = {
      name: form.name.trim(),
      coupon_type: form.coupon_type,
      code: form.code.trim().toUpperCase(),
      discount_type: form.discount_type,
      duration: form.duration,
    };

    if (form.discount_type === "percent") {
      input.percent_off = parseFloat(form.percent_off);
    } else {
      input.amount_off = Math.round(parseFloat(form.amount_off) * 100);
      input.currency = form.currency;
    }

    if (form.duration === "repeating") {
      input.duration_in_months = parseInt(form.duration_in_months, 10);
    }

    if (form.max_redemptions) {
      input.max_redemptions = parseInt(form.max_redemptions, 10);
    }

    if (form.expires_at) {
      input.expires_at = Math.floor(form.expires_at.getTime() / 1000);
    }

    await createCoupon.mutateAsync(input);
    setDialogOpen(false);
    setDialogStep("form");
  }

  const isStripeNotConfigured =
    !isLoading && !!error?.message?.includes("NO_STRIPE_KEY");

  const sortedCoupons = [...coupons].sort((a, b) =>
    sortOrder === "desc" ? b.created - a.created : a.created - b.created,
  );

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div>
            <CardTitle className="flex items-center gap-2">
              <Tag className="h-5 w-5" />
              Coupons
            </CardTitle>
            <CardDescription>
              Manage Stripe promotion codes for this product. Enable promo code
              entry so eligible patients can enter a code at checkout.
            </CardDescription>
          </div>
          {!readOnly && (
            <Button onClick={handleOpenDialog} size="sm">
              <Plus className="mr-2 h-4 w-4" />
              Create coupon
            </Button>
          )}
        </div>
      </CardHeader>
      <CardContent>
        <div className="flex items-center justify-between rounded-lg border p-3 mb-4">
          <div>
            <p className="text-sm font-medium">
              Allow promo code entry at checkout
            </p>
            <p className="text-xs text-muted-foreground mt-0.5">
              Patients will see a promo code field on Stripe's checkout page.
            </p>
          </div>
          <Switch
            checked={allowPromoCodes}
            onCheckedChange={(checked) => {
              if (readOnly) return;
              togglePromoCodes.mutate(checked);
            }}
            disabled={readOnly || togglePromoCodes.isPending}
          />
        </div>
        {isLoading && (
          <div className="flex items-center gap-2 text-muted-foreground py-8 justify-center">
            <Loader2 className="h-4 w-4 animate-spin" />
            <span>Loading coupons…</span>
          </div>
        )}

        {!isLoading && isStripeNotConfigured && (
          <div className="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4">
            <AlertCircle className="h-5 w-5 text-amber-600 mt-0.5 shrink-0" />
            <div>
              <p className="font-medium text-amber-800">
                Stripe not configured
              </p>
              <p className="text-sm text-amber-700 mt-1">
                Configure the Stripe payment provider for this tenant before
                managing coupons.
              </p>
            </div>
          </div>
        )}

        {!isLoading && !isStripeNotConfigured && error && (
          <div className="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4">
            <AlertCircle className="h-5 w-5 text-red-600 mt-0.5 shrink-0" />
            <div>
              <p className="font-medium text-red-800">Error loading coupons</p>
              <p className="text-sm text-red-700 mt-1">{error.message}</p>
            </div>
          </div>
        )}

        {!isLoading && !error && coupons.length === 0 && (
          <div className="text-center py-10 text-muted-foreground">
            <Tag className="mx-auto h-8 w-8 mb-3 opacity-40" />
            <p className="font-medium">No coupons yet</p>
            <p className="text-sm mt-1">
              Create a coupon to offer discounts on this product at checkout.
            </p>
          </div>
        )}

        {!isLoading && !error && coupons.length > 0 && (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Name</TableHead>
                <TableHead>Code</TableHead>
                <TableHead>Type</TableHead>
                <TableHead>Discount</TableHead>
                <TableHead>Duration</TableHead>
                <TableHead>Redemptions</TableHead>
                <TableHead>Expires</TableHead>
                <TableHead>Status</TableHead>
                <TableHead
                  className="cursor-pointer select-none"
                  onClick={() =>
                    setSortOrder((o) => (o === "desc" ? "asc" : "desc"))
                  }
                >
                  <span className="flex items-center gap-1">
                    Created
                    <ArrowUpDown className="h-3.5 w-3.5 text-muted-foreground" />
                  </span>
                </TableHead>
                <TableHead>Created by</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {sortedCoupons.map((promo) => {
                return (
                  <TableRow key={promo.id}>
                    <TableCell>
                      <span className="font-medium">
                        {promo.coupon.name ?? "—"}
                      </span>
                    </TableCell>
                    <TableCell className="font-mono">
                      <span className="flex items-center gap-1">
                        {promo.code}
                        <Button
                          variant="ghost"
                          size="icon"
                          className="h-6 w-6 shrink-0"
                          title="Copy code to clipboard"
                          onClick={() => handleClipboardCopy(promo.code)}
                        >
                          <Copy className="h-3.5 w-3.5" />
                        </Button>
                      </span>
                    </TableCell>
                    <TableCell>
                      {promo.coupon_type === "internal" ? (
                        <Badge className="bg-purple-100 text-purple-700 hover:bg-purple-100 border border-purple-200">
                          Internal
                        </Badge>
                      ) : promo.coupon_type === "marketing" ? (
                        <Badge className="bg-blue-100 text-blue-700 hover:bg-blue-100 border border-blue-200">
                          Marketing
                        </Badge>
                      ) : (
                        <span className="text-muted-foreground text-sm">—</span>
                      )}
                    </TableCell>
                    <TableCell>{formatDiscount(promo)}</TableCell>
                    <TableCell>{formatDuration(promo)}</TableCell>
                    <TableCell>
                      {promo.times_redeemed}
                      {promo.max_redemptions != null &&
                        ` / ${promo.max_redemptions}`}
                    </TableCell>
                    <TableCell>
                      {promo.expires_at
                        ? dateTime.unix(promo.expires_at).format("DD MMM YYYY")
                        : "—"}
                    </TableCell>
                    <TableCell>
                      <Badge variant={promo.active ? "default" : "secondary"}>
                        {promo.active ? "Active" : "Inactive"}
                      </Badge>
                    </TableCell>
                    <TableCell className="whitespace-nowrap text-sm">
                      {dateTime.unix(promo.created).format("DD MMM YYYY")}
                    </TableCell>
                    <TableCell className="text-sm text-muted-foreground max-w-[160px] truncate">
                      {promo.created_by ?? "—"}
                    </TableCell>
                    <TableCell>
                      {!readOnly && (
                        <div className="flex items-center justify-end gap-1">
                          {promo.active && (
                            <>
                              <Button
                                variant="ghost"
                                size="icon"
                                title="Edit coupon"
                                onClick={() => handleOpenEdit(promo)}
                                disabled={updateCoupon.isPending}
                              >
                                <Pencil className="h-4 w-4" />
                              </Button>
                              <Button
                                variant="ghost"
                                size="icon"
                                title="Deactivate coupon"
                                onClick={() => deactivateCoupon.mutate(promo.id)}
                                disabled={deactivateCoupon.isPending}
                              >
                                {deactivateCoupon.isPending ? (
                                  <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                  <X className="h-4 w-4" />
                                )}
                              </Button>
                            </>
                          )}
                        </div>
                      )}
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
        )}
      </CardContent>

      {/* Create Coupon Dialog */}
      <Dialog
        open={dialogOpen}
        onOpenChange={(open) => !open && handleCloseDialog()}
      >
        <DialogContent className="max-w-lg">
          {dialogStep === "form" ? (
            <>
              <DialogHeader>
                <DialogTitle className="flex items-center gap-2">
                  <Tag className="h-5 w-5" />
                  Create coupon
                </DialogTitle>
                <DialogDescription>
                  Creates a Stripe coupon and promotion code scoped to this
                  product.
                </DialogDescription>
              </DialogHeader>

              <div className="grid gap-4 py-2">
                {/* Coupon name */}
                <div className="space-y-1.5">
                  <Label htmlFor="coupon-name">Coupon name *</Label>
                  <Input
                    id="coupon-name"
                    placeholder="e.g. Internal Testing, Summer Campaign"
                    value={form.name}
                    onChange={(e) => updateForm("name", e.target.value)}
                    className={formErrors.name ? "border-red-500" : ""}
                  />
                  {formErrors.name && (
                    <p className="text-xs text-red-500">{formErrors.name}</p>
                  )}
                </div>

                {/* Promotion code slug */}
                <div className="space-y-1.5">
                  <Label htmlFor="coupon-code">Promotion code *</Label>
                  <div className="flex gap-2">
                    <Button
                      type="button"
                      variant="outline"
                      onClick={() => updateForm("code", generateCode())}
                    >
                      Generate
                    </Button>
                    <Input
                      id="coupon-code"
                      placeholder="e.g. SUMMER20"
                      value={form.code}
                      onChange={(e) =>
                        updateForm("code", e.target.value.toUpperCase())
                      }
                      className={cn(
                        "flex-1",
                        formErrors.code ? "border-red-500" : "",
                      )}
                    />
                  </div>
                  {formErrors.code && (
                    <p className="text-xs text-red-500">{formErrors.code}</p>
                  )}
                  <p className="text-xs text-muted-foreground">
                    Letters, numbers, hyphens, and underscores only.
                  </p>
                </div>

                {/* Coupon type */}
                <div className="space-y-1.5">
                  <Label>Coupon type</Label>
                  <Select
                    value={form.coupon_type}
                    onValueChange={(v) =>
                      updateForm("coupon_type", v as "internal" | "marketing")
                    }
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="marketing">
                        Marketing — publicly shareable
                      </SelectItem>
                      <SelectItem value="internal">
                        Internal — operational use only
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-1.5">
                  <Label>Discount type *</Label>
                  <Select
                    value={form.discount_type}
                    onValueChange={(v) =>
                      updateForm("discount_type", v as "percent" | "amount")
                    }
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="percent">Percentage off</SelectItem>
                      <SelectItem value="amount">Fixed amount off</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                {/* Discount value */}
                {form.discount_type === "percent" ? (
                  <div className="space-y-1.5">
                    <Label htmlFor="percent-off">
                      Percentage off (1–100) *
                    </Label>
                    <Input
                      id="percent-off"
                      type="number"
                      min={1}
                      max={100}
                      step={1}
                      placeholder="20"
                      value={form.percent_off}
                      onChange={(e) =>
                        updateForm("percent_off", e.target.value)
                      }
                      className={formErrors.percent_off ? "border-red-500" : ""}
                    />
                    {formErrors.percent_off && (
                      <p className="text-xs text-red-500">
                        {formErrors.percent_off}
                      </p>
                    )}
                  </div>
                ) : (
                  <div className="grid grid-cols-2 gap-3">
                    <div className="space-y-1.5">
                      <Label htmlFor="amount-off">Amount off *</Label>
                      <Input
                        id="amount-off"
                        type="number"
                        min={0.01}
                        step={0.01}
                        placeholder="10.00"
                        value={form.amount_off}
                        onChange={(e) =>
                          updateForm("amount_off", e.target.value)
                        }
                        className={
                          formErrors.amount_off ? "border-red-500" : ""
                        }
                      />
                      {formErrors.amount_off && (
                        <p className="text-xs text-red-500">
                          {formErrors.amount_off}
                        </p>
                      )}
                    </div>
                    <div className="space-y-1.5">
                      <Label htmlFor="currency">Currency *</Label>
                      <Select
                        value={form.currency}
                        onValueChange={(v) => updateForm("currency", v)}
                      >
                        <SelectTrigger id="currency">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="usd">USD</SelectItem>
                          <SelectItem value="eur">EUR</SelectItem>
                          <SelectItem value="gbp">GBP</SelectItem>
                          <SelectItem value="cad">CAD</SelectItem>
                          <SelectItem value="aud">AUD</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </div>
                )}

                {/* Duration */}
                <div className="space-y-1.5">
                  <Label>Duration *</Label>
                  <Select
                    value={form.duration}
                    onValueChange={(v) =>
                      updateForm(
                        "duration",
                        v as "once" | "repeating" | "forever",
                      )
                    }
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="once">
                        Once — applies to the first payment only
                      </SelectItem>
                      <SelectItem value="repeating">
                        Repeating — applies over N months
                      </SelectItem>
                      <SelectItem value="forever">
                        Forever — applies to all payments
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                {form.duration === "repeating" && (
                  <div className="space-y-1.5">
                    <Label htmlFor="duration-months">Number of months *</Label>
                    <Input
                      id="duration-months"
                      type="number"
                      min={1}
                      step={1}
                      placeholder="3"
                      value={form.duration_in_months}
                      onChange={(e) =>
                        updateForm("duration_in_months", e.target.value)
                      }
                      className={
                        formErrors.duration_in_months ? "border-red-500" : ""
                      }
                    />
                    {formErrors.duration_in_months && (
                      <p className="text-xs text-red-500">
                        {formErrors.duration_in_months}
                      </p>
                    )}
                  </div>
                )}

                {/* Optional fields */}
                <div className="grid grid-cols-2 gap-3">
                  <div className="space-y-1.5">
                    <Label htmlFor="max-redemptions">Max redemptions</Label>
                    <Input
                      id="max-redemptions"
                      type="number"
                      min={1}
                      step={1}
                      placeholder="Unlimited"
                      value={form.max_redemptions}
                      onChange={(e) =>
                        updateForm("max_redemptions", e.target.value)
                      }
                      className={
                        formErrors.max_redemptions ? "border-red-500" : ""
                      }
                    />
                    {formErrors.max_redemptions && (
                      <p className="text-xs text-red-500">
                        {formErrors.max_redemptions}
                      </p>
                    )}
                  </div>

                  <div className="space-y-1.5">
                    <Label>Expiry date</Label>
                    <Popover open={calendarOpen} onOpenChange={setCalendarOpen}>
                      <PopoverTrigger asChild>
                        <Button
                          variant="outline"
                          className={cn(
                            "w-full justify-start text-left font-normal",
                            !form.expires_at && "text-muted-foreground",
                          )}
                        >
                          <CalendarIcon className="mr-2 h-4 w-4" />
                          {form.expires_at
                            ? dateTime(form.expires_at).format("DD MMM YYYY")
                            : "No expiry"}
                        </Button>
                      </PopoverTrigger>
                      <PopoverContent className="w-auto p-0" align="start">
                        <Calendar
                          mode="single"
                          selected={form.expires_at}
                          onSelect={(date) => {
                            updateForm("expires_at", date);
                            setCalendarOpen(false);
                          }}
                          disabled={(date) => date < new Date()}
                          initialFocus
                        />
                        {form.expires_at && (
                          <div className="p-2 border-t">
                            <Button
                              variant="ghost"
                              size="sm"
                              className="w-full"
                              onClick={() => {
                                updateForm("expires_at", undefined);
                                setCalendarOpen(false);
                              }}
                            >
                              Clear expiry
                            </Button>
                          </div>
                        )}
                      </PopoverContent>
                    </Popover>
                  </div>
                </div>
              </div>

              <DialogFooter>
                <Button
                  variant="outline"
                  onClick={handleCloseDialog}
                  disabled={createCoupon.isPending}
                >
                  Cancel
                </Button>
                <Button
                  onClick={handleSubmit}
                  disabled={createCoupon.isPending}
                >
                  Review details
                </Button>
              </DialogFooter>
            </>
          ) : (
            <>
              <DialogHeader>
                <DialogTitle className="flex items-center gap-2">
                  <Tag className="h-5 w-5" />
                  Confirm coupon
                </DialogTitle>
                <DialogDescription>
                  Review the details below before creating.
                </DialogDescription>
              </DialogHeader>

              {/* Immutability warning */}
              <div className="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3">
                <AlertCircle className="h-4 w-4 text-amber-600 mt-0.5 shrink-0" />
                <p className="text-sm text-amber-800">
                  <span className="font-semibold">
                    These details are permanent.
                  </span>{" "}
                  The promo code, discount, duration, redemption limit, and
                  expiry cannot be changed after creation. To apply different
                  terms, deactivate this coupon and create a new one. Only the
                  name and type label can be updated later.
                </p>
              </div>

              {/* Summary */}
              <div className="rounded-lg border bg-muted/20 divide-y text-sm">
                <div className="flex justify-between px-4 py-2.5">
                  <span className="text-muted-foreground">Name</span>
                  <span className="font-medium">{form.name}</span>
                </div>
                <div className="flex justify-between px-4 py-2.5">
                  <span className="text-muted-foreground">Code</span>
                  <span className="font-mono font-medium">
                    {form.code.trim().toUpperCase()}
                  </span>
                </div>
                <div className="flex justify-between px-4 py-2.5">
                  <span className="text-muted-foreground">Type</span>
                  <span className="font-medium capitalize">
                    {form.coupon_type}
                  </span>
                </div>
                <div className="flex justify-between px-4 py-2.5">
                  <span className="text-muted-foreground">Discount</span>
                  <span className="font-medium">
                    {form.discount_type === "percent"
                      ? `${form.percent_off}% off`
                      : `-${(form.currency ?? "usd").toUpperCase()} ${parseFloat(form.amount_off).toFixed(2)}`}
                  </span>
                </div>
                <div className="flex justify-between px-4 py-2.5">
                  <span className="text-muted-foreground">Duration</span>
                  <span className="font-medium">
                    {form.duration === "once" && "Once — first payment only"}
                    {form.duration === "forever" && "Forever — all payments"}
                    {form.duration === "repeating" &&
                      `Repeating — ${form.duration_in_months} month${parseInt(form.duration_in_months, 10) > 1 ? "s" : ""}`}
                  </span>
                </div>
                <div className="flex justify-between px-4 py-2.5">
                  <span className="text-muted-foreground">Max redemptions</span>
                  <span className="font-medium">
                    {form.max_redemptions || "Unlimited"}
                  </span>
                </div>
                <div className="flex justify-between px-4 py-2.5">
                  <span className="text-muted-foreground">Expiry</span>
                  <span className="font-medium">
                    {form.expires_at
                      ? dateTime(form.expires_at).format("DD MMM YYYY")
                      : "No expiry"}
                  </span>
                </div>
              </div>

              <DialogFooter>
                <Button
                  variant="outline"
                  onClick={() => setDialogStep("form")}
                  disabled={createCoupon.isPending}
                >
                  <ArrowLeft className="mr-2 h-4 w-4" />
                  Back
                </Button>
                <Button
                  onClick={handleConfirmCreate}
                  disabled={createCoupon.isPending}
                >
                  {createCoupon.isPending ? (
                    <>
                      <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                      Creating…
                    </>
                  ) : (
                    "Confirm & Create"
                  )}
                </Button>
              </DialogFooter>
            </>
          )}
        </DialogContent>
      </Dialog>
      {/* Edit Coupon Dialog */}
      <Dialog
        open={!!editingPromo}
        onOpenChange={(open) => !open && handleCloseEdit()}
      >
        <DialogContent className="max-w-sm">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Pencil className="h-5 w-5" />
              Edit coupon
            </DialogTitle>
            <DialogDescription>
              Update the name and type of this coupon.
            </DialogDescription>
          </DialogHeader>

          <div className="grid gap-4 py-2">
            <div className="space-y-1.5">
              <Label htmlFor="edit-name">Coupon name *</Label>
              <Input
                id="edit-name"
                placeholder="e.g. Internal Testing, Summer Campaign"
                value={editForm.name}
                onChange={(e) => {
                  setEditForm((p) => ({ ...p, name: e.target.value }));
                  setEditFormErrors((p) => ({ ...p, name: undefined }));
                }}
                className={editFormErrors.name ? "border-red-500" : ""}
              />
              {editFormErrors.name && (
                <p className="text-xs text-red-500">{editFormErrors.name}</p>
              )}
            </div>
            <div className="space-y-1.5">
              <Label>Coupon type</Label>
              <Select
                value={editForm.coupon_type}
                onValueChange={(v) =>
                  setEditForm((p) => ({
                    ...p,
                    coupon_type: v as "internal" | "marketing",
                  }))
                }
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="marketing">
                    Marketing — publicly shareable
                  </SelectItem>
                  <SelectItem value="internal">
                    Internal — operational use only
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <DialogFooter>
            <Button
              variant="outline"
              onClick={handleCloseEdit}
              disabled={updateCoupon.isPending}
            >
              Cancel
            </Button>
            <Button
              onClick={handleEditSubmit}
              disabled={updateCoupon.isPending}
            >
              {updateCoupon.isPending ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Saving…
                </>
              ) : (
                "Save changes"
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </Card>
  );
}
