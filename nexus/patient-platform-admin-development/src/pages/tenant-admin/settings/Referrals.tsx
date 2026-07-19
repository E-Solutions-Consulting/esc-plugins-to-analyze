import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Eye, Gift, Loader2, RefreshCw, Save, Search } from "lucide-react";
import { toast } from "sonner";
import { PageHeader } from "@/components/common/PageHeader";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { TenantIntegrationSettings } from "@/components/features/TenantIntegrationSettings";
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
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { supabase } from "@/integrations/supabase/client";
import { useAuditLog } from "@/hooks/useAuditLog";
import { useAuth } from "@/stores/authStore";

type ReferralProgramConfig = {
  id: string;
  tenant_id: string;
  status: string;
  currency: string;
  reward_amount_cents: number;
  created_at: string;
  updated_at: string;
};

type ReferralRecord = {
  id: string;
  status: string;
  reward_status: string;
  coupon_status: string | null;
  validity_status: string | null;
  referrer_email: string | null;
  friend_email: string | null;
  referral_code: string | null;
  friend_coupon_code: string | null;
  referral_url: string | null;
  friendbuy_customer_id: string | null;
  friendbuy_purl_id: string | null;
  friendbuy_share_id: string | null;
  friendbuy_click_id: string | null;
  friendbuy_conversion_id: string | null;
  friendbuy_reward_id: string | null;
  friendbuy_campaign_id: string | null;
  stripe_coupon_id: string | null;
  stripe_promotion_code_id: string | null;
  redemption_count: number | null;
  max_redemptions: number | null;
  issued_at: string | null;
  delivered_at: string | null;
  clicked_at: string | null;
  purchased_at: string | null;
  redeemed_at: string | null;
  reward_pending_at: string | null;
  reward_credited_at: string | null;
  reward_rejected_at: string | null;
  expires_at: string | null;
  raw_friendbuy: Record<string, unknown>;
  raw_stripe: Record<string, unknown>;
  created_at: string;
};

type ReferralSyncEvent = {
  id: string;
  source: string;
  source_event_type: string;
  source_event_id: string;
  status: string;
  processed_at: string;
};

const DEFAULT_FORM = {
  status: "disabled",
  currency: "USD",
  rewardAmount: "25.00",
};

function centsToAmount(cents?: number | null) {
  return ((cents || 0) / 100).toFixed(2);
}

function amountToCents(value: string) {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? Math.round(parsed * 100) : 0;
}

function formatMoney(cents?: number | null, currency = "USD") {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency,
  }).format((cents || 0) / 100);
}

function formatDateTime(value?: string | null) {
  if (!value) return "—";
  const timestamp = Date.parse(value);
  if (!Number.isFinite(timestamp)) return value;
  return new Intl.DateTimeFormat("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
  }).format(new Date(timestamp));
}

function titleize(value?: string | null) {
  if (!value) return "Unknown";
  return value
    .split("_")
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");
}

function lifecycleLabel(record: ReferralRecord) {
  if (record.redeemed_at || record.purchased_at) return "Redeemed";
  if (record.clicked_at || record.status === "clicked") return "Clicked";
  if (record.delivered_at || record.status === "delivered") return "Delivered";
  if (record.issued_at || record.status === "issued") return "Issued";
  return titleize(record.status);
}

function redemptionLabel(record: ReferralRecord) {
  if (record.redeemed_at || record.purchased_at) {
    return formatDateTime(record.redeemed_at || record.purchased_at);
  }
  if (record.validity_status === "redeemed") return "Redeemed";
  return "Not redeemed";
}

function validityLabel(record: ReferralRecord) {
  const status = titleize(record.validity_status || record.coupon_status);
  if (record.expires_at) {
    return `${status} · expires ${formatDateTime(record.expires_at)}`;
  }
  return status;
}

function toForm(config?: ReferralProgramConfig | null) {
  if (!config) return DEFAULT_FORM;
  return {
    status: config.status,
    currency: config.currency || "USD",
    rewardAmount: centsToAmount(config.reward_amount_cents),
  };
}

