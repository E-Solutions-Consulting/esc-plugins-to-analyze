import { useState } from 'react';
import { usePaymentProviders } from '@/hooks/usePaymentProviders';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Skeleton } from '@/components/ui/skeleton';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Plus, Pencil, Trash2, CreditCard, Key, X } from 'lucide-react';

const defaultSetting: RequiredSetting = {
  key: '',
  label: '',
  type: 'text',
  required: true,
  placeholder: '',
};

export function PaymentProvidersManager() {
  const { providers, isLoading, createProvider, updateProvider, deleteProvider } = usePaymentProviders();
  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
  const [editingProvider, setEditingProvider] = useState<PaymentProvider | null>(null);
  const [deletingProvider, setDeletingProvider] = useState<PaymentProvider | null>(null);
  
  const [formData, setFormData] = useState<PaymentProviderFormData>({
    key: '',
    name: '',
    description: '',
    logo_url: '',
    is_active: true,
    required_settings: [],
  });

  const resetForm = () => {
    setFormData({
      key: '',
      name: '',
      description: '',
      logo_url: '',
      is_active: true,
      required_settings: [],
    });
    setEditingProvider(null);
  };

  const openCreateDialog = () => {
    resetForm();
    setIsDialogOpen(true);
  };

  const openEditDialog = (provider: PaymentProvider) => {
    setEditingProvider(provider);
    setFormData({
      key: provider.key,
      name: provider.name,
      description: provider.description || '',
      logo_url: provider.logo_url || '',
      is_active: provider.is_active,
      required_settings: provider.required_settings || [],
    });
    setIsDialogOpen(true);
  };

  const openDeleteDialog = (provider: PaymentProvider) => {
    setDeletingProvider(provider);
    setIsDeleteDialogOpen(true);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (editingProvider) {
      await updateProvider.mutateAsync({ id: editingProvider.id, ...formData });
    } else {
      await createProvider.mutateAsync(formData);
    }
    
    setIsDialogOpen(false);
    resetForm();
  };

  const handleDelete = async () => {
    if (deletingProvider) {
      await deleteProvider.mutateAsync(deletingProvider.id);
      setIsDeleteDialogOpen(false);
      setDeletingProvider(null);
    }
  };

  const addSetting = () => {
    setFormData({
      ...formData,
      required_settings: [...formData.required_settings, { ...defaultSetting }],
    });
  };

  const updateSetting = (index: number, field: keyof RequiredSetting, value: string | boolean) => {
    const newSettings = [...formData.required_settings];
    newSettings[index] = { ...newSettings[index], [field]: value };
    setFormData({ ...formData, required_settings: newSettings });
  };

  const removeSetting = (index: number) => {
    const newSettings = formData.required_settings.filter((_, i) => i !== index);
    setFormData({ ...formData, required_settings: newSettings });
  };

  if (isLoading) {
    return (
      <Card>
        <CardHeader>
          <Skeleton className="h-6 w-48" />
          <Skeleton className="h-4 w-72 mt-2" />
        </CardHeader>
        <CardContent>
          <div className="space-y-3">
            {[1, 2, 3].map((i) => (
              <Skeleton key={i} className="h-16 w-full" />
            ))}
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <>
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <div>
            <CardTitle className="flex items-center gap-2">
              <CreditCard className="h-5 w-5" />
              Payment Providers
            </CardTitle>
            <CardDescription>
              Manage payment providers available to tenants
            </CardDescription>
          </div>
          <Button onClick={openCreateDialog}>
            <Plus className="h-4 w-4 mr-2" />
            Add Provider
          </Button>
        </CardHeader>
        <CardContent>
          {providers && providers.length > 0 ? (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Provider</TableHead>
                  <TableHead>Key</TableHead>
                  <TableHead>Required Settings</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {providers.map((provider) => (
                  <TableRow key={provider.id}>
                    <TableCell>
                      <div>
                        <div className="font-medium">{provider.name}</div>
                        {provider.description && (
                          <div className="text-sm text-muted-foreground line-clamp-1">
                            {provider.description}
                          </div>
                        )}
                      </div>
                    </TableCell>
                    <TableCell>
                      <code className="text-sm bg-muted px-2 py-1 rounded">
                        {provider.key}
                      </code>
                    </TableCell>
                    <TableCell>
                      <div className="flex flex-wrap gap-1">
                        {provider.required_settings?.map((setting) => (
                          <Badge key={setting.key} variant="outline" className="text-xs">
                            <Key className="h-3 w-3 mr-1" />
                            {setting.label}
                          </Badge>
                        ))}
                        {(!provider.required_settings || provider.required_settings.length === 0) && (
                          <span className="text-sm text-muted-foreground">None</span>
                        )}
                      </div>
                    </TableCell>
                    <TableCell>
                      <Badge variant={provider.is_active ? 'default' : 'secondary'}>
                        {provider.is_active ? 'Active' : 'Inactive'}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <div className="flex justify-end gap-2">
                        <Button
                          variant="ghost"
                          size="icon"
                          onClick={() => openEditDialog(provider)}
                        >
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="icon"
                          onClick={() => openDeleteDialog(provider)}
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          ) : (
            <div className="text-center py-8 text-muted-foreground">
              <CreditCard className="h-12 w-12 mx-auto mb-4 opacity-50" />
              <p>No payment providers configured yet.</p>
              <p className="text-sm">Add your first payment provider to get started.</p>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Create/Edit Dialog */}
      <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen} modal={true}>
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>
              {editingProvider ? 'Edit Payment Provider' : 'Add Payment Provider'}
            </DialogTitle>
            <DialogDescription>
              {editingProvider
                ? 'Update the payment provider configuration'
                : 'Configure a new payment provider for tenants'}
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={handleSubmit}>
            <div className="space-y-4 py-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="name">Provider Name</Label>
                  <Input
                    id="name"
                    value={formData.name}
                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                    placeholder="e.g., Stripe"
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="key">Provider Key</Label>
                  <Input
                    id="key"
                    value={formData.key}
                    onChange={(e) => setFormData({ ...formData, key: e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '_') })}
                    placeholder="e.g., stripe"
                    required
                  />
                </div>
              </div>

              <div className="space-y-2">
                <Label htmlFor="description">Description</Label>
                <Textarea
                  id="description"
                  value={formData.description}
                  onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                  placeholder="Brief description of the payment provider..."
                  rows={2}
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="logo_url">Logo URL</Label>
                <Input
                  id="logo_url"
                  type="url"
                  value={formData.logo_url}
                  onChange={(e) => setFormData({ ...formData, logo_url: e.target.value })}
                  placeholder="https://example.com/logo.png"
                />
              </div>

              <div className="flex items-center justify-between">
                <Label htmlFor="is_active">Active</Label>
                <Switch
                  id="is_active"
                  checked={formData.is_active}
                  onCheckedChange={(checked) => setFormData({ ...formData, is_active: checked })}
                />
              </div>

              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <Label>Required Settings</Label>
                  <Button type="button" variant="outline" size="sm" onClick={addSetting}>
                    <Plus className="h-4 w-4 mr-1" />
                    Add Setting
                  </Button>
                </div>
                <p className="text-sm text-muted-foreground">
                  Define the settings tenants will need to configure when enabling this provider.
                </p>
                
                {formData.required_settings.length > 0 ? (
                  <div className="space-y-3">
                    {formData.required_settings.map((setting, index) => (
                      <div key={index} className="border rounded-lg p-4 space-y-3">
                        <div className="flex items-center justify-between">
                          <span className="text-sm font-medium">Setting {index + 1}</span>
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => removeSetting(index)}
                          >
                            <X className="h-4 w-4" />
                          </Button>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                          <div className="space-y-1">
                            <Label className="text-xs">Key</Label>
                            <Input
                              value={setting.key}
                              onChange={(e) => updateSetting(index, 'key', e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '_'))}
                              placeholder="e.g., secret_key"
                              required
                            />
                          </div>
                          <div className="space-y-1">
                            <Label className="text-xs">Label</Label>
                            <Input
                              value={setting.label}
                              onChange={(e) => updateSetting(index, 'label', e.target.value)}
                              placeholder="e.g., Secret Key"
                              required
                            />
                          </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                          <div className="space-y-1">
                            <Label className="text-xs">Type</Label>
                            <Select
                              value={setting.type}
                              onValueChange={(value) => updateSetting(index, 'type', value)}
                            >
                              <SelectTrigger>
                                <SelectValue />
                              </SelectTrigger>
                              <SelectContent>
                                <SelectItem value="text">Text</SelectItem>
                                <SelectItem value="secret">Secret (masked)</SelectItem>
                                <SelectItem value="select">Select</SelectItem>
                              </SelectContent>
                            </Select>
                          </div>
                          <div className="space-y-1">
                            <Label className="text-xs">Placeholder</Label>
                            <Input
                              value={setting.placeholder || ''}
                              onChange={(e) => updateSetting(index, 'placeholder', e.target.value)}
                              placeholder="e.g., sk_live_..."
                            />
                          </div>
                        </div>
                        <div className="flex items-center gap-2">
                          <Switch
                            checked={setting.required}
                            onCheckedChange={(checked) => updateSetting(index, 'required', checked)}
                          />
                          <Label className="text-xs">Required</Label>
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="border border-dashed rounded-lg p-4 text-center text-sm text-muted-foreground">
                    No settings defined. Add settings that tenants will need to configure.
                  </div>
                )}
              </div>
            </div>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setIsDialogOpen(false)}>
                Cancel
              </Button>
              <Button
                type="submit"
                disabled={createProvider.isPending || updateProvider.isPending}
              >
                {createProvider.isPending || updateProvider.isPending
                  ? 'Saving...'
                  : editingProvider
                  ? 'Update Provider'
                  : 'Create Provider'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <AlertDialog open={isDeleteDialogOpen} onOpenChange={setIsDeleteDialogOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Payment Provider</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete "{deletingProvider?.name}"? This action cannot be undone.
              Tenants using this provider will need to reconfigure their payment settings.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDelete}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
