import { useState } from 'react';
import { useQueries, useMutation, useQueryClient } from '@tanstack/react-query';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Crown, Loader2, UserX, UserCheck, Key, User, ShieldX, Building2, Plus, X } from 'lucide-react';

interface SuperadminManageDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  superadmin: Superadmin | null;
}

export function SuperadminManageDialog({
  open,
  onOpenChange,
  superadmin,
}: SuperadminManageDialogProps) {
  const queryClient = useQueryClient();
  const [fullName, setFullName] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [showRevokeConfirm, setShowRevokeConfirm] = useState(false);
  const [showDeactivateConfirm, setShowDeactivateConfirm] = useState(false);
  const [selectedTenantToAdd, setSelectedTenantToAdd] = useState('');
  const [isPrimaryTenant, setIsPrimaryTenant] = useState(false);

  // Fetch all tenants and existing memberships in parallel
  const [
    { data: allTenants = [] },
    { data: tenantMemberships = [], refetch: refetchMemberships },
  ] = useQueries({
    queries: [
      {
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
      },
      {
        queryKey: ['superadmin-tenants', superadmin?.admin_user_id],
        queryFn: async () => {
          if (!superadmin?.admin_user_id) return [];
          const { data, error } = await supabase
            .from('tenant_memberships')
            .select('id, tenant_id, is_primary, tenant:tenants(id, name, slug)')
            .eq('admin_user_id', superadmin.admin_user_id);
          if (error) throw error;
          return data.map((m: any) => ({
            id: m.tenant_id,
            name: m.tenant?.name,
            slug: m.tenant?.slug,
            is_primary: m.is_primary,
          }));
        },
        enabled: open && !!superadmin?.admin_user_id,
      },
    ],
  });

  // Reset form when dialog opens with new superadmin
  const handleOpenChange = (newOpen: boolean) => {
    if (newOpen && superadmin) {
      setFullName(superadmin.full_name || '');
      setNewPassword('');
      setConfirmPassword('');
    }
    onOpenChange(newOpen);
  };

  const manageRoleMutation = useMutation({
    mutationFn: async (params: {
      action: 'update_password' | 'update_profile' | 'deactivate_user' | 'activate_user' | 'remove_superadmin' | 'add_tenant' | 'remove_tenant';
      newPassword?: string;
      fullName?: string;
      tenantId?: string;
      isPrimary?: boolean;
    }) => {
      const { data: { session } } = await supabase.auth.getSession();
      const accessToken = session?.access_token;
      if (!accessToken) throw new Error('Missing auth session');
      const { data, error } = await supabase.functions.invoke('manage-roles', {
        headers: { Authorization: `Bearer ${accessToken}` },
        body: {
          adminUserId: superadmin?.admin_user_id,
          action: params.action,
          newPassword: params.newPassword,
          fullName: params.fullName,
          tenantId: params.tenantId,
          isPrimary: params.isPrimary,
        },
      });
      if (error) throw error;
      if (data?.error) throw new Error(data.error);
      return data;
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['platform-superadmins'] });
      queryClient.invalidateQueries({ queryKey: ['all-admin-users'] });
      queryClient.invalidateQueries({ queryKey: ['superadmin-tenants'] });
      queryClient.invalidateQueries({ queryKey: ['tenant-members'] });
      
      const messages: Record<string, string> = {
        update_password: 'Password updated successfully',
        update_profile: 'Profile updated successfully',
        deactivate_user: 'User deactivated successfully',
        activate_user: 'User activated successfully',
        remove_superadmin: 'Superadmin privileges revoked',
        add_tenant: 'Tenant added successfully',
        remove_tenant: 'Tenant removed successfully',
      };
      toast.success(messages[variables.action] || 'Action completed');
      
      if (variables.action === 'update_password') {
        setNewPassword('');
        setConfirmPassword('');
      }
      if (variables.action === 'remove_superadmin') {
        onOpenChange(false);
      }
      if (variables.action === 'add_tenant') {
        setSelectedTenantToAdd('');
        setIsPrimaryTenant(false);
      }
    },
    onError: (error: Error) => {
      toast.error(error.message || 'Failed to perform action');
    },
  });

  if (!superadmin) return null;

  const initials = superadmin.full_name
    ?.split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase() || superadmin.email[0].toUpperCase();

  const isActive = superadmin.is_active;
  
  const availableTenants = allTenants.filter(
    (t) => !tenantMemberships.some((tm: any) => tm.id === t.id)
  );

  const handleAddTenant = () => {
    if (!selectedTenantToAdd) return;
    manageRoleMutation.mutate({
      action: 'add_tenant',
      tenantId: selectedTenantToAdd,
      isPrimary: isPrimaryTenant,
    });
  };

  const handleUpdateProfile = () => {
    if (!fullName.trim()) {
      toast.error('Name cannot be empty');
      return;
    }
    manageRoleMutation.mutate({ action: 'update_profile', fullName: fullName.trim() });
  };

  const handleResetPassword = () => {
    if (newPassword.length < 8) {
      toast.error('Password must be at least 8 characters');
      return;
    }
    if (newPassword !== confirmPassword) {
      toast.error('Passwords do not match');
      return;
    }
    manageRoleMutation.mutate({ action: 'update_password', newPassword });
  };

  const handleToggleStatus = () => {
    if (isActive) {
      setShowDeactivateConfirm(true);
    } else {
      manageRoleMutation.mutate({ action: 'activate_user' });
    }
  };

  const handleRevokeSuperadmin = () => {
    setShowRevokeConfirm(true);
  };

  return (
    <>
      <Dialog open={open} onOpenChange={handleOpenChange}>
        <DialogContent className="sm:max-w-[500px] max-h-[85vh] flex flex-col min-h-0 overflow-hidden">
          <DialogHeader>
            <DialogTitle>Manage Superadmin</DialogTitle>
            <DialogDescription>
              Update profile, reset password, or manage account status.
            </DialogDescription>
          </DialogHeader>

          <div className="flex-1 min-h-0 overflow-y-auto overscroll-contain -mx-6 px-6">
            <div className="space-y-6 pb-6">
              {/* User Info Header */}
              <div className="flex items-center gap-3 p-4 bg-muted rounded-lg">
                <Avatar className="h-14 w-14">
                  <AvatarImage src={superadmin.avatar_url || undefined} />
                  <AvatarFallback className="text-lg">{initials}</AvatarFallback>
                </Avatar>
                <div className="flex-1">
                  <div className="flex items-center gap-2">
                    <p className="font-semibold">{superadmin.full_name || superadmin.email}</p>
                    <Crown className="h-4 w-4 text-amber-500" />
                  </div>
                  <p className="text-sm text-muted-foreground">{superadmin.email}</p>
                  <Badge variant={isActive ? 'default' : 'secondary'} className="mt-1">
                    {isActive ? 'Active' : 'Inactive'}
                  </Badge>
                </div>
              </div>

              {/* Profile Section */}
              <div className="space-y-3">
                <div className="flex items-center gap-2">
                  <User className="h-4 w-4 text-muted-foreground" />
                  <Label className="text-sm font-medium">Profile</Label>
                </div>
                <div className="space-y-3 pl-6">
                  <div className="space-y-2">
                    <Label htmlFor="fullName" className="text-sm">Full Name</Label>
                    <Input
                      id="fullName"
                      value={fullName}
                      onChange={(e) => setFullName(e.target.value)}
                      placeholder="Enter full name"
                    />
                  </div>
                  <Button
                    onClick={handleUpdateProfile}
                    disabled={manageRoleMutation.isPending || fullName === (superadmin.full_name || '')}
                    size="sm"
                  >
                    {manageRoleMutation.isPending && (
                      <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                    )}
                    Save Profile
                  </Button>
                </div>
              </div>

              <Separator />

              {/* Password Reset Section */}
              <div className="space-y-3">
                <div className="flex items-center gap-2">
                  <Key className="h-4 w-4 text-muted-foreground" />
                  <Label className="text-sm font-medium">Reset Password</Label>
                </div>
                <div className="space-y-3 pl-6">
                  <div className="space-y-2">
                    <Label htmlFor="newPassword" className="text-sm">New Password</Label>
                    <Input
                      id="newPassword"
                      type="password"
                      value={newPassword}
                      onChange={(e) => setNewPassword(e.target.value)}
                      placeholder="Minimum 8 characters"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="confirmPassword" className="text-sm">Confirm Password</Label>
                    <Input
                      id="confirmPassword"
                      type="password"
                      value={confirmPassword}
                      onChange={(e) => setConfirmPassword(e.target.value)}
                      placeholder="Confirm new password"
                    />
                  </div>
                  <Button
                    onClick={handleResetPassword}
                    disabled={manageRoleMutation.isPending || !newPassword || !confirmPassword}
                    size="sm"
                  >
                    {manageRoleMutation.isPending && (
                      <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                    )}
                    Reset Password
                  </Button>
                </div>
              </div>

              <Separator />

              {/* Account Status Section */}
              <div className="space-y-3">
                <div className="flex items-center gap-2">
                  {isActive ? (
                    <UserCheck className="h-4 w-4 text-green-500" />
                  ) : (
                    <UserX className="h-4 w-4 text-destructive" />
                  )}
                  <Label className="text-sm font-medium">Account Status</Label>
                </div>
                <div className="flex items-center justify-between pl-6">
                  <p className="text-sm text-muted-foreground">
                    {isActive
                      ? 'This account is currently active and can access the platform.'
                      : 'This account is deactivated and cannot log in.'}
                  </p>
                  <Button
                    variant={isActive ? 'destructive' : 'default'}
                    size="sm"
                    onClick={handleToggleStatus}
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

              {/* Tenant Memberships Section */}
              <div className="space-y-3">
                <div className="flex items-center gap-2">
                  <Building2 className="h-4 w-4 text-muted-foreground" />
                  <Label className="text-sm font-medium">Tenant Memberships</Label>
                </div>
                <div className="space-y-3 pl-6">
                  {tenantMemberships.length > 0 ? (
                    <div className="space-y-2">
                      {tenantMemberships.map((tenant: any) => (
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
                            id="isPrimaryTenant"
                            checked={isPrimaryTenant}
                            onCheckedChange={(checked) =>
                              setIsPrimaryTenant(checked as boolean)
                            }
                          />
                          <label
                            htmlFor="isPrimaryTenant"
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

              <Separator />

              {/* Revoke Superadmin Section */}
              <div className="space-y-3">
                <div className="flex items-center gap-2">
                  <ShieldX className="h-4 w-4 text-destructive" />
                  <Label className="text-sm font-medium">Revoke Superadmin</Label>
                </div>
                <div className="flex items-center justify-between pl-6">
                  <p className="text-sm text-muted-foreground">
                    Remove superadmin privileges. User will retain tenant memberships.
                  </p>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={handleRevokeSuperadmin}
                    disabled={manageRoleMutation.isPending}
                    className="text-destructive hover:text-destructive"
                  >
                    Revoke Access
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </DialogContent>
      </Dialog>

      {/* Deactivate Confirmation */}
      <AlertDialog open={showDeactivateConfirm} onOpenChange={setShowDeactivateConfirm}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Deactivate Superadmin?</AlertDialogTitle>
            <AlertDialogDescription>
              This will prevent {superadmin.full_name || superadmin.email} from logging in to the platform.
              You can reactivate the account later.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => {
                manageRoleMutation.mutate({ action: 'deactivate_user' });
                setShowDeactivateConfirm(false);
              }}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              Deactivate
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Revoke Confirmation */}
      <AlertDialog open={showRevokeConfirm} onOpenChange={setShowRevokeConfirm}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Revoke Superadmin Privileges?</AlertDialogTitle>
            <AlertDialogDescription>
              This will remove platform superadmin access from {superadmin.full_name || superadmin.email}.
              They will still have access to any assigned tenants.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => {
                manageRoleMutation.mutate({ action: 'remove_superadmin' });
                setShowRevokeConfirm(false);
              }}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              Revoke Access
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
