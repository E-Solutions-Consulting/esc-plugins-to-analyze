import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { AdminLayout } from '@/components/layouts/AdminLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { HtmlEditor } from '@/components/common/HtmlEditor';
import { TermsPreview } from '@/components/common/TermsPreview';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
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
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { supabase } from '@/integrations/supabase/client';
import { useAuditLog } from '@/hooks/useAuditLog';
import { useAuth } from '@/stores/authStore';
import { dateTime } from '@/lib/dayjs';
import { toNullableRichTextHtml } from '@/lib/html-content';
import { toast } from 'sonner';
import { FileText, Loader2, Pencil, Save, Trash2, X } from 'lucide-react';

type LegalDocumentVersion = {
  id: string;
  tenant_id: string;
  version: number;
  content: string;
  is_live: boolean;
  created_by_admin_user_id: string | null;
  published_by_admin_user_id: string | null;
  published_at: string | null;
  created_at: string;
  updated_at: string;
};

type LegalDocumentVersionsProps = {
  tableName: string;
  queryKey: string;
  auditEntityType: string;
  title: string;
  description: string;
  unavailableMessage: string;
  contentLabel: string;
  contentPlaceholder: string;
  editPlaceholder: string;
  emptyMessage: string;
  requiredMessage: string;
  publishedEditMessage: string;
  publishedDeleteMessage: string;
  createErrorLogMessage: string;
  updateErrorLogMessage: string;
  deleteErrorLogMessage: string;
  publishErrorLogMessage: string;
  /**
   * When true, render content WITHOUT the AdminLayout wrapper so it can be
   * embedded inside another layout (e.g. the regrouped Settings v2 IA). The
   * standalone routes leave this false and keep their own AdminLayout.
   */
  embedded?: boolean;
};

