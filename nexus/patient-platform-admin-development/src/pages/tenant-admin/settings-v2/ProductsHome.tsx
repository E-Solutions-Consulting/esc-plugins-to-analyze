/**
 * Products home — top-level product TYPES (lines), per-tenant activated.
 *
 * Types come from the global product_types table (Medications, Labs, Fitness,
 * Wearables, Experiences) with an `availability` flag. Each tenant enables the
 * lines it offers (tenant_product_types); Medications is available + enabled by
 * default, Labs is "coming soon", the rest are coming soon too.
 *
 * Selecting an available + enabled type opens the real products experience
 * (ProductsContent) where products are added/managed under that line. The
 * sub-categories within a line (Weight Loss, Energy, …) live in product_categories
 * and are managed per-product as today.
 */
import { useState } from "react";
import { PageHeader } from "@/components/common/PageHeader";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Switch } from "@/components/ui/switch";
import { cn } from "@/lib/utils";
import {
  ArrowLeft,
  Clock,
  Dumbbell,
  FlaskConical,
  Loader2,
  Pill,
  Sparkles,
  Tag,
  Watch,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { ProductsContent } from "@/pages/tenant-admin/catalog/Products";
import {
  type TenantProductType,
  useTenantProductTypes,
} from "@/hooks/useTenantProductTypes";
import { canEditResource } from "@/lib/admin-permissions";
import { useAuth } from "@/stores/authStore";

// Icon per known type key; unknown keys fall back to a generic tag.
const TYPE_ICONS: Record<string, LucideIcon> = {
  medications: Pill,
  labs: FlaskConical,
  fitness: Dumbbell,
  wearables: Watch,
  experiences: Sparkles,
};

export function ProductsHome() {
  const { productTypes, isLoading, setTypeEnabled } = useTenantProductTypes();
  const {
    currentTenantId,
    isPlatformSuperadmin,
    isTenantAdmin,
    isCustomerSupport,
  } = useAuth();
  const [selected, setSelected] = useState<TenantProductType | null>(null);
  const canEditProducts = canEditResource(
    { isPlatformSuperadmin, isTenantAdmin, isCustomerSupport, currentTenantId },
    "product",
  );

  // A usable type selected → render the real products experience for that line.
  if (selected) {
    return (
      <div>
        <Button
          variant="ghost"
          size="sm"
          className="mb-2"
          onClick={() => setSelected(null)}
        >
          <ArrowLeft className="h-4 w-4 mr-1" /> All product types
        </Button>
        <p className="mb-4 text-sm text-muted-foreground">
          Managing <strong>{selected.name}</strong> products.
        </p>
        {/* Medications is the only line with a real products experience today;
            other lines reuse the same component once their backend lands. */}
        <ProductsContent />
      </div>
    );
  }

  return (
    <div className="max-w-4xl space-y-6">
      <PageHeader
        title="Products"
        description="Choose a product type to manage its products. Enable the types your tenant offers; types marked “coming soon” aren’t available yet."
      />

      {isLoading
        ? (
          <div className="flex items-center justify-center py-12">
            <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
          </div>
        )
        : productTypes.length === 0
        ? (
          <Card>
            <CardContent className="py-10 text-center text-sm text-muted-foreground">
              No product types are configured. Ask a platform admin to add them
              under Platform → Product Categories.
            </CardContent>
          </Card>
        )
        : (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {productTypes.map((type) => {
              const Icon = TYPE_ICONS[type.key] ?? Tag;
              const isAvailable = type.availability === "available";
              const isOpenable = isAvailable && type.isEnabled;
              return (
                <Card
                  key={type.id}
                  role={isOpenable ? "button" : undefined}
                  tabIndex={isOpenable ? 0 : undefined}
                  onClick={() => isOpenable && setSelected(type)}
                  className={cn(
                    "transition-colors",
                    isOpenable
                      ? "cursor-pointer hover:border-primary hover:bg-accent/40"
                      : !isAvailable
                      ? "opacity-60"
                      : "",
                  )}
                >
                  <CardContent className="flex flex-col gap-3 py-6">
                    <div className="flex items-center justify-between">
                      <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-muted">
                        <Icon className="h-5 w-5" />
                      </div>
                      {isAvailable
                        ? (
                          <Badge variant="secondary" className="gap-1 text-xs">
                            <Sparkles className="h-3 w-3" /> Available
                          </Badge>
                        )
                        : (
                          <Badge variant="secondary" className="gap-1 text-xs">
                            <Clock className="h-3 w-3" /> Coming soon
                          </Badge>
                        )}
                    </div>
                    <div>
                      <p className="font-semibold">{type.name}</p>
                      <p className="text-sm text-muted-foreground">
                        {type.description}
                      </p>
                    </div>

                    {/* Per-tenant enable toggle — only for available lines. */}
                    {isAvailable
                      ? (
                        <div
                          className="mt-1 flex items-center justify-between rounded-md border p-2"
                          onClick={(e) => e.stopPropagation()}
                        >
                          <span className="text-xs text-muted-foreground">
                            {type.isEnabled
                              ? "Enabled for this tenant"
                              : "Enable for this tenant"}
                          </span>
                          <Switch
                            checked={type.isEnabled}
                            disabled={!canEditProducts || setTypeEnabled.isPending}
                            onCheckedChange={(checked) =>
                              setTypeEnabled.mutate({
                                productTypeId: type.id,
                                isEnabled: checked,
                              })}
                            aria-label={`Toggle ${type.name} for this tenant`}
                          />
                        </div>
                      )
                      : null}
                  </CardContent>
                </Card>
              );
            })}
          </div>
        )}
    </div>
  );
}
