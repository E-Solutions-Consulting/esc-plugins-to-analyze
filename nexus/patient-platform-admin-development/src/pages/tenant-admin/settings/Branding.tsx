import { ImageUpload } from "@/components/common/ImageUpload";
import { PageHeader } from "@/components/common/PageHeader";
import { AdminLayout } from "@/components/layouts/AdminLayout";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useAuditLog } from "@/hooks/useAuditLog";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/stores/authStore";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Image, Link, Loader2, Mail, Palette, Save, Type } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";

const BRAND_ASSETS_BUCKET = "brand-assets";

interface TenantBranding {
  id?: string;
  tenant_id: string;
  logo_url: string | null;
  logo_has_wordmark: boolean;
  primary_color: string | null;
  secondary_color: string | null;
  accent_color: string | null;
  rise_logo_url: string | null;
  aria_logo_url: string | null;
  favicon_url: string | null;
  font_family: string | null;
  support_email: string | null;
  terms_url: string | null;
  privacy_url: string | null;
  hipaa_url: string | null;
}

type BrandingFormData = Omit<TenantBranding, "id" | "tenant_id">;

const DEFAULT_FORM: BrandingFormData = {
  logo_url: null,
  logo_has_wordmark: false,
  primary_color: "#3B82F6",
  secondary_color: "#1E40AF",
  accent_color: "#10B981",
  rise_logo_url: null,
  aria_logo_url: null,
  favicon_url: null,
  font_family: "Inter, sans-serif",
  support_email: "",
  terms_url: "/terms",
  privacy_url: "/privacy",
  hipaa_url: "/hipaa",
};

