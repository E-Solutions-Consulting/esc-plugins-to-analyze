import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { useAuth } from '@/stores/authStore';
import { useAuditLog } from '@/hooks/useAuditLog';
import { AdminLayout } from '@/components/layouts/AdminLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { DataTable, Column } from '@/components/common/DataTable';
import { StatusBadge } from '@/components/common/StatusBadge';
import { ImageUpload } from '@/components/common/ImageUpload';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { toast } from 'sonner';
import { Plus, Loader2, MoreHorizontal, Palette, Power, PowerOff, Building2, Pencil } from 'lucide-react';
import { dateTime } from '@/lib/dayjs';
import { normalizePhoneDigits } from '@/lib/phone';
import { validateTenant, validateTenantUpdate } from '@/lib/validations';

interface TenantWithBranding extends Tenant {
  tenant_branding?: TenantBranding | null;
}

export function TenantsContent() {
  const { isPlatformSuperadmin } = useAuth();
  const { logAction } = useAuditLog();
  const queryClient = useQueryClient();
  const [search, setSearch] = useState('');
  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [editingTenant, setEditingTenant] = useState<TenantWithBranding | null>(null);
  const [editingTenantDetails, setEditingTenantDetails] = useState<TenantWithBranding | null>(null);
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [newTenant, setNewTenant] = useState({
    name: '',
    slug: '',
    contact_email: '',
  });
  const [logoUrl, setLogoUrl] = useState<string | null>(null);
  const [brandingColors, setBrandingColors] = useState({
    primary_color: '#3B82F6',
    secondary_color: '#1E40AF',
    accent_color: '#10B981',
  });
  const [editFormData, setEditFormData] = useState<TenantUpdateFormData>({
    name: '',
    contact_email: '',
    contact_phone: '',
  });
  const [editFormErrors, setEditFormErrors] = useState<Record<string, string>>({});

  const { data: tenants = [], isLoading } = useQuery({
    queryKey: ['tenants', search],
    queryFn: async () => {
      let query = supabase
        .from('tenants')
        .select('*, tenant_branding(logo_url, primary_color, secondary_color, accent_color)')
        .order('created_at', { ascending: false });

      if (search) {
        query = query.or(`name.ilike.%${search}%,slug.ilike.%${search}%`);
      }

      const { data, error } = await query;
      if (error) throw error;
      return data as TenantWithBranding[];
    },
    enabled: isPlatformSuperadmin,
  });

  const createMutation = useMutation({
    mutationFn: async (data: TenantFormData) => {
      const { data: tenant, error } = await supabase
        .from('tenants')
        .insert([{ ...data, status: 'pending' as const }])
        .select()
        .single();

      if (error) throw error;
      return tenant;
    },
    onSuccess: (tenant) => {
      queryClient.invalidateQueries({ queryKey: ['tenants'] });
      logAction({
        action: 'create',
        entityType: 'tenant',
        entityId: tenant.id,
        afterData: tenant,
        tenantId: null,
      });
      toast.success('Tenant created successfully');
      setIsDialogOpen(false);
      setNewTenant({ name: '', slug: '', contact_email: '' });
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to create tenant');
    },
  });

  const updateStatusMutation = useMutation({
    mutationFn: async ({ id, status }: { id: string; status: 'active' | 'inactive' | 'pending' | 'suspended' }) => {
      const { error } = await supabase
        .from('tenants')
        .update({ status })
        .eq('id', id);

      if (error) throw error;
      return { id, status };
    },
    onSuccess: ({ id, status }) => {
      queryClient.invalidateQueries({ queryKey: ['tenants'] });
      logAction({
        action: 'update',
        entityType: 'tenant',
        entityId: id,
        afterData: { status },
        tenantId: null,
      });
      toast.success(`Tenant ${status === 'active' ? 'activated' : 'deactivated'}`);
    },
  });

  const updateBrandingMutation = useMutation({
    mutationFn: async ({ 
      tenantId, 
      logoUrl, 
      colors 
    }: { 
      tenantId: string; 
      logoUrl: string | null;
      colors: typeof brandingColors;
    }) => {
      // Check if branding record exists
      const { data: existing } = await supabase
        .from('tenant_branding')
        .select('id')
        .eq('tenant_id', tenantId)
        .single();

      const brandingData = {
        logo_url: logoUrl,
        primary_color: colors.primary_color,
        secondary_color: colors.secondary_color,
        accent_color: colors.accent_color,
      };

      if (existing) {
        const { error } = await supabase
          .from('tenant_branding')
          .update(brandingData)
          .eq('tenant_id', tenantId);
        if (error) throw error;
      } else {
        const { error } = await supabase
          .from('tenant_branding')
          .insert({ tenant_id: tenantId, ...brandingData });
        if (error) throw error;
      }
      return { tenantId, brandingData };
    },
    onSuccess: ({ tenantId, brandingData }) => {
      queryClient.invalidateQueries({ queryKey: ['tenants'] });
      logAction({
        action: 'update',
        entityType: 'tenant_branding',
        entityId: tenantId,
        afterData: brandingData,
        tenantId: null,
      });
      toast.success('Branding updated successfully');
      setEditingTenant(null);
      setLogoUrl(null);
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to update branding');
    },
  });

  const updateTenantDetailsMutation = useMutation({
    mutationFn: async ({ id, data }: { id: string; data: TenantUpdateFormData }) => {
      // Get before data for audit
      const { data: beforeData } = await supabase
        .from('tenants')
        .select('name, contact_email, contact_phone')
        .eq('id', id)
        .single();

      const { error } = await supabase
        .from('tenants')
        .update({
          name: data.name,
          contact_email: data.contact_email || null,
          contact_phone: normalizePhoneDigits(data.contact_phone || '') || null,
        })
        .eq('id', id);

      if (error) throw error;
      return { id, beforeData, afterData: data };
    },
    onSuccess: ({ id, beforeData, afterData }) => {
      queryClient.invalidateQueries({ queryKey: ['tenants'] });
      logAction({
        action: 'update',
        entityType: 'tenant',
        entityId: id,
        beforeData: beforeData as Record<string, unknown>,
        afterData: afterData as unknown as Record<string, unknown>,
        tenantId: null,
      });
      toast.success('Tenant details updated successfully');
      setEditingTenantDetails(null);
      setEditFormErrors({});
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to update tenant');
    },
  });

  const handleOpenEditDialog = (tenant: TenantWithBranding) => {
    setEditingTenant(tenant);
    setLogoUrl(tenant.tenant_branding?.logo_url || null);
    setBrandingColors({
      primary_color: tenant.tenant_branding?.primary_color || '#3B82F6',
      secondary_color: tenant.tenant_branding?.secondary_color || '#1E40AF',
      accent_color: tenant.tenant_branding?.accent_color || '#10B981',
    });
  };

  const handleSaveBranding = () => {
    if (editingTenant) {
      updateBrandingMutation.mutate({ 
        tenantId: editingTenant.id, 
        logoUrl,
        colors: brandingColors,
      });
    }
  };

  const handleOpenEditDetailsDialog = (tenant: TenantWithBranding) => {
    setEditingTenantDetails(tenant);
    setEditFormData({
      name: tenant.name,
      contact_email: tenant.contact_email || '',
      contact_phone: tenant.contact_phone || '',
    });
    setEditFormErrors({});
  };

  const handleSaveDetails = (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingTenantDetails) return;

    const validation = validateTenantUpdate(editFormData);
    if (validation.success === false) {
      setEditFormErrors(validation.errors);
      toast.error('Please fix the validation errors');
      return;
    }

    updateTenantDetailsMutation.mutate({
      id: editingTenantDetails.id,
      data: validation.data,
    });
  };

  const columns: Column<TenantWithBranding>[] = [
    {
      key: 'logo',
      header: '',
      cell: (tenant) => (
        <Avatar className="h-10 w-10">
          <AvatarImage src={tenant.tenant_branding?.logo_url || undefined} />
          <AvatarFallback>
            <Building2 className="h-5 w-5 text-muted-foreground" />
          </AvatarFallback>
        </Avatar>
      ),
      className: 'w-14',
    },
    {
      key: 'name',
      header: 'Tenant',
      cell: (tenant) => (
        <div>
          <p className="font-medium">{tenant.name}</p>
          <p className="text-sm text-muted-foreground font-mono">{tenant.slug}</p>
        </div>
      ),
    },
    {
      key: 'contact',
      header: 'Contact',
      cell: (tenant) => tenant.contact_email || '—',
    },
    {
      key: 'status',
      header: 'Status',
      cell: (tenant) => <StatusBadge status={tenant.status} />,
    },
    {
      key: 'created',
      header: 'Created',
      cell: (tenant) => dateTime(tenant.created_at).format('MMM D, YYYY'),
    },
    {
      key: 'actions',
      header: '',
      cell: (tenant) => (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon">
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem onClick={() => handleOpenEditDetailsDialog(tenant)}>
              <Pencil className="h-4 w-4 mr-2" />
              Edit Details
            </DropdownMenuItem>
            <DropdownMenuItem onClick={() => handleOpenEditDialog(tenant)}>
              <Palette className="h-4 w-4 mr-2" />
              Edit Branding
            </DropdownMenuItem>
            {tenant.status === 'active' ? (
              <DropdownMenuItem
                onClick={() => updateStatusMutation.mutate({ id: tenant.id, status: 'inactive' as const })}
              >
                <PowerOff className="h-4 w-4 mr-2" />
                Deactivate
              </DropdownMenuItem>
            ) : (
              <DropdownMenuItem
                onClick={() => updateStatusMutation.mutate({ id: tenant.id, status: 'active' as const })}
              >
                <Power className="h-4 w-4 mr-2" />
                Activate
              </DropdownMenuItem>
            )}
          </DropdownMenuContent>
        </DropdownMenu>
      ),
      className: 'w-12',
    },
  ];

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setFormErrors({});
    
    const validation = validateTenant(newTenant);
    if (validation.success === false) {
      setFormErrors(validation.errors);
      toast.error('Please fix the validation errors');
      return;
    }
    
    createMutation.mutate(validation.data);
  };

  if (!isPlatformSuperadmin) {
    return (
      <>
        <div className="flex items-center justify-center h-full">
          <p className="text-muted-foreground">Access denied. Platform Superadmin role required.</p>
        </div>
      </>
    );
  }

  return (
    <>
      <PageHeader
        title="Tenants"
        description="Manage platform tenants"
        actions={
          <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
            <DialogTrigger asChild>
              <Button>
                <Plus className="h-4 w-4 mr-2" />
                Add Tenant
              </Button>
            </DialogTrigger>
            <DialogContent>
              <form onSubmit={handleSubmit}>
                <DialogHeader>
                  <DialogTitle>Create New Tenant</DialogTitle>
                  <DialogDescription>
                    Add a new tenant to the platform.
                  </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4 py-4">
                  <div className="space-y-2">
                    <Label htmlFor="name">Tenant Name</Label>
                    <Input
                      id="name"
                      value={newTenant.name}
                      onChange={(e) => setNewTenant({ ...newTenant, name: e.target.value })}
                      maxLength={100}
                      className={formErrors.name ? 'border-destructive' : ''}
                    />
                    {formErrors.name && <p className="text-sm text-destructive">{formErrors.name}</p>}
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="slug">Slug</Label>
                    <Input
                      id="slug"
                      value={newTenant.slug}
                      onChange={(e) => setNewTenant({ ...newTenant, slug: e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '-') })}
                      maxLength={50}
                      className={formErrors.slug ? 'border-destructive' : ''}
                    />
                    {formErrors.slug && <p className="text-sm text-destructive">{formErrors.slug}</p>}
                    <p className="text-xs text-muted-foreground">URL-friendly identifier (lowercase, hyphens only)</p>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="contact_email">Contact Email (optional)</Label>
                    <Input
                      id="contact_email"
                      type="email"
                      value={newTenant.contact_email}
                      onChange={(e) => setNewTenant({ ...newTenant, contact_email: e.target.value })}
                      maxLength={255}
                      className={formErrors.contact_email ? 'border-destructive' : ''}
                    />
                    {formErrors.contact_email && <p className="text-sm text-destructive">{formErrors.contact_email}</p>}
                  </div>
                </div>
                <DialogFooter>
                  <Button type="button" variant="outline" onClick={() => setIsDialogOpen(false)}>
                    Cancel
                  </Button>
                  <Button type="submit" disabled={createMutation.isPending}>
                    {createMutation.isPending && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
                    Create Tenant
                  </Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>
        }
      />

      <DataTable
        columns={columns}
        data={tenants}
        isLoading={isLoading}
        searchPlaceholder="Search tenants..."
        searchValue={search}
        onSearchChange={setSearch}
        emptyMessage="No tenants found"
      />

      {/* Edit Branding Dialog */}
      <Dialog open={!!editingTenant} onOpenChange={(open) => !open && setEditingTenant(null)}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Edit Tenant Branding</DialogTitle>
            <DialogDescription>
              Configure logo and brand colors for {editingTenant?.name}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-6 py-4">
            {/* Logo Section */}
            <div className="space-y-2">
              <Label>Logo</Label>
              <ImageUpload
                bucket="tenant-logos"
                folder={editingTenant?.id || ''}
                value={logoUrl}
                onChange={setLogoUrl}
              />
              <p className="text-xs text-muted-foreground">
                Recommended: Square image, at least 200x200 pixels
              </p>
            </div>

            {/* Colors Section */}
            <div className="space-y-4">
              <Label className="text-base font-medium">Brand Colors</Label>
              
              <div className="grid gap-4">
                <div className="space-y-2">
                  <Label htmlFor="primary_color" className="text-sm">Primary Color</Label>
                  <div className="flex items-center gap-3">
                    <input
                      type="color"
                      id="primary_color"
                      data-testid="input-color-primary"
                      value={brandingColors.primary_color}
                      onChange={(e) => setBrandingColors({ ...brandingColors, primary_color: e.target.value })}
                      className="h-10 w-14 cursor-pointer rounded border border-input bg-transparent p-1"
                    />
                    <Input
                      value={brandingColors.primary_color}
                      onChange={(e) => setBrandingColors({ ...brandingColors, primary_color: e.target.value })}
                      placeholder="#3B82F6"
                      className="flex-1 font-mono text-sm"
                      maxLength={7}
                    />
                  </div>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="secondary_color" className="text-sm">Secondary Color</Label>
                  <div className="flex items-center gap-3">
                    <input
                      type="color"
                      id="secondary_color"
                      data-testid="input-color-secondary"
                      value={brandingColors.secondary_color}
                      onChange={(e) => setBrandingColors({ ...brandingColors, secondary_color: e.target.value })}
                      className="h-10 w-14 cursor-pointer rounded border border-input bg-transparent p-1"
                    />
                    <Input
                      value={brandingColors.secondary_color}
                      onChange={(e) => setBrandingColors({ ...brandingColors, secondary_color: e.target.value })}
                      placeholder="#1E40AF"
                      className="flex-1 font-mono text-sm"
                      maxLength={7}
                    />
                  </div>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="accent_color" className="text-sm">Accent Color</Label>
                  <div className="flex items-center gap-3">
                    <input
                      type="color"
                      id="accent_color"
                      data-testid="input-color-accent"
                      value={brandingColors.accent_color}
                      onChange={(e) => setBrandingColors({ ...brandingColors, accent_color: e.target.value })}
                      className="h-10 w-14 cursor-pointer rounded border border-input bg-transparent p-1"
                    />
                    <Input
                      value={brandingColors.accent_color}
                      onChange={(e) => setBrandingColors({ ...brandingColors, accent_color: e.target.value })}
                      placeholder="#10B981"
                      className="flex-1 font-mono text-sm"
                      maxLength={7}
                    />
                  </div>
                </div>
              </div>

              {/* Color Preview */}
              <div className="space-y-2">
                <Label className="text-sm text-muted-foreground">Preview</Label>
                <div className="flex gap-2">
                  <div 
                    className="h-8 flex-1 rounded"
                    style={{ backgroundColor: brandingColors.primary_color }}
                    title="Primary"
                  />
                  <div 
                    className="h-8 flex-1 rounded"
                    style={{ backgroundColor: brandingColors.secondary_color }}
                    title="Secondary"
                  />
                  <div 
                    className="h-8 flex-1 rounded"
                    style={{ backgroundColor: brandingColors.accent_color }}
                    title="Accent"
                  />
                </div>
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setEditingTenant(null)}>
              Cancel
            </Button>
            <Button onClick={handleSaveBranding} disabled={updateBrandingMutation.isPending}>
              {updateBrandingMutation.isPending && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
              Save Branding
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Edit Tenant Details Dialog */}
      <Dialog open={!!editingTenantDetails} onOpenChange={(open) => !open && setEditingTenantDetails(null)}>
        <DialogContent>
          <form onSubmit={handleSaveDetails}>
            <DialogHeader>
              <DialogTitle>Edit Tenant Details</DialogTitle>
              <DialogDescription>
                Update the name and contact information for {editingTenantDetails?.name}
              </DialogDescription>
            </DialogHeader>
            <div className="grid gap-4 py-4">
              <div className="space-y-2">
                <Label htmlFor="edit_name">Tenant Name</Label>
                <Input
                  id="edit_name"
                  value={editFormData.name}
                  onChange={(e) => setEditFormData({ ...editFormData, name: e.target.value })}
                  maxLength={100}
                  className={editFormErrors.name ? 'border-destructive' : ''}
                />
                {editFormErrors.name && <p className="text-sm text-destructive">{editFormErrors.name}</p>}
              </div>
              <div className="space-y-2">
                <Label htmlFor="edit_contact_email">Contact Email</Label>
                <Input
                  id="edit_contact_email"
                  type="email"
                  value={editFormData.contact_email}
                  onChange={(e) => setEditFormData({ ...editFormData, contact_email: e.target.value })}
                  maxLength={255}
                  className={editFormErrors.contact_email ? 'border-destructive' : ''}
                />
                {editFormErrors.contact_email && <p className="text-sm text-destructive">{editFormErrors.contact_email}</p>}
              </div>
              <div className="space-y-2">
                <Label htmlFor="edit_contact_phone">Contact Phone</Label>
                <Input
                  id="edit_contact_phone"
                  type="tel"
                  value={editFormData.contact_phone}
                  onChange={(e) => setEditFormData({ ...editFormData, contact_phone: e.target.value })}
                  maxLength={50}
                  className={editFormErrors.contact_phone ? 'border-destructive' : ''}
                />
                {editFormErrors.contact_phone && <p className="text-sm text-destructive">{editFormErrors.contact_phone}</p>}
              </div>
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setEditingTenantDetails(null)}>
                Cancel
              </Button>
              <Button type="submit" disabled={updateTenantDetailsMutation.isPending}>
                {updateTenantDetailsMutation.isPending && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
                Save Changes
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </>
  );
}

/** Original route entry point — keeps the standalone page working unchanged. */
export default function Tenants() {
  return (
    <AdminLayout variant="platform">
      <TenantsContent />
    </AdminLayout>
  );
}
