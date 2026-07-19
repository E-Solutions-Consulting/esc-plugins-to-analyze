import { useEffect, useState } from 'react';
import { useQueries } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { AdminLayout } from '@/components/layouts/AdminLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { DataTable, Column } from '@/components/common/DataTable';
import { CreateAdminDialog } from '@/components/features/CreateAdminDialog';
import { RoleAssignmentDialog } from '@/components/features/RoleAssignmentDialog';
import { SuperadminManageDialog } from '@/components/features/SuperadminManageDialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Plus, Shield, Crown, Building2, Headset, Settings2 } from 'lucide-react';
import { dateTime } from '@/lib/dayjs';

const ALL_USERS_PAGE_SIZE = 25;

export function AdminsContent() {
  const [activeTab, setActiveTab] = useState('superadmins');
  const [superadminSearch, setSuperadminSearch] = useState('');
  const [allUsersSearchInput, setAllUsersSearchInput] = useState('');
  const [allUsersSearch, setAllUsersSearch] = useState('');
  const [allUsersPage, setAllUsersPage] = useState(1);
  const [showCreateDialog, setShowCreateDialog] = useState(false);
  const [selectedUser, setSelectedUser] = useState<AdminUser | null>(null);
  const [showRoleDialog, setShowRoleDialog] = useState(false);
  const [selectedSuperadmin, setSelectedSuperadmin] = useState<Superadmin | null>(null);
  const [showSuperadminDialog, setShowSuperadminDialog] = useState(false);

  useEffect(() => {
    const timeoutId = window.setTimeout(() => {
      setAllUsersSearch(allUsersSearchInput.trim());
    }, 300);

    return () => window.clearTimeout(timeoutId);
  }, [allUsersSearchInput]);

  const [ 
    { data: superadmins = [], isLoading: loadingSuperadmins } , 
    { data: allUsersResult = { data: [], count: 0 }, isLoading: loadingAllUsers }
  ] = useQueries({
    queries: [
      {
        queryKey: ['platform-superadmins', superadminSearch],
        queryFn: async () => {
          const { data, error } = await supabase.rpc('get_platform_superadmins');

          if (error) throw error;

          const mapped = (data as Omit<Superadmin, 'id'>[])?.map((s) => ({
            ...s,
            id: s.admin_user_id,
          })) || [];

          if (superadminSearch) {
            return mapped.filter(
              (s) =>
                s.email.toLowerCase().includes(superadminSearch.toLowerCase()) ||
                s.full_name?.toLowerCase().includes(superadminSearch.toLowerCase())
            );
          }

          return mapped;
        },
      },
      {
        queryKey: ['all-admin-users', allUsersSearch, allUsersPage],
        queryFn: async () => {
          const from = (allUsersPage - 1) * ALL_USERS_PAGE_SIZE;
          const { data, error } = await supabase.rpc('get_all_admin_users', {
            _search: allUsersSearch || null,
            _offset: from,
            _limit: ALL_USERS_PAGE_SIZE,
          });

          if (error) throw error;

          const mapped = (data as unknown as Array<{
            admin_user_id: string;
            email: string;
            full_name: string | null;
            avatar_url: string | null;
            is_active: boolean;
            created_at: string;
            roles: string[] | null;
            tenants: TenantInfo[] | null;
            total_count: number;
          }>)?.map((u) => ({
            id: u.admin_user_id,
            admin_user_id: u.admin_user_id,
            email: u.email,
            full_name: u.full_name,
            avatar_url: u.avatar_url,
            is_active: u.is_active,
            created_at: u.created_at,
            roles: u.roles || [],
            tenants: u.tenants || [],
          })) || [];

          return { data: mapped, count: data?.[0]?.total_count ?? 0 };
        },
      },
    ],
  });

  const allUsers = allUsersResult.data;
  const allUsersCount = allUsersResult.count;

  const superadminColumns: Column<Superadmin>[] = [
    {
      key: 'user',
      header: 'User',
      cell: (admin) => {
        const initials = admin.full_name
          ?.split(' ')
          .map((n) => n[0])
          .join('')
          .toUpperCase() || admin.email[0].toUpperCase();

        return (
          <div className="flex items-center gap-3">
            <Avatar className="h-10 w-10">
              <AvatarImage src={admin.avatar_url || undefined} />
              <AvatarFallback>{initials}</AvatarFallback>
            </Avatar>
            <div>
              <div className="flex items-center gap-2">
                <p className="font-medium">
                  {admin.full_name || admin.email}
                </p>
                <Crown className="h-4 w-4 text-amber-500" aria-label="Platform Superadmin" />
              </div>
              <p className="text-sm text-muted-foreground">{admin.email}</p>
            </div>
          </div>
        );
      },
    },
    {
      key: 'tenants',
      header: 'Tenant Access',
      cell: (admin) => (
        <div className="flex items-center gap-2">
          <Building2 className="h-4 w-4 text-muted-foreground" />
          <span>{admin.tenant_count} tenant{admin.tenant_count !== 1 ? 's' : ''}</span>
        </div>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      cell: (admin) => (
        <Badge variant={admin.is_active ? 'default' : 'secondary'}>
          {admin.is_active ? 'Active' : 'Inactive'}
        </Badge>
      ),
    },
    {
      key: 'created',
      header: 'Created',
      cell: (admin) => dateTime(admin.created_at).format('MMM D, YYYY'),
    },
    {
      key: 'actions',
      header: '',
      className: 'w-[50px]',
      cell: (admin) => (
        <Button
          variant="ghost"
          size="icon"
          onClick={(e) => {
            e.stopPropagation();
            setSelectedSuperadmin(admin);
            setShowSuperadminDialog(true);
          }}
        >
          <Settings2 className="h-4 w-4" />
        </Button>
      ),
    },
  ];

  const allUsersColumns: Column<AdminUser>[] = [
    {
      key: 'user',
      header: 'User',
      cell: (user) => {
        const initials = user.full_name
          ?.split(' ')
          .map((n) => n[0])
          .join('')
          .toUpperCase() || user.email[0].toUpperCase();

        const isSuperadmin = user.roles?.includes('platform_superadmin');
        const isCustomerSupport = user.roles?.includes('customer_support');

        return (
          <div className="flex items-center gap-3">
            <Avatar className="h-10 w-10">
              <AvatarImage src={user.avatar_url || undefined} />
              <AvatarFallback>{initials}</AvatarFallback>
            </Avatar>
            <div>
              <div className="flex items-center gap-2">
                <p className="font-medium">
                  {user.full_name || user.email}
                </p>
                {isSuperadmin && (
                  <Crown className="h-4 w-4 text-amber-500" aria-label="Platform Superadmin" />
                )}
                {isCustomerSupport && (
                  <Headset className="h-4 w-4 text-muted-foreground" aria-label="Customer Support" />
                )}
              </div>
              <p className="text-sm text-muted-foreground">{user.email}</p>
            </div>
          </div>
        );
      },
    },
    {
      key: 'roles',
      header: 'Roles',
      cell: (user) => (
        <div className="flex flex-wrap gap-1">
          {user.roles?.map((role) => (
            <Badge key={role} variant="outline" className="text-xs">
              {role === 'platform_superadmin'
                ? 'Superadmin'
                : role === 'customer_support'
                ? 'Customer Support'
                : 'Tenant Admin'}
            </Badge>
          ))}
          {(!user.roles || user.roles.length === 0) && (
            <span className="text-muted-foreground text-sm">No roles</span>
          )}
        </div>
      ),
    },
    {
      key: 'tenants',
      header: 'Tenants',
      cell: (user) => (
        <div className="flex flex-wrap gap-1">
          {user.tenants.slice(0, 2).map((tenant) => (
            <Badge key={tenant.id} variant="secondary" className="text-xs">
              {tenant.name}
            </Badge>
          ))}
          {user.tenants.length > 2 && (
            <Badge variant="secondary" className="text-xs">
              +{user.tenants.length - 2} more
            </Badge>
          )}
          {user.tenants.length === 0 && (
            <span className="text-muted-foreground text-sm">No tenants</span>
          )}
        </div>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      cell: (user) => (
        <Badge variant={user.is_active ? 'default' : 'secondary'}>
          {user.is_active ? 'Active' : 'Inactive'}
        </Badge>
      ),
    },
    {
      key: 'created',
      header: 'Created',
      cell: (user) => dateTime(user.created_at).format('MMM D, YYYY'),
    },
    {
      key: 'actions',
      header: '',
      className: 'w-[50px]',
      cell: (user) => (
        <Button
          variant="ghost"
          size="icon"
          onClick={(e) => {
            e.stopPropagation();
            setSelectedUser(user);
            setShowRoleDialog(true);
          }}
        >
          <Settings2 className="h-4 w-4" />
        </Button>
      ),
    },
  ];

  return (
    <>
      <PageHeader
        title="Admins & Roles"
        description="Manage platform superadmins and all tenant users"
        actions={
          <Button onClick={() => setShowCreateDialog(true)}>
            <Plus className="h-4 w-4 mr-2" />
            Add Admin
          </Button>
        }
      />

      <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-4">
        <TabsList>
          <TabsTrigger value="superadmins" className="flex items-center gap-2">
            <Crown className="h-4 w-4" />
            Superadmins
          </TabsTrigger>
          <TabsTrigger value="all-users" className="flex items-center gap-2">
            <Shield className="h-4 w-4" />
            All Users
          </TabsTrigger>
        </TabsList>

        <TabsContent value="superadmins">
          <DataTable
            columns={superadminColumns}
            data={superadmins}
            isLoading={loadingSuperadmins}
            searchPlaceholder="Search superadmins..."
            searchValue={superadminSearch}
            onSearchChange={setSuperadminSearch}
            emptyMessage="No superadmins found"
          />
        </TabsContent>

        <TabsContent value="all-users">
          <DataTable
            columns={allUsersColumns}
            data={allUsers}
            isLoading={loadingAllUsers}
            searchPlaceholder="Search all users..."
            searchValue={allUsersSearchInput}
            onSearchChange={(value) => {
              setAllUsersSearchInput(value);
              setAllUsersPage(1);
            }}
            emptyMessage="No users found"
            page={allUsersPage}
            pageSize={ALL_USERS_PAGE_SIZE}
            total={allUsersCount}
            onPageChange={setAllUsersPage}
          />
        </TabsContent>
      </Tabs>

      <CreateAdminDialog
        open={showCreateDialog}
        onOpenChange={setShowCreateDialog}
        variant="platform"
      />

      <RoleAssignmentDialog
        open={showRoleDialog}
        onOpenChange={setShowRoleDialog}
        user={selectedUser}
      />

      <SuperadminManageDialog
        open={showSuperadminDialog}
        onOpenChange={setShowSuperadminDialog}
        superadmin={selectedSuperadmin}
      />
    </>
  );
}

/** Original route entry point — keeps the standalone page working unchanged. */
export default function Admins() {
  return (
    <AdminLayout variant="platform">
      <AdminsContent />
    </AdminLayout>
  );
}
