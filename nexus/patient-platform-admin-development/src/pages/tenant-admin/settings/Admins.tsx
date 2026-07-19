import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/stores/authStore";
import { AdminLayout } from "@/components/layouts/AdminLayout";
import { PageHeader } from "@/components/common/PageHeader";
import { DataTable, Column } from "@/components/common/DataTable";
import { CreateAdminDialog } from "@/components/features/CreateAdminDialog";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
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
import { Plus, Shield, Crown, UserMinus, Loader2 } from "lucide-react";
import { dateTime } from "@/lib/dayjs";
import { toast } from "sonner";

interface TenantMember {
  id: string; // Mapped from membership_id for DataTable compatibility
  membership_id: string;
  admin_user_id: string;
  tenant_id: string;
  is_primary: boolean;
  membership_created_at: string;
  email: string;
  full_name: string | null;
  avatar_url: string | null;
  is_active: boolean;
  roles: string[] | null;
}

/**
 * Page body without the AdminLayout wrapper, so it can be rendered both at the
 * original route (via the default export below) and inside the regrouped
 * Settings v2 IA. See docs/SettingsIARedesign.md, Part 3 (migration).
 */
export function AdminsContent() {
  const { currentTenantId } = useAuth();
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [showCreateDialog, setShowCreateDialog] = useState(false);
  const [memberToRemove, setMemberToRemove] = useState<TenantMember | null>(
    null,
  );

  const { data: members = [], isLoading } = useQuery({
    queryKey: ["tenant-members", currentTenantId, search],
    queryFn: async () => {
      if (!currentTenantId) return [];

      const { data, error } = await supabase.rpc("get_tenant_members", {
        p_tenant_id: currentTenantId,
      });

      if (error) throw error;

      // Map membership_id to id for DataTable and filter by search
      const mapped =
        (data as Omit<TenantMember, "id">[])?.map((m) => ({
          ...m,
          id: m.membership_id,
        })) || [];

      if (search) {
        return mapped.filter(
          (m) =>
            m.email.toLowerCase().includes(search.toLowerCase()) ||
            m.full_name?.toLowerCase().includes(search.toLowerCase()),
        );
      }

      return mapped;
    },
    enabled: !!currentTenantId,
  });

  const removeMemberMutation = useMutation({
    mutationFn: async (member: TenantMember) => {
      const {
        data: { session },
      } = await supabase.auth.getSession();
      const accessToken = session?.access_token;
      if (!accessToken) throw new Error("Missing auth session");
      const { data, error } = await supabase.functions.invoke("manage-roles", {
        headers: { Authorization: `Bearer ${accessToken}` },
        body: {
          adminUserId: member.admin_user_id,
          action: "remove_from_tenant",
          tenantId: currentTenantId,
        },
      });
      if (error) throw error;
      if (data?.error) throw new Error(data.error);
      return data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["tenant-members"] });
      toast.success("Admin removed from tenant");
      setMemberToRemove(null);
    },
    onError: (error: Error) => {
      toast.error(error.message || "Failed to remove admin");
    },
  });

  const columns: Column<TenantMember>[] = [
    {
      key: "user",
      header: "User",
      cell: (member) => {
        const initials =
          member.full_name
            ?.split(" ")
            .map((n) => n[0])
            .join("")
            .toUpperCase() || member.email[0].toUpperCase();

        return (
          <div className="flex items-center gap-3">
            <Avatar className="h-10 w-10">
              <AvatarImage src={member.avatar_url || undefined} />
              <AvatarFallback>{initials}</AvatarFallback>
            </Avatar>
            <div>
              <div className="flex items-center gap-2">
                <p className="font-medium">
                  {member.full_name || member.email}
                </p>
                {member.is_primary && (
                  <Crown
                    className="h-4 w-4 text-amber-500"
                    aria-label="Primary admin"
                  />
                )}
              </div>
              <p className="text-sm text-muted-foreground">{member.email}</p>
            </div>
          </div>
        );
      },
    },
    {
      key: "role",
      header: "Role",
      cell: (member) => (
        <div className="flex items-center gap-2">
          <Shield className="h-4 w-4 text-muted-foreground" />
          <span>{member.is_primary ? "Primary Admin" : "Admin"}</span>
        </div>
      ),
    },
    {
      key: "status",
      header: "Status",
      cell: (member) => (
        <Badge variant={member.is_active ? "default" : "secondary"}>
          {member.is_active ? "Active" : "Inactive"}
        </Badge>
      ),
    },
    {
      key: "joined",
      header: "Joined",
      cell: (member) =>
        dateTime(member.membership_created_at).format("MMM D, YYYY"),
    },
    {
      key: "actions",
      header: "",
      className: "w-[50px]",
      cell: (member) =>
        !member.is_primary && (
          <Button
            variant="ghost"
            size="icon"
            onClick={(e) => {
              e.stopPropagation();
              setMemberToRemove(member);
            }}
            title="Remove from tenant"
          >
            <UserMinus className="h-4 w-4 text-destructive" />
          </Button>
        ),
    },
  ];

  return (
    <>
      <PageHeader
        title="Admins & Roles"
        description="Manage tenant administrators and their permissions"
        actions={
          <Button onClick={() => setShowCreateDialog(true)}>
            <Plus className="h-4 w-4 mr-2" />
            Invite Admin
          </Button>
        }
      />

      <DataTable
        columns={columns}
        data={members}
        isLoading={isLoading}
        searchPlaceholder="Search admins..."
        searchValue={search}
        onSearchChange={setSearch}
        emptyMessage="No administrators found"
      />

      <CreateAdminDialog
        open={showCreateDialog}
        onOpenChange={setShowCreateDialog}
        variant="tenant"
        tenantId={currentTenantId || undefined}
      />

      <AlertDialog
        open={!!memberToRemove}
        onOpenChange={() => setMemberToRemove(null)}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Remove Admin from Tenant</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to remove{" "}
              <span className="font-medium">
                {memberToRemove?.full_name || memberToRemove?.email}
              </span>{" "}
              from this tenant? They will lose access to all tenant resources.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={removeMemberMutation.isPending}>
              Cancel
            </AlertDialogCancel>
            <AlertDialogAction
              onClick={() =>
                memberToRemove && removeMemberMutation.mutate(memberToRemove)
              }
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              disabled={removeMemberMutation.isPending}
            >
              {removeMemberMutation.isPending && (
                <Loader2 className="h-4 w-4 mr-2 animate-spin" />
              )}
              Remove
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

/** Original route entry point — keeps the standalone page working unchanged. */
export default function Admins() {
  return (
    <AdminLayout variant="tenant">
      <AdminsContent />
    </AdminLayout>
  );
}
