import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { toast } from 'sonner';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Crown, Building2, Headset, Loader2, Plus, X, UserX, UserCheck } from 'lucide-react';

interface RoleAssignmentDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  user: AdminUser | null;
}

export function RoleAssignmentDialog({
  open,
  onOpenChange,
  user,
}: RoleAssignmentDialogProps) {
  const queryClient = useQueryClient();
  const [selectedTenantToAdd, setSelectedTenantToAdd] = useState('');
  const [isPrimaryTenant, setIsPrimaryTenant] = useState(false);

  const { data: allTenants = [] } = useQuery({
    queryKey: ['tenants-for-role-assignment'],
    queryFn: async () => {
      const { data, error } = await supabase
        .from('tenants')
        .select('id, name, slug')
        .eq('status', 'active')
        .order('name');
      if (error) throw error;
      return data;
    },
    enabled: open,
  });

  const manageRoleMutation = useMutation({
    mutationFn: async (params: {
      action:
        | 'add_superadmin'
        | 'remove_superadmin'
        | 'add_customer_support'
        | 'remove_customer_support'
        | 'add_tenant'
        | 'add_tenant_membership'
        | 'remove_tenant'
        | 'deactivate_user'
        | 'activate_user';
      tenantId?: string;
      isPrimary?: boolean;
    }) => {
      const { data: { session } } = await supabase.auth.getSession();
      const accessToken = session?.access_token;
      if (!accessToken) throw new Error('Missing auth session');
      const { data, error } = await supabase.functions.invoke('manage-roles', {
        headers: { Authorization: `Bearer ${accessToken}` },
        body: {
          adminUserId: user?.admin_user_id,
          action: params.action,
          tenantId: params.tenantId,
          isPrimary: params.isPrimary,
        },
      });
      if (error) throw error;
      if (data?.error) throw new Error(data.error);
      return data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platform-superadmins'] });
      queryClient.invalidateQueries({ queryKey: ['all-admin-users'] });
      queryClient.invalidateQueries({ queryKey: ['tenant-members'] });
      toast.success('Roles updated successfully');
    },
    onError: (error: Error) => {
      toast.error(error.message || 'Failed to update roles');
    },
  });

  if (!user) return null;

  const isSuperadmin = user.roles?.includes('platform_superadmin');
  const isCustomerSupport = user.roles?.includes('customer_support');
  const isActive = user.is_active !== false;
  const availableTenants = allTenants.filter(
    (t) => !user.tenants.some((ut) => ut.id === t.id)
  );

  const initials = user.full_name
    ?.split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase() || user.email[0].toUpperCase();

  const handleAddTenant = () => {
    if (!selectedTenantToAdd) return;
    manageRoleMutation.mutate({
      action: isCustomerSupport ? 'add_tenant_membership' : 'add_tenant',
      tenantId: selectedTenantToAdd,
      isPrimary: isPrimaryTenant,
    });
    setSelectedTenantToAdd('');
    setIsPrimaryTenant(false);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="flex max-h-[85dvh] min-h-0 flex-col overflow-hidden sm:max-w-[500px]">
        <DialogHeader className="shrink-0">
          <DialogTitle>Manage Roles & Access</DialogTitle>
          <DialogDescription>
            Update roles and tenant memberships for this user.
          </DialogDescription>
        </DialogHeader>

        <div className="-mx-6 min-h-0 flex-1 overflow-y-auto px-6">
          <div className="space-y-6 pb-4">
            {/* User Info */}
            <div className="flex items-center gap-3 p-3 bg-muted rounded-lg">
              <Avatar className="h-12 w-12">
                <AvatarImage src={user.avatar_url || undefined} />
                <AvatarFallback>{initials}</AvatarFallback>
              </Avatar>
              <div>
                <p className="font-medium">{user.full_name || user.email}</p>
                <p className="text-sm text-muted-foreground">{user.email}</p>
              </div>
            </div>

            {/* Platform Role */}
            <div className="space-y-3">
              <Label className="text-sm font-medium">Platform Roles</Label>
              <div className="flex items-center justify-between p-3 border rounded-lg">
                <div className="flex items-center gap-2">
                  <Crown className="h-4 w-4 text-amber-500" />
                  <span>Platform Superadmin</span>
                </div>
                <Button
                  size="sm"
                  variant={isSuperadmin ? 'destructive' : 'default'}
                  onClick={() =>
                    manageRoleMutation.mutate({
                      action: isSuperadmin ? 'remove_superadmin' : 'add_superadmin',
                    })
                  }
                  disabled={manageRoleMutation.isPending}
                >
                  {manageRoleMutation.isPending && (
                    <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                  )}
                  {isSuperadmin ? 'Remove' : 'Grant'}
                </Button>
              </div>
              <div className="flex items-center justify-between p-3 border rounded-lg">
                <div className="flex items-center gap-2">
                  <Headset className="h-4 w-4 text-muted-foreground" />
                  <span>Customer Support</span>
                </div>
                <Button
                  size="sm"
                  variant={isCustomerSupport ? 'destructive' : 'default'}
                  onClick={() =>
                    manageRoleMutation.mutate({
                      action: isCustomerSupport
                        ? 'remove_customer_support'
                        : 'add_customer_support',
                    })
                  }
                  disabled={manageRoleMutation.isPending}
                >
                  {manageRoleMutation.isPending && (
                    <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                  )}
                  {isCustomerSupport ? 'Remove' : 'Grant'}
                </Button>
              </div>
            </div>

            {/* Account Status */}
            <div className="space-y-3">
              <Label className="text-sm font-medium">Account Status</Label>
              <div className="flex items-center justify-between p-3 border rounded-lg">
                <div className="flex items-center gap-2">
                  {isActive ? (
                    <UserCheck className="h-4 w-4 text-green-500" />
                  ) : (
                    <UserX className="h-4 w-4 text-destructive" />
                  )}
                  <span>{isActive ? 'Active' : 'Deactivated'}</span>
                </div>
                <Button
                  size="sm"
                  variant={isActive ? 'destructive' : 'default'}
                  onClick={() =>
                    manageRoleMutation.mutate({
                      action: isActive ? 'deactivate_user' : 'activate_user',
                    })
                  }
                  disabled={manageRoleMutation.isPending}
                >
                  {manageRoleMutation.isPending && (
                    <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                  )}
                  {isActive ? 'Deactivate' : 'Activate'}
                </Button>
              </div>
            </div>

            <Separator />

            {/* Tenant Memberships */}
            <div className="space-y-3">
              <Label className="text-sm font-medium">Tenant Memberships</Label>

              {user.tenants.length > 0 ? (
                <div className="space-y-2">
                  {user.tenants.map((tenant) => (
                    <div
                      key={tenant.id}
                      className="flex items-center justify-between p-3 border rounded-lg"
                    >
                      <div className="flex items-center gap-2">
                        <Building2 className="h-4 w-4 text-muted-foreground" />
                        <span>{tenant.name}</span>
                        {tenant.is_primary && (
                          <Badge variant="secondary" className="text-xs">
                            Primary
                          </Badge>
                        )}
                      </div>
                      <Button
                        size="sm"
                        variant="ghost"
                        onClick={() =>
                          manageRoleMutation.mutate({
                            action: 'remove_tenant',
                            tenantId: tenant.id,
                          })
                        }
                        disabled={manageRoleMutation.isPending}
                      >
                        <X className="h-4 w-4" />
                      </Button>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-muted-foreground py-2">
                  No tenant memberships
                </p>
              )}

              {/* Add Tenant */}
              {availableTenants.length > 0 && (
                <div className="flex items-end gap-2 pt-2">
                  <div className="flex-1 space-y-2">
                    <Select
                      value={selectedTenantToAdd}
                      onValueChange={setSelectedTenantToAdd}
                    >
                      <SelectTrigger>
                        <SelectValue placeholder="Select tenant to add" />
                      </SelectTrigger>
                      <SelectContent>
                        {availableTenants.map((tenant) => (
                          <SelectItem key={tenant.id} value={tenant.id}>
                            {tenant.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <div className="flex items-center space-x-2">
                      <Checkbox
                        id="isPrimary"
                        checked={isPrimaryTenant}
                        onCheckedChange={(checked) =>
                          setIsPrimaryTenant(checked as boolean)
                        }
                      />
                      <label
                        htmlFor="isPrimary"
                        className="text-sm text-muted-foreground"
                      >
                        Set as primary tenant
                      </label>
                    </div>
                  </div>
                  <Button
                    onClick={handleAddTenant}
                    disabled={!selectedTenantToAdd || manageRoleMutation.isPending}
                  >
                    {manageRoleMutation.isPending ? (
                      <Loader2 className="h-4 w-4 animate-spin" />
                    ) : (
                      <Plus className="h-4 w-4" />
                    )}
                  </Button>
                </div>
              )}
            </div>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