export function LegalDocumentVersions({
  tableName,
  queryKey,
  auditEntityType,
  title,
  description,
  unavailableMessage,
  contentLabel,
  contentPlaceholder,
  editPlaceholder,
  emptyMessage,
  requiredMessage,
  publishedEditMessage,
  publishedDeleteMessage,
  createErrorLogMessage,
  updateErrorLogMessage,
  deleteErrorLogMessage,
  publishErrorLogMessage,
  embedded = false,
}: LegalDocumentVersionsProps) {
  const queryClient = useQueryClient();
  const { logAction } = useAuditLog();
  const { currentTenantId, isTenantAdmin, isPlatformSuperadmin, user } = useAuth();
  const [draftContent, setDraftContent] = useState('');
  const [publishOnCreate, setPublishOnCreate] = useState(false);
  const [editingVersionId, setEditingVersionId] = useState<string | null>(null);
  const [editingContent, setEditingContent] = useState('');
  const [draftVersionPendingDelete, setDraftVersionPendingDelete] =
    useState<LegalDocumentVersion | null>(null);
  const [expandedVersionIds, setExpandedVersionIds] = useState<string[]>([]);
  const [collapsedDraftVersionIds, setCollapsedDraftVersionIds] = useState<string[]>([]);

  const canManageDocuments = Boolean(currentTenantId && (isTenantAdmin || isPlatformSuperadmin));
  const queryKeyParts = [queryKey, currentTenantId];

  useEffect(() => {
    setDraftContent('');
    setPublishOnCreate(false);
    setEditingVersionId(null);
    setEditingContent('');
    setDraftVersionPendingDelete(null);
    setExpandedVersionIds([]);
    setCollapsedDraftVersionIds([]);
  }, [currentTenantId]);

  const { data: versions = [], isLoading } = useQuery({
    queryKey: queryKeyParts,
    queryFn: async () => {
      if (!currentTenantId) return [];

      const client = supabase as unknown as {
        from: (table: string) => {
          select: (query: string) => {
            eq: (column: string, value: string) => {
              order: (column: string, options?: { ascending?: boolean }) => Promise<{
                data: unknown[] | null;
                error: Error | null;
              }>;
            };
          };
        };
      };

      const { data, error } = await client
        .from(tableName)
        .select('*')
        .eq('tenant_id', currentTenantId)
        .order('version', { ascending: false });

      if (error) throw error;
      return (data ?? []) as LegalDocumentVersion[];
    },
    enabled: canManageDocuments,
  });

  const createVersion = useMutation({
    mutationFn: async () => {
      if (!currentTenantId) {
        throw new Error('No tenant selected.');
      }

      const content = toNullableRichTextHtml(draftContent);
      if (!content) {
        throw new Error(requiredMessage);
      }

      const client = supabase as unknown as {
        from: (table: string) => {
          insert: (payload: Record<string, unknown>) => {
            select: (query: string) => {
              single: () => Promise<{ data: unknown; error: Error | null }>;
            };
          };
        };
      };

      const payload: Record<string, unknown> = {
        tenant_id: currentTenantId,
        content,
        is_live: publishOnCreate,
        created_by_admin_user_id: user?.id ?? null,
      };

      if (publishOnCreate) {
        payload.published_by_admin_user_id = user?.id ?? null;
      }

      const { data, error } = await client
        .from(tableName)
        .insert(payload)
        .select('*')
        .single();

      if (error) throw error;
      return data as LegalDocumentVersion;
    },
    onSuccess: async (createdVersion) => {
      queryClient.invalidateQueries({ queryKey: queryKeyParts });
      setDraftContent('');

      await logAction({
        action: 'create',
        entityType: auditEntityType,
        entityId: createdVersion.id,
        beforeData: null,
        afterData: {
          version: createdVersion.version,
          is_live: createdVersion.is_live,
        },
        tenantId: currentTenantId,
      });

      toast.success(
        createdVersion.is_live
          ? `Version ${createdVersion.version} created and published`
          : `Version ${createdVersion.version} created`,
      );
    },
    onError: (error) => {
      console.error(createErrorLogMessage, error);
      toast.error(error instanceof Error ? error.message : 'Failed to create version');
    },
  });

  const updateDraftVersion = useMutation({
    mutationFn: async ({
      version,
      content: draftUpdateContent,
    }: {
      version: LegalDocumentVersion;
      content: string;
    }) => {
      if (version.published_at) {
        throw new Error(publishedEditMessage);
      }

      const content = toNullableRichTextHtml(draftUpdateContent);
      if (!content) {
        throw new Error(requiredMessage);
      }

      const client = supabase as unknown as {
        from: (table: string) => {
          update: (payload: Record<string, unknown>) => {
            eq: (column: string, value: string) => {
              eq: (column: string, value: string) => {
                is: (column: string, value: null) => {
                  select: (query: string) => {
                    single: () => Promise<{ data: unknown; error: Error | null }>;
                  };
                };
              };
            };
          };
        };
      };

      const { data, error } = await client
        .from(tableName)
        .update({ content })
        .eq('id', version.id)
        .eq('tenant_id', version.tenant_id)
        .is('published_at', null)
        .select('*')
        .single();

      if (error) throw error;
      return { before: version, after: data as LegalDocumentVersion };
    },
    onSuccess: async ({ before, after }) => {
      queryClient.invalidateQueries({ queryKey: queryKeyParts });
      setEditingVersionId(null);
      setEditingContent('');

      await logAction({
        action: 'update',
        entityType: auditEntityType,
        entityId: after.id,
        beforeData: {
          version: before.version,
          content: before.content,
          published_at: before.published_at,
        },
        afterData: {
          version: after.version,
          content: after.content,
          published_at: after.published_at,
        },
        tenantId: after.tenant_id,
      });

      toast.success(`Draft version ${after.version} saved`);
    },
    onError: (error) => {
      console.error(updateErrorLogMessage, error);
      toast.error(error instanceof Error ? error.message : 'Failed to save draft');
    },
  });

  const deleteDraftVersion = useMutation({
    mutationFn: async (version: LegalDocumentVersion) => {
      if (version.published_at) {
        throw new Error(publishedDeleteMessage);
      }

      const client = supabase as unknown as {
        from: (table: string) => {
          delete: () => {
            eq: (column: string, value: string) => {
              eq: (column: string, value: string) => {
                is: (column: string, value: null) => {
                  select: (query: string) => {
                    single: () => Promise<{ data: unknown; error: Error | null }>;
                  };
                };
              };
            };
          };
        };
      };

      const { data, error } = await client
        .from(tableName)
        .delete()
        .eq('id', version.id)
        .eq('tenant_id', version.tenant_id)
        .is('published_at', null)
        .select('*')
        .single();

      if (error) throw error;
      return data as LegalDocumentVersion;
    },
    onSuccess: async (deletedVersion) => {
      queryClient.invalidateQueries({ queryKey: queryKeyParts });
      setDraftVersionPendingDelete(null);
      setExpandedVersionIds((currentIds) =>
        currentIds.filter((id) => id !== deletedVersion.id),
      );
      setCollapsedDraftVersionIds((currentIds) =>
        currentIds.filter((id) => id !== deletedVersion.id),
      );

      if (editingVersionId === deletedVersion.id) {
        setEditingVersionId(null);
        setEditingContent('');
      }

      await logAction({
        action: 'delete',
        entityType: auditEntityType,
        entityId: deletedVersion.id,
        beforeData: {
          version: deletedVersion.version,
          content: deletedVersion.content,
          is_live: deletedVersion.is_live,
          published_at: deletedVersion.published_at,
        },
        afterData: null,
        tenantId: deletedVersion.tenant_id,
      });

      toast.success(`Draft version ${deletedVersion.version} deleted`);
    },
    onError: (error) => {
      console.error(deleteErrorLogMessage, error);
      toast.error(error instanceof Error ? error.message : 'Failed to delete draft');
    },
  });

  const publishVersion = useMutation({
    mutationFn: async (version: LegalDocumentVersion) => {
      const client = supabase as unknown as {
        from: (table: string) => {
          update: (payload: Record<string, unknown>) => {
            eq: (column: string, value: string) => {
              eq: (column: string, value: string) => {
                select: (query: string) => {
                  single: () => Promise<{ data: unknown; error: Error | null }>;
                };
              };
            };
          };
        };
      };

      const payload: Record<string, unknown> = { is_live: true };
      if (!version.published_at) {
        payload.published_by_admin_user_id = user?.id ?? null;
        payload.published_at = new Date().toISOString();
      }

      const { data, error } = await client
        .from(tableName)
        .update(payload)
        .eq('id', version.id)
        .eq('tenant_id', version.tenant_id)
        .select('*')
        .single();

      if (error) throw error;
      return { before: version, after: data as LegalDocumentVersion };
    },
    onSuccess: async ({ before, after }) => {
      queryClient.invalidateQueries({ queryKey: queryKeyParts });

      await logAction({
        action: 'publish',
        entityType: auditEntityType,
        entityId: after.id,
        beforeData: {
          version: before.version,
          is_live: before.is_live,
        },
        afterData: {
          version: after.version,
          is_live: after.is_live,
        },
        tenantId: after.tenant_id,
      });

      toast.success(`Version ${after.version} is now live`);
    },
    onError: (error) => {
      console.error(publishErrorLogMessage, error);
      toast.error('Failed to publish version');
    },
  });

  const isDraftVersion = (version: LegalDocumentVersion) => !version.published_at;

  const toggleVersionContent = (version: LegalDocumentVersion) => {
    if (isDraftVersion(version)) {
      setCollapsedDraftVersionIds((currentIds) =>
        currentIds.includes(version.id)
          ? currentIds.filter((id) => id !== version.id)
          : [...currentIds, version.id],
      );
      return;
    }

    setExpandedVersionIds((currentIds) =>
      currentIds.includes(version.id)
        ? currentIds.filter((id) => id !== version.id)
        : [...currentIds, version.id],
    );
  };

  const handleEditDraft = (version: LegalDocumentVersion) => {
    if (!isDraftVersion(version)) {
      toast.error('Only draft versions can be edited.');
      return;
    }

    setEditingVersionId(version.id);
    setEditingContent(version.content);
  };

  const handleCancelDraftEdit = () => {
    setEditingVersionId(null);
    setEditingContent('');
  };

  const handleSaveDraft = () => {
    const version = versions.find((candidate) => candidate.id === editingVersionId);
    if (!version) return;

    updateDraftVersion.mutate({ version, content: editingContent });
  };

  if (!canManageDocuments) {
    const unavailableContent = (
      <div className="flex h-full items-center justify-center">
        <p className="text-muted-foreground">{unavailableMessage}</p>
      </div>
    );

    return embedded ? (
      unavailableContent
    ) : (
      <AdminLayout variant="tenant">{unavailableContent}</AdminLayout>
    );
  }

  const content = (
    <>
      <PageHeader title={title} description={description} />

      <Card>
        <CardHeader>
          <CardTitle>Create a New Version</CardTitle>
          <CardDescription>
            Save as draft to keep editing until the version is first made live.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor={`${queryKey}-content`}>{contentLabel}</Label>
            <HtmlEditor
              id={`${queryKey}-content`}
              value={draftContent}
              onChange={setDraftContent}
              placeholder={contentPlaceholder}
              minHeightClassName="h-80"
              editorClassName="resize-y overflow-auto"
            />
          </div>

          <div className="flex items-center gap-3 rounded-md border p-3">
            <Checkbox
              id={`publish-${queryKey}`}
              checked={publishOnCreate}
              onCheckedChange={(checked) => setPublishOnCreate(checked === true)}
            />
            <Label htmlFor={`publish-${queryKey}`} className="cursor-pointer">
              Make this version live as soon as it is created
            </Label>
          </div>

          <Button
            onClick={() => createVersion.mutate()}
            disabled={createVersion.isPending || !draftContent.trim()}
          >
            {createVersion.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            {publishOnCreate ? 'Create and make live' : 'Save draft'}
          </Button>
        </CardContent>
      </Card>

      <Card className="mt-6">
        <CardHeader>
          <CardTitle>Version History</CardTitle>
          <CardDescription>
            Review previous tenant versions and switch the live version when needed.
          </CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-4">
              <Skeleton className="h-24 w-full" />
              <Skeleton className="h-24 w-full" />
            </div>
          ) : versions.length === 0 ? (
            <div className="flex items-center gap-3 rounded-md border bg-muted/20 p-4">
              <FileText className="h-4 w-4 text-muted-foreground" />
              <p className="text-sm text-muted-foreground">{emptyMessage}</p>
            </div>
          ) : (
            <div className="space-y-4">
              {versions.map((version, index) => {
                const isDraft = isDraftVersion(version);
                const isEditing = editingVersionId === version.id;
                const isExpanded =
                  version.is_live ||
                  isEditing ||
                  (isDraft
                    ? !collapsedDraftVersionIds.includes(version.id)
                    : expandedVersionIds.includes(version.id));

                return (
                  <div key={version.id} className="space-y-4">
                    <div className="space-y-4 rounded-lg border p-4">
                      <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div className="min-w-0 flex-1 space-y-3">
                          <div className="flex flex-wrap items-center gap-2">
                            <span className="font-semibold">Version {version.version}</span>
                            {version.is_live && <Badge>Live</Badge>}
                            {isDraft && <Badge variant="outline">Draft</Badge>}
                            {!version.is_live && version.published_at && (
                              <Badge variant="secondary">Previously live</Badge>
                            )}
                            <span className="text-sm text-muted-foreground">
                              Created {dateTime(version.created_at).format('MMM D, YYYY h:mm A')}
                            </span>
                            {version.published_at && (
                              <span className="text-sm text-muted-foreground">
                                First made live {dateTime(version.published_at).format('MMM D, YYYY h:mm A')}
                              </span>
                            )}
                          </div>
                          {!version.is_live && !isEditing && (
                            <Button
                              type="button"
                              variant="ghost"
                              size="sm"
                              className="w-fit px-0 text-sm"
                              onClick={() => toggleVersionContent(version)}
                            >
                              {isExpanded ? 'Collapse content' : 'Show content'}
                            </Button>
                          )}
                        </div>

                        <div className="flex flex-wrap gap-2 lg:justify-end">
                          {isEditing ? (
                            <>
                              <Button
                                type="button"
                                variant="outline"
                                onClick={handleCancelDraftEdit}
                                disabled={updateDraftVersion.isPending}
                              >
                                <X className="h-4 w-4" />
                                Cancel
                              </Button>
                              <Button
                                type="button"
                                onClick={handleSaveDraft}
                                disabled={updateDraftVersion.isPending || !editingContent.trim()}
                              >
                                {updateDraftVersion.isPending ? (
                                  <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                  <Save className="h-4 w-4" />
                                )}
                                Save draft
                              </Button>
                            </>
                          ) : (
                            <>
                              {isDraft && (
                                <Button
                                  type="button"
                                  variant="outline"
                                  onClick={() => handleEditDraft(version)}
                                  disabled={
                                    updateDraftVersion.isPending ||
                                    publishVersion.isPending ||
                                    deleteDraftVersion.isPending
                                  }
                                >
                                  <Pencil className="h-4 w-4" />
                                  Edit draft
                                </Button>
                              )}
                              {isDraft && (
                                <Button
                                  type="button"
                                  variant="outline"
                                  onClick={() => setDraftVersionPendingDelete(version)}
                                  disabled={
                                    updateDraftVersion.isPending ||
                                    publishVersion.isPending ||
                                    deleteDraftVersion.isPending
                                  }
                                >
                                  <Trash2 className="h-4 w-4" />
                                  Delete draft
                                </Button>
                              )}
                              {!version.is_live && (
                                <Button
                                  type="button"
                                  variant="outline"
                                  onClick={() => publishVersion.mutate(version)}
                                  disabled={
                                    publishVersion.isPending ||
                                    updateDraftVersion.isPending ||
                                    deleteDraftVersion.isPending
                                  }
                                >
                                  {publishVersion.isPending && <Loader2 className="h-4 w-4 animate-spin" />}
                                  Make live
                                </Button>
                              )}
                            </>
                          )}
                        </div>
                      </div>

                      {isEditing ? (
                        <HtmlEditor
                          id={`${queryKey}-version-${version.id}`}
                          value={editingContent}
                          onChange={setEditingContent}
                          placeholder={editPlaceholder}
                          minHeightClassName="h-80"
                          editorClassName="resize-y overflow-auto"
                          disabled={updateDraftVersion.isPending}
                        />
                      ) : isExpanded && (
                        <TermsPreview content={version.content} />
                      )}
                    </div>
                    {index < versions.length - 1 && <Separator />}
                  </div>
                );
              })}
            </div>
          )}
        </CardContent>
      </Card>

      <AlertDialog
        open={Boolean(draftVersionPendingDelete)}
        onOpenChange={(open) => {
          if (!open && !deleteDraftVersion.isPending) {
            setDraftVersionPendingDelete(null);
          }
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete draft version?</AlertDialogTitle>
            <AlertDialogDescription>
              This will permanently delete draft version{' '}
              {draftVersionPendingDelete?.version ?? ''}. Published versions cannot be deleted.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleteDraftVersion.isPending}>
              Cancel
            </AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              disabled={deleteDraftVersion.isPending}
              onClick={(event) => {
                event.preventDefault();
                if (draftVersionPendingDelete) {
                  deleteDraftVersion.mutate(draftVersionPendingDelete);
                }
              }}
            >
              {deleteDraftVersion.isPending && (
                <Loader2 className="h-4 w-4 animate-spin" />
              )}
              Delete draft
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );

  return embedded ? content : <AdminLayout variant="tenant">{content}</AdminLayout>;
}
