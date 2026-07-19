import { useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
import { dateTime } from "@/lib/dayjs";
import { AdminLayout } from "@/components/layouts/AdminLayout";
import { PageHeader } from "@/components/common/PageHeader";
import { DataTable, type Column } from "@/components/common/DataTable";
import { MigrationBadge } from "@/components/common/MigrationStatus";
import { StatusBadge } from "@/components/common/StatusBadge";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/stores/authStore";
import { ROUTES } from "@/lib/constants";

export default function Subscriptions() {
  const { currentTenantId } = useAuth();
  const navigate = useNavigate();
  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState<"all" | SubscriptionStatus>(
    "all",
  );
  const [sortBy, setSortBy] = useState<SortOption>("created_desc");

  const PAGE_SIZE = 25;

  useEffect(() => {
    const timeoutId = window.setTimeout(() => {
      setSearch(searchInput.trim());
    }, 350);

    return () => window.clearTimeout(timeoutId);
  }, [searchInput]);

  const { data: subscriptionsResult, isLoading } = useQuery({
    queryKey: ["subscriptions", currentTenantId, statusFilter, sortBy, search, page],
    queryFn: async () => {
      if (!currentTenantId) return { data: [], hasNextPage: false };

      const from = (page - 1) * PAGE_SIZE;
      const { data, error } = await supabase.rpc(
        "list_tenant_subscriptions",
        {
          p_limit: PAGE_SIZE + 1,
          p_offset: from,
          p_search: search || null,
          p_sort: sortBy,
          p_status: statusFilter === "all" ? null : statusFilter,
          p_tenant_id: currentTenantId,
        },
      );

      if (error) throw error;

      const rows = data || [];
      return {
        data: rows.slice(0, PAGE_SIZE).map((subscription) => ({
          id: subscription.id,
          status: subscription.status,
          current_period_end_at: subscription.current_period_end_at,
          metadata: subscription.metadata,
          created_at: subscription.created_at,
          patients: {
            first_name: subscription.patient_first_name,
            last_name: subscription.patient_last_name,
          },
        })) as unknown as SubscriptionWithPatient[],
        hasNextPage: rows.length > PAGE_SIZE,
      };
    },
    enabled: !!currentTenantId,
  });

  const subscriptions = subscriptionsResult?.data || [];
  const hasNextPage = subscriptionsResult?.hasNextPage || false;

  const columns: Column<SubscriptionWithPatient>[] = [
    {
      key: "id",
      header: "Subscription",
      cell: (subscription) => (
        <span className="font-mono text-xs">
          {`SUB-${subscription.id.slice(0, 8).toUpperCase()}`}
        </span>
      ),
    },
    {
      key: "patient",
      header: "Patient",
      cell: (subscription) =>
        subscription.patients
          ? `${subscription.patients.first_name} ${subscription.patients.last_name}`
          : "—",
    },
    {
      key: "status",
      header: "State",
      cell: (subscription) => (
        <StatusBadge status={subscription.status} size="sm" />
      ),
    },
    {
      key: "migration",
      header: "Migration",
      cell: (subscription) => (
        <MigrationBadge
          metadata={subscription.metadata}
          entityType="subscription"
        />
      ),
    },
    {
      key: "renewal",
      header: "Renewal",
      cell: (subscription) =>
        subscription.current_period_end_at
          ? dateTime(subscription.current_period_end_at).format("MMM D, YYYY")
          : "—",
    },
    {
      key: "created",
      header: "Created",
      cell: (subscription) =>
        dateTime(subscription.created_at).format("MMM D, YYYY"),
    },
  ];

  return (
    <AdminLayout variant="tenant">
      <PageHeader
        title="Subscriptions"
        description="Track subscription lifecycle state and upcoming renewals"
        actions={
          <div className="flex items-center gap-2">
            <Select
              value={statusFilter}
              onValueChange={(value) => {
                setStatusFilter(value as "all" | SubscriptionStatus);
                setPage(1);
              }}
            >
              <SelectTrigger className="w-48">
                <SelectValue placeholder="Filter by status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All States</SelectItem>
                <SelectItem value="active">Active</SelectItem>
                <SelectItem value="pending_validation">
                  Pending Validation
                </SelectItem>
                <SelectItem value="pending_cancellation">
                  Pending Cancellation
                </SelectItem>
                <SelectItem value="paused">Paused</SelectItem>
                <SelectItem value="cancelled">Cancelled</SelectItem>
              </SelectContent>
            </Select>

            <Select
              value={sortBy}
              onValueChange={(value) => {
                setSortBy(value as SortOption);
                setPage(1);
              }}
            >
              <SelectTrigger className="w-56">
                <SelectValue placeholder="Order by" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="name_asc">Name (A-Z)</SelectItem>
                <SelectItem value="name_desc">Name (Z-A)</SelectItem>
                <SelectItem value="renewal_asc">Renewal (Soonest)</SelectItem>
                <SelectItem value="renewal_desc">Renewal (Latest)</SelectItem>
                <SelectItem value="created_desc">Created (Newest)</SelectItem>
                <SelectItem value="created_asc">Created (Oldest)</SelectItem>
              </SelectContent>
            </Select>
          </div>
        }
      />

      <DataTable
        columns={columns}
        data={subscriptions}
        isLoading={isLoading}
        searchPlaceholder="Search by subscription or patient..."
        searchValue={searchInput}
        onSearchChange={(value) => {
          setSearchInput(value);
          setPage(1);
        }}
        emptyMessage="No subscriptions found"
        page={page}
        pageSize={PAGE_SIZE}
        hasNextPage={hasNextPage}
        onPageChange={setPage}
        onRowClick={(subscription) =>
          navigate(
            ROUTES.TENANT_ADMIN.SUBSCRIPTION_DETAIL.replace(
              ":id",
              subscription.id,
            ),
          )
        }
      />
    </AdminLayout>
  );
}