export function ReferralsContent() {
  const { currentTenantId } = useAuth();
  const { logAction } = useAuditLog();
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [validityFilter, setValidityFilter] = useState("all");
  const [rewardFilter, setRewardFilter] = useState("all");
  const [selectedRecord, setSelectedRecord] = useState<ReferralRecord | null>(
    null,
  );
  const [form, setForm] = useState(DEFAULT_FORM);

  const configQuery = useQuery({
    queryKey: ["referral-program-config", currentTenantId],
    enabled: Boolean(currentTenantId),
    queryFn: async () => {
      const { data, error } = await (supabase as any)
        .from("referral_program_configs")
        .select("*")
        .eq("tenant_id", currentTenantId)
        .maybeSingle();
      if (error) throw error;
      const config = data as ReferralProgramConfig | null;
      setForm(toForm(config));
      return config;
    },
  });

  const recordsQuery = useQuery({
    queryKey: [
      "referral-records",
      currentTenantId,
      search,
      statusFilter,
      validityFilter,
      rewardFilter,
    ],
    enabled: Boolean(currentTenantId),
    queryFn: async () => {
      let query = (supabase as any)
        .from("referral_records")
        .select(
          [
            "id",
            "status",
            "reward_status",
            "coupon_status",
            "validity_status",
            "referrer_email",
            "friend_email",
            "referral_code",
            "friend_coupon_code",
            "referral_url",
            "friendbuy_customer_id",
            "friendbuy_purl_id",
            "friendbuy_share_id",
            "friendbuy_click_id",
            "friendbuy_conversion_id",
            "friendbuy_reward_id",
            "friendbuy_campaign_id",
            "stripe_coupon_id",
            "stripe_promotion_code_id",
            "redemption_count",
            "max_redemptions",
            "issued_at",
            "delivered_at",
            "clicked_at",
            "purchased_at",
            "redeemed_at",
            "reward_pending_at",
            "reward_credited_at",
            "reward_rejected_at",
            "expires_at",
            "raw_friendbuy",
            "raw_stripe",
            "created_at",
          ].join(","),
        )
        .eq("tenant_id", currentTenantId)
        .order("created_at", { ascending: false })
        .limit(50);

      const normalizedSearch = search.trim();
      if (normalizedSearch) {
        query = query.or(
          [
            `referral_code.ilike.%${normalizedSearch}%`,
            `friend_coupon_code.ilike.%${normalizedSearch}%`,
            `referrer_email.ilike.%${normalizedSearch}%`,
            `friend_email.ilike.%${normalizedSearch}%`,
          ].join(","),
        );
      }
      if (statusFilter !== "all") {
        query = query.eq("status", statusFilter);
      }
      if (validityFilter !== "all") {
        query = query.eq("validity_status", validityFilter);
      }
      if (rewardFilter !== "all") {
        query = query.eq("reward_status", rewardFilter);
      }

      const { data, error } = await query;
      if (error) throw error;
      return (data || []) as ReferralRecord[];
    },
  });

  const syncEventsQuery = useQuery({
    queryKey: ["referral-sync-events", currentTenantId, selectedRecord?.id],
    enabled: Boolean(currentTenantId && selectedRecord?.id),
    queryFn: async () => {
      const { data, error } = await (supabase as any)
        .from("referral_sync_events")
        .select(
          "id,source,source_event_type,source_event_id,status,processed_at",
        )
        .eq("tenant_id", currentTenantId)
        .eq("referral_record_id", selectedRecord!.id)
        .order("processed_at", { ascending: false })
        .limit(25);
      if (error) throw error;
      return (data || []) as ReferralSyncEvent[];
    },
  });

  const stats = useMemo(() => {
    const rows = recordsQuery.data || [];
    return {
      total: rows.length,
      rewarded: rows.filter((row) => row.reward_status === "credited").length,
      pending: rows.filter((row) => row.reward_status.includes("pending")).length,
    };
  }, [recordsQuery.data]);

  const saveMutation = useMutation({
    mutationFn: async () => {
      if (!currentTenantId) throw new Error("No tenant selected");

      const payload = {
        tenant_id: currentTenantId,
        status: form.status,
        currency: form.currency.trim().toUpperCase() || "USD",
        reward_amount_cents: amountToCents(form.rewardAmount),
      };

      const beforeData = configQuery.data || null;
      const { data, error } = await (supabase as any)
        .from("referral_program_configs")
        .upsert(payload, { onConflict: "tenant_id" })
        .select("*")
        .single();
      if (error) throw error;

      await logAction({
        action: beforeData ? "update" : "create",
        entityType: "referral_program_config",
        entityId: data.id,
        beforeData: beforeData as unknown as Record<string, unknown> | null,
        afterData: data as Record<string, unknown>,
      });

      return data as ReferralProgramConfig;
    },
    onSuccess: (config) => {
      queryClient.setQueryData(["referral-program-config", currentTenantId], config);
      toast.success("Referral program config saved");
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : "Failed to save config");
    },
  });

  // Scoped reconciliation only — reward outcome (credited/rejected) and
  // coupon distribution/redemption have no webhook equivalent, unlike every
  // other referral signal (share/click/conversion/email-capture/opt-out),
  // which already arrives in near-real-time via friendbuy-webhook.
  const reconcileMutation = useMutation({
    mutationFn: async () => {
      if (!currentTenantId) throw new Error("No tenant selected");
      const { data, error } = await supabase.functions.invoke("friendbuy-reconcile", {
        body: { tenant_id: currentTenantId },
      });
      if (error) throw error;
      return data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["referral-records"] });
      toast.success("Reward and coupon status reconciled");
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : "Reconciliation failed");
    },
  });

  if (!currentTenantId) {
    return (
      <PageHeader
        title="Referrals"
        description="Select a tenant before configuring referrals."
      />
    );
  }

  return (
    <div className="max-w-6xl space-y-6">
      <PageHeader
        title="Referrals"
        description="Configure Friendbuy connection settings and monitor Friendbuy-synced referral activity."
      />

      <TenantIntegrationSettings only={["referrals"]} />

      <div className="grid gap-4 md:grid-cols-3">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Program status</CardTitle>
          </CardHeader>
          <CardContent>
            <Badge variant={form.status === "active" ? "default" : "secondary"}>
              {form.status}
            </Badge>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Reward amount</CardTitle>
          </CardHeader>
          <CardContent className="text-2xl font-semibold">
            {formatMoney(amountToCents(form.rewardAmount), form.currency)}
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Synced records</CardTitle>
          </CardHeader>
          <CardContent className="text-sm">
            {stats.total} total · {stats.pending} pending · {stats.rewarded} credited
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Gift className="h-5 w-5" />
            Referral Program Config
          </CardTitle>
          <CardDescription>
            These PP-owned values control local referral display and synced
            balance fallback. Coupon rules, email copy, and Stripe coupon
            economics are configured in Friendbuy/Stripe.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          {configQuery.isLoading ? (
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
              <Loader2 className="h-4 w-4 animate-spin" />
              Loading referral config
            </div>
          ) : (
            <>
              <div className="grid gap-4 md:grid-cols-3">
                <div className="space-y-2">
                  <Label>Status</Label>
                  <Select
                    value={form.status}
                    onValueChange={(status) => setForm((prev) => ({ ...prev, status }))}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="disabled">Disabled</SelectItem>
                      <SelectItem value="draft">Draft</SelectItem>
                      <SelectItem value="active">Active</SelectItem>
                      <SelectItem value="paused">Paused</SelectItem>
                      <SelectItem value="archived">Archived</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label>Currency</Label>
                  <Input
                    value={form.currency}
                    onChange={(event) =>
                      setForm((prev) => ({ ...prev, currency: event.target.value }))}
                  />
                </div>
                <div className="space-y-2">
                  <Label>Reward amount</Label>
                  <Input
                    type="number"
                    min="0"
                    step="0.01"
                    value={form.rewardAmount}
                    onChange={(event) =>
                      setForm((prev) => ({ ...prev, rewardAmount: event.target.value }))}
                  />
                </div>
              </div>

              <div className="flex flex-wrap gap-2">
                <Button
                  onClick={() => saveMutation.mutate()}
                  disabled={saveMutation.isPending}
                >
                  {saveMutation.isPending ? (
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  ) : (
                    <Save className="mr-2 h-4 w-4" />
                  )}
                  Save config
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => reconcileMutation.mutate()}
                  disabled={reconcileMutation.isPending}
                >
                  {reconcileMutation.isPending ? (
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  ) : (
                    <RefreshCw className="mr-2 h-4 w-4" />
                  )}
                  Reconcile rewards & coupons
                </Button>
              </div>
            </>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Referral Tracking</CardTitle>
          <CardDescription>
            Per-code lifecycle visibility from Friendbuy webhook/API data and
            Nexus purchase reconciliation.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid gap-3 lg:grid-cols-[minmax(220px,1fr)_180px_180px_180px]">
            <div className="relative">
              <Search className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input
                className="pl-9"
                placeholder="Search referrer, friend, or code"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
              />
            </div>
            <Select value={statusFilter} onValueChange={setStatusFilter}>
              <SelectTrigger>
                <SelectValue placeholder="Lifecycle" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All lifecycle</SelectItem>
                <SelectItem value="issued">Issued</SelectItem>
                <SelectItem value="delivered">Delivered</SelectItem>
                <SelectItem value="clicked">Clicked</SelectItem>
                <SelectItem value="purchased">Purchased</SelectItem>
                <SelectItem value="conversion_recorded">Conversion recorded</SelectItem>
                <SelectItem value="reward_pending">Reward pending</SelectItem>
                <SelectItem value="rewarded">Rewarded</SelectItem>
                <SelectItem value="invalid">Invalid</SelectItem>
                <SelectItem value="expired">Expired</SelectItem>
                <SelectItem value="exception">Exception</SelectItem>
              </SelectContent>
            </Select>
            <Select value={validityFilter} onValueChange={setValidityFilter}>
              <SelectTrigger>
                <SelectValue placeholder="Validity" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All validity</SelectItem>
                <SelectItem value="active">Active</SelectItem>
                <SelectItem value="redeemed">Redeemed</SelectItem>
                <SelectItem value="expired">Expired</SelectItem>
                <SelectItem value="invalid">Invalid</SelectItem>
                <SelectItem value="unknown">Unknown</SelectItem>
              </SelectContent>
            </Select>
            <Select value={rewardFilter} onValueChange={setRewardFilter}>
              <SelectTrigger>
                <SelectValue placeholder="Reward" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All rewards</SelectItem>
                <SelectItem value="not_earned">Not earned</SelectItem>
                <SelectItem value="pending_eligibility">Pending eligibility</SelectItem>
                <SelectItem value="pending_approval">Pending approval</SelectItem>
                <SelectItem value="credited">Credited</SelectItem>
                <SelectItem value="rejected">Rejected</SelectItem>
                <SelectItem value="manual_exception">Manual exception</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <Separator />
          <div className="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Code</TableHead>
                  <TableHead>Friend coupon</TableHead>
                  <TableHead>Referrer</TableHead>
                  <TableHead>Friend</TableHead>
                  <TableHead>Issued</TableHead>
                  <TableHead>Delivered</TableHead>
                  <TableHead>Redeemed</TableHead>
                  <TableHead>Validity</TableHead>
                  <TableHead>Reward</TableHead>
                  <TableHead className="text-right">Details</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {recordsQuery.isLoading ? (
                  <TableRow>
                    <TableCell colSpan={10} className="text-muted-foreground">
                      Loading referrals
                    </TableCell>
                  </TableRow>
                ) : (recordsQuery.data || []).length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={10} className="text-muted-foreground">
                      No referral records yet
                    </TableCell>
                  </TableRow>
                ) : (
                  recordsQuery.data?.map((record) => (
                    <TableRow key={record.id}>
                      <TableCell className="font-medium">
                        {record.referral_code || "—"}
                      </TableCell>
                      <TableCell>{record.friend_coupon_code || "—"}</TableCell>
                      <TableCell>{record.referrer_email || "—"}</TableCell>
                      <TableCell>{record.friend_email || "—"}</TableCell>
                      <TableCell>
                        <div className="space-y-1">
                          <Badge variant="secondary">{lifecycleLabel(record)}</Badge>
                          <div className="text-xs text-muted-foreground">
                            {formatDateTime(record.issued_at || record.created_at)}
                          </div>
                        </div>
                      </TableCell>
                      <TableCell>
                        {record.delivered_at ? (
                          <div className="text-sm">{formatDateTime(record.delivered_at)}</div>
                        ) : (
                          <span className="text-muted-foreground">Not synced</span>
                        )}
                      </TableCell>
                      <TableCell>
                        <div className="space-y-1">
                          <Badge
                            variant={
                              record.redeemed_at || record.purchased_at
                                ? "default"
                                : "outline"
                            }
                          >
                            {redemptionLabel(record)}
                          </Badge>
                          {record.redemption_count !== null ||
                          record.max_redemptions !== null ? (
                            <div className="text-xs text-muted-foreground">
                              {record.redemption_count ?? 0} /{" "}
                              {record.max_redemptions ?? "∞"} redemptions
                            </div>
                          ) : null}
                        </div>
                      </TableCell>
                      <TableCell className="min-w-44">
                        <Badge variant="outline">{validityLabel(record)}</Badge>
                      </TableCell>
                      <TableCell>
                        <Badge
                          variant={
                            record.reward_status === "credited"
                              ? "default"
                              : "outline"
                          }
                        >
                          {titleize(record.reward_status)}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-right">
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          onClick={() => setSelectedRecord(record)}
                        >
                          <Eye className="mr-2 h-4 w-4" />
                          View
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>

      <Dialog
        open={Boolean(selectedRecord)}
        onOpenChange={(open) => {
          if (!open) setSelectedRecord(null);
        }}
      >
        <DialogContent className="max-w-4xl">
          <DialogHeader>
            <DialogTitle>
              Referral Code {selectedRecord?.referral_code || "Unknown"}
            </DialogTitle>
            <DialogDescription>
              Lifecycle, provider identifiers, sync events, and raw Friendbuy
              payload for this referral code.
            </DialogDescription>
          </DialogHeader>

          {selectedRecord ? (
            <div className="space-y-6">
              <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                {[
                  ["Issued", selectedRecord.issued_at || selectedRecord.created_at],
                  ["Delivered", selectedRecord.delivered_at],
                  ["Clicked", selectedRecord.clicked_at],
                  [
                    "Redeemed",
                    selectedRecord.redeemed_at || selectedRecord.purchased_at,
                  ],
                  ["Reward pending", selectedRecord.reward_pending_at],
                  ["Reward credited", selectedRecord.reward_credited_at],
                  ["Reward rejected", selectedRecord.reward_rejected_at],
                  ["Expires", selectedRecord.expires_at],
                ].map(([label, value]) => (
                  <div key={label} className="rounded-md border p-3">
                    <div className="text-xs font-medium uppercase text-muted-foreground">
                      {label}
                    </div>
                    <div className="mt-1 text-sm">{formatDateTime(value)}</div>
                  </div>
                ))}
              </div>

              <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                  <CardHeader className="pb-3">
                    <CardTitle className="text-base">Provider IDs</CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-2 text-sm">
                    {[
                      ["Referral code (advocate)", selectedRecord.referral_code],
                      ["Coupon code (friend)", selectedRecord.friend_coupon_code],
                      ["Friendbuy campaign", selectedRecord.friendbuy_campaign_id],
                      ["Friendbuy customer", selectedRecord.friendbuy_customer_id],
                      ["Friendbuy PURL", selectedRecord.friendbuy_purl_id],
                      ["Friendbuy share", selectedRecord.friendbuy_share_id],
                      ["Friendbuy click", selectedRecord.friendbuy_click_id],
                      ["Friendbuy conversion", selectedRecord.friendbuy_conversion_id],
                      ["Friendbuy reward", selectedRecord.friendbuy_reward_id],
                      ["Stripe coupon", selectedRecord.stripe_coupon_id],
                      ["Stripe promo", selectedRecord.stripe_promotion_code_id],
                    ].map(([label, value]) => (
                      <div key={label} className="flex justify-between gap-3">
                        <span className="text-muted-foreground">{label}</span>
                        <span className="max-w-[240px] truncate font-mono text-xs">
                          {value || "—"}
                        </span>
                      </div>
                    ))}
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader className="pb-3">
                    <CardTitle className="text-base">Sync Events</CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-2 text-sm">
                    {syncEventsQuery.isLoading ? (
                      <div className="text-muted-foreground">Loading events</div>
                    ) : (syncEventsQuery.data || []).length === 0 ? (
                      <div className="text-muted-foreground">No sync events</div>
                    ) : (
                      syncEventsQuery.data?.map((event) => (
                        <div key={event.id} className="rounded-md border p-2">
                          <div className="flex items-center justify-between gap-2">
                            <Badge variant="outline">{event.source}</Badge>
                            <span className="text-xs text-muted-foreground">
                              {formatDateTime(event.processed_at)}
                            </span>
                          </div>
                          <div className="mt-1 font-medium">
                            {event.source_event_type}
                          </div>
                          <div className="font-mono text-xs text-muted-foreground">
                            {event.source_event_id}
                          </div>
                        </div>
                      ))
                    )}
                  </CardContent>
                </Card>
              </div>

              <Card>
                <CardHeader className="pb-3">
                  <CardTitle className="text-base">Raw Friendbuy Snapshot</CardTitle>
                </CardHeader>
                <CardContent>
                  <pre className="max-h-72 overflow-auto rounded-md bg-muted p-3 text-xs">
                    {JSON.stringify(selectedRecord.raw_friendbuy || {}, null, 2)}
                  </pre>
                </CardContent>
              </Card>
            </div>
          ) : null}
        </DialogContent>
      </Dialog>
    </div>
  );
}

export default function Referrals() {
  return <ReferralsContent />;
}