/** Page body without the AdminLayout wrapper (for reuse in Settings v2). */
export function BrandingContent() {
  const { currentTenantId } = useAuth();
  const { logAction } = useAuditLog();
  const queryClient = useQueryClient();
  const [formData, setFormData] = useState<BrandingFormData>(DEFAULT_FORM);
  const [activeTab, setActiveTab] = useState("logos");

  const { data: branding, isLoading } = useQuery({
    queryKey: ["tenant-branding", currentTenantId],
    queryFn: async () => {
      if (!currentTenantId) return null;

      const { data, error } = await (
        supabase as unknown as {
          from: (t: string) => {
            select: (cols: string) => {
              eq: (
                col: string,
                val: string,
              ) => {
                single: () => Promise<{
                  data: TenantBranding | null;
                  error: { code: string } | null;
                }>;
              };
            };
          };
        }
      )
        .from("tenant_branding")
        .select("*")
        .eq("tenant_id", currentTenantId)
        .single();

      if (error && error.code !== "PGRST116") throw error;
      return data;
    },
    enabled: !!currentTenantId,
  });

  useEffect(() => {
    if (branding) {
      setFormData({
        logo_url: branding.logo_url,
        logo_has_wordmark: branding.logo_has_wordmark === true,
        primary_color: branding.primary_color || DEFAULT_FORM.primary_color,
        secondary_color:
          branding.secondary_color || DEFAULT_FORM.secondary_color,
        accent_color: branding.accent_color || DEFAULT_FORM.accent_color,
        rise_logo_url: branding.rise_logo_url,
        aria_logo_url: branding.aria_logo_url,
        favicon_url: branding.favicon_url,
        font_family: branding.font_family || DEFAULT_FORM.font_family,
        support_email: branding.support_email || "",
        terms_url: branding.terms_url || DEFAULT_FORM.terms_url,
        privacy_url: branding.privacy_url || DEFAULT_FORM.privacy_url,
        hipaa_url: branding.hipaa_url || DEFAULT_FORM.hipaa_url,
      });
    }
  }, [branding]);

  const saveMutation = useMutation({
    mutationFn: async (data: BrandingFormData) => {
      if (!currentTenantId) throw new Error("No tenant selected");

      const payload = {
        tenant_id: currentTenantId,
        ...data,
        support_email: data.support_email?.trim() || null,
        font_family: data.font_family?.trim() || null,
        terms_url: data.terms_url?.trim() || null,
        privacy_url: data.privacy_url?.trim() || null,
        hipaa_url: data.hipaa_url?.trim() || null,
      };

      const { data: result, error } = await (
        supabase as unknown as {
          from: (t: string) => {
            upsert: (
              payload: Record<string, unknown>,
              opts: { onConflict: string },
            ) => {
              select: () => {
                single: () => Promise<{
                  data: TenantBranding;
                  error: Error | null;
                }>;
              };
            };
          };
        }
      )
        .from("tenant_branding")
        .upsert(payload as unknown as Record<string, unknown>, {
          onConflict: "tenant_id",
        })
        .select()
        .single();

      if (error) throw error;
      return { result, previousData: branding };
    },
    onSuccess: ({ result, previousData }) => {
      queryClient.invalidateQueries({ queryKey: ["tenant-branding"] });
      logAction({
        action: previousData ? "update" : "create",
        entityType: "tenant_branding",
        entityId: result.id ?? currentTenantId ?? "",
        beforeData: previousData as unknown as Record<string, unknown>,
        afterData: result as unknown as Record<string, unknown>,
      });
      toast.success("Branding saved successfully");
    },
    onError: (error) => {
      toast.error(
        error instanceof Error ? error.message : "Failed to save branding",
      );
    },
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    saveMutation.mutate(formData);
  };

  const assetFolder = currentTenantId ?? "unknown";

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
      </div>
    );
  }

  return (
    <>
      <PageHeader
        title="Branding"
        description="Manage brand assets, colors, and contact details for this tenant"
      />

      <form onSubmit={handleSubmit} className="space-y-6">
        <Tabs
          value={activeTab}
          onValueChange={setActiveTab}
          className="space-y-6"
        >
          <TabsList className="flex h-auto w-full flex-wrap justify-start gap-2 rounded-xl bg-muted/50 p-1">
            <TabsTrigger value="logos" className="gap-2">
              <Image className="h-4 w-4" />
              Logos &amp; Icons
            </TabsTrigger>
            <TabsTrigger value="colors" className="gap-2">
              <Palette className="h-4 w-4" />
              Colors
            </TabsTrigger>
            <TabsTrigger value="typography" className="gap-2">
              <Type className="h-4 w-4" />
              Typography
            </TabsTrigger>
            <TabsTrigger value="contact" className="gap-2">
              <Mail className="h-4 w-4" />
              Contact
            </TabsTrigger>
            <TabsTrigger value="legal" className="gap-2">
              <Link className="h-4 w-4" />
              Legal Links
            </TabsTrigger>
          </TabsList>

          {/* ── Logos & Icons ── */}
          <TabsContent value="logos" className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>Logos &amp; Icons</CardTitle>
                <CardDescription>
                  Upload brand assets. Accepted formats: PNG, JPG, WebP, SVG
                  (max 5 MB each).
                </CardDescription>
              </CardHeader>
              <CardContent className="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                <div className="space-y-2">
                  <Label>Main Logo</Label>
                  <p className="text-xs text-muted-foreground">
                    Shown in the app header and login screen
                  </p>
                  <ImageUpload
                    bucket={BRAND_ASSETS_BUCKET}
                    folder={assetFolder}
                    value={formData.logo_url}
                    onChange={(url) =>
                      setFormData({ ...formData, logo_url: url })
                    }
                  />
                  <div className="flex items-start gap-2 pt-1">
                    <Checkbox
                      id="logo-has-wordmark"
                      data-testid="checkbox-logo-has-wordmark"
                      checked={formData.logo_has_wordmark}
                      onCheckedChange={(checked) =>
                        setFormData({
                          ...formData,
                          logo_has_wordmark: checked === true,
                        })
                      }
                    />
                    <div className="space-y-1">
                      <Label
                        htmlFor="logo-has-wordmark"
                        className="text-sm font-normal leading-none"
                      >
                        Logo already includes the brand name
                      </Label>
                      <p className="text-xs text-muted-foreground">
                        Tick for wordmark logos, so the patient app doesn&apos;t
                        show the name twice next to the logo.
                      </p>
                    </div>
                  </div>
                </div>

                <div className="space-y-2">
                  <Label>RISE Fitness Logo</Label>
                  <p className="text-xs text-muted-foreground">
                    Used in the dashboard fitness card and nav
                  </p>
                  <ImageUpload
                    bucket={BRAND_ASSETS_BUCKET}
                    folder={assetFolder}
                    value={formData.rise_logo_url}
                    onChange={(url) =>
                      setFormData({ ...formData, rise_logo_url: url })
                    }
                  />
                </div>

                <div className="space-y-2">
                  <Label>Aria AI Logo</Label>
                  <p className="text-xs text-muted-foreground">
                    Displayed on the Aria AI companion icon and header
                  </p>
                  <ImageUpload
                    bucket={BRAND_ASSETS_BUCKET}
                    folder={assetFolder}
                    value={formData.aria_logo_url}
                    onChange={(url) =>
                      setFormData({ ...formData, aria_logo_url: url })
                    }
                  />
                </div>

                <div className="space-y-2">
                  <Label>Favicon</Label>
                  <p className="text-xs text-muted-foreground">
                    Browser tab icon (square, ideally 512×512)
                  </p>
                  <ImageUpload
                    bucket={BRAND_ASSETS_BUCKET}
                    folder={assetFolder}
                    value={formData.favicon_url}
                    onChange={(url) =>
                      setFormData({ ...formData, favicon_url: url })
                    }
                  />
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          {/* ── Colors ── */}
          <TabsContent value="colors" className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>Brand Colors</CardTitle>
                <CardDescription>
                  Hex values applied as CSS variables across the patient UI
                </CardDescription>
              </CardHeader>
              <CardContent className="grid gap-6 sm:grid-cols-3">
                <div className="space-y-2">
                  <Label htmlFor="primary_color">Primary Color</Label>
                  <div className="flex items-center gap-3">
                    <input
                      id="primary_color"
                      type="color"
                      value={formData.primary_color ?? "#3B82F6"}
                      onChange={(e) =>
                        setFormData({
                          ...formData,
                          primary_color: e.target.value,
                        })
                      }
                      className="h-10 w-14 cursor-pointer rounded border p-1"
                    />
                    <Input
                      value={formData.primary_color ?? ""}
                      onChange={(e) =>
                        setFormData({
                          ...formData,
                          primary_color: e.target.value,
                        })
                      }
                      placeholder="#3B82F6"
                      className="font-mono"
                    />
                  </div>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="secondary_color">Secondary Color</Label>
                  <div className="flex items-center gap-3">
                    <input
                      id="secondary_color"
                      type="color"
                      value={formData.secondary_color ?? "#1E40AF"}
                      onChange={(e) =>
                        setFormData({
                          ...formData,
                          secondary_color: e.target.value,
                        })
                      }
                      className="h-10 w-14 cursor-pointer rounded border p-1"
                    />
                    <Input
                      value={formData.secondary_color ?? ""}
                      onChange={(e) =>
                        setFormData({
                          ...formData,
                          secondary_color: e.target.value,
                        })
                      }
                      placeholder="#1E40AF"
                      className="font-mono"
                    />
                  </div>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="accent_color">Accent Color</Label>
                  <div className="flex items-center gap-3">
                    <input
                      id="accent_color"
                      type="color"
                      value={formData.accent_color ?? "#10B981"}
                      onChange={(e) =>
                        setFormData({
                          ...formData,
                          accent_color: e.target.value,
                        })
                      }
                      className="h-10 w-14 cursor-pointer rounded border p-1"
                    />
                    <Input
                      value={formData.accent_color ?? ""}
                      onChange={(e) =>
                        setFormData({
                          ...formData,
                          accent_color: e.target.value,
                        })
                      }
                      placeholder="#10B981"
                      className="font-mono"
                    />
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Live preview */}
            <Card>
              <CardHeader>
                <CardTitle>Color Preview</CardTitle>
                <CardDescription>
                  How these colors appear as swatches
                </CardDescription>
              </CardHeader>
              <CardContent className="flex flex-wrap gap-4">
                {[
                  { label: "Primary", color: formData.primary_color },
                  { label: "Secondary", color: formData.secondary_color },
                  { label: "Accent", color: formData.accent_color },
                ].map(({ label, color }) => (
                  <div
                    key={label}
                    className="flex flex-col items-center gap-1.5"
                  >
                    <div
                      className="h-16 w-16 rounded-xl border shadow-sm"
                      style={{ backgroundColor: color ?? "transparent" }}
                    />
                    <span className="text-xs text-muted-foreground">
                      {label}
                    </span>
                    <span className="font-mono text-xs">{color ?? "—"}</span>
                  </div>
                ))}
              </CardContent>
            </Card>
          </TabsContent>

          {/* ── Typography ── */}
          <TabsContent value="typography" className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>Typography</CardTitle>
                <CardDescription>
                  Font family applied via the{" "}
                  <code className="text-xs">--font-family</code> CSS variable
                </CardDescription>
              </CardHeader>
              <CardContent className="max-w-md space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="font_family">Font Family</Label>
                  <Input
                    id="font_family"
                    value={formData.font_family ?? ""}
                    onChange={(e) =>
                      setFormData({ ...formData, font_family: e.target.value })
                    }
                    placeholder="Inter, sans-serif"
                  />
                  <p className="text-xs text-muted-foreground">
                    Use a CSS font-family value. Ensure the font is loaded via a
                    stylesheet or Google Fonts URL in your deployment.
                  </p>
                </div>

                {formData.font_family && (
                  <p
                    className="text-lg text-foreground"
                    style={{ fontFamily: formData.font_family }}
                  >
                    The quick brown fox jumps over the lazy dog
                  </p>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          {/* ── Contact ── */}
          <TabsContent value="contact" className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>Contact Details</CardTitle>
                <CardDescription>
                  Support contact info displayed to patients throughout the
                  platform
                </CardDescription>
              </CardHeader>
              <CardContent className="max-w-md space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="support_email">Support Email</Label>
                  <Input
                    id="support_email"
                    type="email"
                    value={formData.support_email ?? ""}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        support_email: e.target.value,
                      })
                    }
                    placeholder="support@yourtenant.com"
                  />
                  <p className="text-xs text-muted-foreground">
                    Shown on the checkout confirmation page and wherever
                    patients need to reach support
                  </p>
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          {/* ── Legal Links ── */}
          <TabsContent value="legal" className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>Legal Links</CardTitle>
                <CardDescription>
                  URLs for legal documents linked in consent copy, footer, and
                  onboarding
                </CardDescription>
              </CardHeader>
              <CardContent className="max-w-lg space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="terms_url">Terms of Service URL</Label>
                  <Input
                    id="terms_url"
                    value={formData.terms_url ?? ""}
                    onChange={(e) =>
                      setFormData({ ...formData, terms_url: e.target.value })
                    }
                    placeholder="/terms"
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="privacy_url">Privacy Policy URL</Label>
                  <Input
                    id="privacy_url"
                    value={formData.privacy_url ?? ""}
                    onChange={(e) =>
                      setFormData({ ...formData, privacy_url: e.target.value })
                    }
                    placeholder="/privacy"
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="hipaa_url">HIPAA Notice URL</Label>
                  <Input
                    id="hipaa_url"
                    value={formData.hipaa_url ?? ""}
                    onChange={(e) =>
                      setFormData({ ...formData, hipaa_url: e.target.value })
                    }
                    placeholder="/hipaa"
                  />
                </div>
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>

        <div className="flex justify-end">
          <Button
            type="submit"
            disabled={saveMutation.isPending}
            className="gap-2"
          >
            {saveMutation.isPending ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
              <Save className="h-4 w-4" />
            )}
            Save Branding
          </Button>
        </div>
      </form>
    </>
  );
}

/** Original route entry point — keeps the standalone page working unchanged. */
export default function BrandingSettings() {
  return (
    <AdminLayout variant="tenant">
      <BrandingContent />
    </AdminLayout>
  );
}
