import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ChevronDown, Loader2, MapPin, Plus, X } from 'lucide-react';
import { toast } from 'sonner';
import { ImageUpload } from '@/components/common/ImageUpload';
import { useAuth } from "@/stores/authStore";
import { useAuditLog } from '@/hooks/useAuditLog';
import { supabase } from '@/integrations/supabase/client';
import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const INJECTION_SITE_IMAGES_BUCKET = 'injection-site-images';
const MAX_LABEL_LENGTH = 60;

async function removeStoredImage(url: string) {
  try {
    const parsedUrl = new URL(url);
    const pathParts = parsedUrl.pathname.split('/');
    const bucketIndex = pathParts.findIndex((part) => part === INJECTION_SITE_IMAGES_BUCKET);

    if (bucketIndex === -1) {
      return;
    }

    const filePath = pathParts.slice(bucketIndex + 1).join('/');
    if (!filePath) {
      return;
    }

    const { error } = await supabase.storage.from(INJECTION_SITE_IMAGES_BUCKET).remove([filePath]);
    if (error) {
      console.error('Failed to remove injection site image:', error);
    }
  } catch (error) {
    console.error('Failed to parse injection site image URL:', error);
  }
}

export function InjectionSitesSettings() {
  const { currentTenantId } = useAuth();
  const { logAction } = useAuditLog();
  const queryClient = useQueryClient();
  const [newSiteName, setNewSiteName] = useState('');
  const [newSiteImageUrl, setNewSiteImageUrl] = useState<string | null>(null);
  const [areSitesExpanded, setAreSitesExpanded] = useState(false);
  const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);

  const { data: injectionSites = [], isLoading } = useQuery({
    queryKey: ['tenant-injection-site-definitions', currentTenantId],
    queryFn: async () => {
      if (!currentTenantId) return [];

      const { data, error } = await supabase
        .from('tenant_injection_site_definitions' as 'medication_capabilities')
        .select('*')
        .eq('tenant_id', currentTenantId)
        .eq('is_active' as 'name', true)
        .order('display_order', { ascending: true })
        .order('label', { ascending: true });

      if (error) throw error;
      return (data ?? []) as unknown as InjectionSiteDefinition[];
    },
    enabled: !!currentTenantId,
  });

  const addMutation = useMutation({
    mutationFn: async ({ label, imageUrl }: { label: string; imageUrl: string }) => {
      if (!currentTenantId) throw new Error('No tenant selected');

      const { data, error } = await supabase
        .from('tenant_injection_site_definitions' as 'medication_capabilities')
        .insert({
          tenant_id: currentTenantId,
          label,
          image_url: imageUrl,
        } as unknown as Record<string, never>)
        .select()
        .single();

      if (error) throw error;
      return data as unknown as InjectionSiteDefinition;
    },
    onSuccess: (site) => {
      queryClient.invalidateQueries({ queryKey: ['tenant-injection-site-definitions', currentTenantId] });
      logAction({
        action: 'create',
        entityType: 'tenant_injection_site_definition',
        entityId: site.id,
        afterData: { label: site.label, image_url: site.image_url },
      });
      setNewSiteName('');
      setNewSiteImageUrl(null);
      setIsCreateDialogOpen(false);
      toast.success('Injection site added');
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to add injection site');
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async (site: InjectionSiteDefinition) => {
      if (!currentTenantId) throw new Error('No tenant selected');

      const { count, error: countError } = await supabase
        .from('medication_shot_intakes')
        .select('id', { count: 'exact', head: true })
        .eq('tenant_id', currentTenantId)
        .eq('injection_site_id', site.id);

      if (countError) throw countError;

      if ((count ?? 0) > 0) {
        const { error } = await supabase
          .from('tenant_injection_site_definitions' as 'medication_capabilities')
          .update({ is_active: false } as unknown as Record<string, never>)
          .eq('id', site.id)
          .eq('tenant_id' as 'id', currentTenantId);

        if (error) throw error;

        return { site, action: 'deactivate' as const };
      }

      const { error } = await supabase
        .from('tenant_injection_site_definitions' as 'medication_capabilities')
        .delete()
        .eq('id', site.id)
        .eq('tenant_id' as 'id', currentTenantId);

      if (error) throw error;

      if (site.image_url) {
        await removeStoredImage(site.image_url);
      }

      return { site, action: 'delete' as const };
    },
    onSuccess: ({ site, action }) => {
      queryClient.invalidateQueries({ queryKey: ['tenant-injection-site-definitions', currentTenantId] });
      logAction({
        action: action === 'delete' ? 'delete' : 'update',
        entityType: 'tenant_injection_site_definition',
        entityId: site.id,
        beforeData: { label: site.label, image_url: site.image_url },
        afterData: action === 'deactivate'
          ? { label: site.label, image_url: site.image_url, is_active: false }
          : undefined,
      });
      toast.success('Injection site removed');
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to remove injection site');
    },
  });

  const normalizedSiteName = newSiteName.trim();
  const siteExists = useMemo(() => {
    if (!normalizedSiteName) return false;
    return injectionSites.some(
      (site) => site.label.toLowerCase() === normalizedSiteName.toLowerCase()
    );
  }, [injectionSites, normalizedSiteName]);

  const handleAddSite = () => {
    if (!normalizedSiteName) return;

    if (normalizedSiteName.length > MAX_LABEL_LENGTH) {
      toast.error(`Injection site must be ${MAX_LABEL_LENGTH} characters or less`);
      return;
    }

    if (!newSiteImageUrl) {
      toast.error('An image is required');
      return;
    }

    if (siteExists) {
      toast.error('This injection site already exists');
      return;
    }

    addMutation.mutate({ label: normalizedSiteName, imageUrl: newSiteImageUrl });
  };

  const imageFolder = currentTenantId ? `${currentTenantId}/injection-sites` : 'injection-sites';

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center gap-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
            <MapPin className="h-5 w-5 text-primary" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <CardTitle>Injection Sites</CardTitle>
              <Badge variant="secondary">{injectionSites.length} total</Badge>
            </div>
            <CardDescription>
              Manage the named injection sites and reference images shown to patients in the shot tracker.
            </CardDescription>
          </div>
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        {isLoading ? (
          <div className="flex items-center justify-center py-6">
            <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
          </div>
        ) : (
          <>
            <div className="flex justify-end">
              <Button
                type="button"
                onClick={() => setIsCreateDialogOpen(true)}
              >
                <Plus className="h-4 w-4" />
                Add injection site
              </Button>
            </div>

            <Collapsible
              open={areSitesExpanded}
              onOpenChange={setAreSitesExpanded}
              className="rounded-lg border"
            >
              <CollapsibleTrigger asChild>
                <Button
                  type="button"
                  variant="ghost"
                  className="h-auto w-full justify-between rounded-lg px-3 py-2 hover:bg-muted/50"
                >
                  <span className="flex items-center gap-2">
                    <span className="text-sm text-muted-foreground">Configured injection sites</span>
                    <Badge variant="secondary">{injectionSites.length} total</Badge>
                  </span>
                  <ChevronDown
                    className={cn(
                      'h-4 w-4 text-muted-foreground transition-transform',
                      areSitesExpanded ? 'rotate-180' : ''
                    )}
                  />
                </Button>
              </CollapsibleTrigger>

              <CollapsibleContent className="px-3 pb-3">
                {injectionSites.length === 0 ? (
                  <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                    Add injection sites to make them available in patient shot tracking forms.
                  </div>
                ) : (
                  <div className="grid gap-3 md:grid-cols-2">
                    {injectionSites.map((site) => (
                      <div
                        key={site.id}
                        className="flex items-center justify-between gap-3 rounded-md border p-3"
                      >
                        <div className="flex items-center gap-3">
                          <img
                            src={site.image_url}
                            alt={site.label}
                            className="h-14 w-14 rounded-lg border object-cover"
                          />
                          <span className="text-sm font-medium">{site.label}</span>
                        </div>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          onClick={() => deleteMutation.mutate(site)}
                          disabled={deleteMutation.isPending}
                        >
                          <X className="h-4 w-4" />
                        </Button>
                      </div>
                    ))}
                  </div>
                )}
              </CollapsibleContent>
            </Collapsible>

            <Dialog open={isCreateDialogOpen} onOpenChange={setIsCreateDialogOpen}>
              <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                  <DialogTitle>Add injection site</DialogTitle>
                  <DialogDescription>
                    Create a patient-facing injection site with a name and image for the shot tracker.
                  </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                  <div className="space-y-2">
                    <Label htmlFor="new-injection-site">Site name</Label>
                    <Input
                      id="new-injection-site"
                      placeholder="e.g. Left abdomen"
                      value={newSiteName}
                      onChange={(event) => setNewSiteName(event.target.value)}
                      onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                          event.preventDefault();
                          handleAddSite();
                        }
                      }}
                    />
                    <p className="text-xs text-muted-foreground">
                      Use a patient-friendly name. The uploaded image is shown in the shot tracker picker.
                    </p>
                    {siteExists ? (
                      <p className="text-xs text-muted-foreground">
                        This injection site is already in your list.
                      </p>
                    ) : null}
                  </div>

                  <div className="space-y-2">
                    <Label>Site image</Label>
                    <ImageUpload
                      bucket={INJECTION_SITE_IMAGES_BUCKET}
                      folder={imageFolder}
                      value={newSiteImageUrl}
                      onChange={setNewSiteImageUrl}
                      disabled={addMutation.isPending}
                    />
                  </div>
                </div>

                <DialogFooter>
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => setIsCreateDialogOpen(false)}
                    disabled={addMutation.isPending}
                  >
                    Cancel
                  </Button>
                  <Button
                    type="button"
                    onClick={handleAddSite}
                    disabled={
                      !normalizedSiteName ||
                      siteExists ||
                      !newSiteImageUrl ||
                      addMutation.isPending
                    }
                  >
                    {addMutation.isPending ? (
                      <Loader2 className="h-4 w-4 animate-spin" />
                    ) : (
                      <Plus className="h-4 w-4" />
                    )}
                    Save site
                  </Button>
                </DialogFooter>
              </DialogContent>
            </Dialog>
          </>
        )}
      </CardContent>
    </Card>
  );
}
