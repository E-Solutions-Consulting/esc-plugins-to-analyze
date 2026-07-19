import { useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/stores/authStore";
import { AdminLayout } from "@/components/layouts/AdminLayout";
import { PageHeader } from "@/components/common/PageHeader";
import { DataTable, Column } from "@/components/common/DataTable";
import { MigrationBadge } from "@/components/common/MigrationStatus";
import { StatusBadge } from "@/components/common/StatusBadge";
import { ROUTES } from "@/lib/constants";
import { dateTime } from "@/lib/dayjs";

interface PatientPageCursor {
  createdAt: string;
  id: string;
}

export default function Patients() {
  const { currentTenantId } = useAuth();
  const navigate = useNavigate();
  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [pageCursors, setPageCursors] = useState<
    Array<PatientPageCursor | null>
  >([null]);

  const PAGE_SIZE = 25;

  useEffect(() => {
    const timeoutId = window.setTimeout(() => {
      setSearch(searchInput.trim());
    }, 300);

    return () => window.clearTimeout(timeoutId);
  }, [searchInput]);

  useEffect(() => {
    setPage(1);
    setPageCursors([null]);
  }, [search]);

  const currentCursor = pageCursors[page - 1] || null;

  const { data: patientsResult, isLoading } = useQuery({
    queryKey: [
      "patients",
      currentTenantId,
      search,
      page,
      currentCursor?.createdAt ?? null,
      currentCursor?.id ?? null,
    ],
    queryFn: async ({ signal }) => {
      if (!currentTenantId) return { data: [], hasNextPage: false };

      const { data, error } = await supabase
        .rpc("list_tenant_patients", {
          p_cursor_created_at: currentCursor?.createdAt ?? null,
          p_cursor_id: currentCursor?.id ?? null,
          p_limit: PAGE_SIZE + 1,
          p_search: search || null,
          p_tenant_id: currentTenantId,
        })
        .abortSignal(signal);

      if (error) throw error;

      const rows = data || [];
      return {
        data: rows.slice(0, PAGE_SIZE) as Patient[],
        hasNextPage: rows.length > PAGE_SIZE,
      };
    },
    placeholderData: (previousData) => previousData,
    enabled: !!currentTenantId,
  });

  const patients = patientsResult?.data || [];
  const hasNextPage = patientsResult?.hasNextPage || false;

  const handlePageChange = (nextPage: number) => {
    if (nextPage === page + 1 && hasNextPage && patients.length > 0) {
      const lastPatient = patients[patients.length - 1];
      const nextCursor = {
        createdAt: lastPatient.created_at,
        id: lastPatient.id,
      };

      setPageCursors((current) => [
        ...current.slice(0, page),
        nextCursor,
      ]);
      setPage(nextPage);
      return;
    }

    if (nextPage >= 1 && nextPage < page) {
      setPage(nextPage);
    }
  };

  const columns: Column<Patient>[] = [
    {
      key: "name",
      header: "Name",
      cell: (patient) => (
        <div>
          <p className="font-medium">
            {patient.first_name} {patient.last_name}
          </p>
          <p className="text-sm text-muted-foreground">{patient.email}</p>
        </div>
      ),
    },
    {
      key: "phone",
      header: "Phone",
      cell: (patient) => patient.phone || "—",
    },
    {
      key: "status",
      header: "Status",
      cell: (patient) => <StatusBadge status={patient.access_status} />,
    },
    {
      key: "migration",
      header: "Migration",
      cell: (patient) => (
        <MigrationBadge metadata={patient.metadata} entityType="patient" />
      ),
    },
    {
      key: "created",
      header: "Created",
      cell: (patient) => dateTime(patient.created_at).format("MMM D, YYYY"),
    },
  ];

  return (
    <AdminLayout variant="tenant">
      <PageHeader
        title="Patients"
        description="Manage your patient records"
      />

      <DataTable
        columns={columns}
        data={patients}
        isLoading={isLoading}
        searchPlaceholder="Search patients..."
        searchValue={searchInput}
        onSearchChange={(value) => {
          setSearchInput(value);
        }}
        emptyMessage="No patients found"
        page={page}
        pageSize={PAGE_SIZE}
        hasNextPage={hasNextPage}
        onPageChange={handlePageChange}
        onRowClick={(patient) =>
          navigate(`${ROUTES.TENANT_ADMIN.PATIENTS}/${patient.id}`)
        }
      />
    </AdminLayout>
  );
}
