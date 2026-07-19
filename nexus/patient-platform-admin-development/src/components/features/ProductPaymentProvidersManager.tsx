import { ProductCouponsManager } from "@/components/features/ProductCouponsManager";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Switch } from "@/components/ui/switch";
import { useProductPaymentProviders } from "@/hooks/useProductPaymentProviders";
import { ROUTES } from "@/lib/constants";
import { AlertCircle, CreditCard, Loader2 } from "lucide-react";
import { Link } from "react-router-dom";

interface ProductPaymentProvidersManagerProps {
  productId: string;
  tenantId?: string | null;
  allowPromoCodes: boolean;
  readOnly?: boolean;
}

export function ProductPaymentProvidersManager({
  productId,
  tenantId,
  allowPromoCodes,
  readOnly = false,
}: ProductPaymentProvidersManagerProps) {
  const { providersWithAssignment, isLoading, toggleProductProvider } =
    useProductPaymentProviders(productId);

  if (isLoading) {
    return (
      <Card>
        <CardContent className="flex items-center justify-center py-8">
          <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
        </CardContent>
      </Card>
    );
  }

  if (!providersWithAssignment.length) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="text-lg">Payment Providers</CardTitle>
          <CardDescription>
            Select which payment providers can be used for this product.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="flex flex-col items-center justify-center py-6 text-center">
            <AlertCircle className="mb-3 h-10 w-10 text-muted-foreground" />
            <h4 className="mb-1 font-medium">
              No Payment Providers Configured
            </h4>
            <p className="mb-4 text-sm text-muted-foreground">
              Configure payment providers in your tenant settings first.
            </p>
            {!readOnly && (
            <Button variant="outline" size="sm" asChild>
              <Link to="/tenant-admin/settings/payments">
                <CreditCard className="mr-2 h-4 w-4" />
                Configure Payment Providers
              </Link>
            </Button>
            )}
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-lg">Payment Providers</CardTitle>
        <CardDescription>
          Select which payment providers can be used for this product.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <div className="space-y-3">
          {providersWithAssignment.map((provider) => (
            <div key={provider.id} className="rounded-lg border p-4">
              <div className="flex items-start justify-between gap-4">
                <div className="flex items-center gap-3">
                  {provider.payment_provider.logo_url ? (
                    <img
                      src={provider.payment_provider.logo_url}
                      alt={provider.payment_provider.name}
                      className="h-8 w-8 rounded object-contain"
                    />
                  ) : (
                    <div className="flex h-8 w-8 items-center justify-center rounded bg-muted">
                      <CreditCard className="h-4 w-4 text-muted-foreground" />
                    </div>
                  )}
                  <div className="space-y-1">
                    <p className="font-medium">
                      {provider.payment_provider.name}
                    </p>
                    <div className="flex flex-wrap items-center gap-2">
                      <Badge variant="outline" className="text-xs">
                        {provider.payment_provider.key}
                      </Badge>
                    </div>
                  </div>
                </div>

                <Switch
                  checked={provider.isAssigned}
                  onCheckedChange={(checked) => {
                    if (readOnly) return;
                    toggleProductProvider.mutate({
                      tenantPaymentProviderId: provider.id,
                      enabled: checked,
                    });
                  }}
                  disabled={readOnly || toggleProductProvider.isPending}
                />
              </div>
              {provider.payment_provider.key === "stripe" && (
                <div className="mt-4 border-t pt-4">
                  <ProductCouponsManager
                    productId={productId}
                    tenantId={tenantId ?? null}
                    allowPromoCodes={allowPromoCodes}
                    readOnly={readOnly}
                  />
                </div>
              )}
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  );
}
