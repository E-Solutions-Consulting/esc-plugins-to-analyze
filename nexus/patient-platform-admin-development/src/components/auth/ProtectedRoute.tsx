import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '@/stores/authStore';
import { ROUTES } from '@/lib/constants';
import { Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  canAccessTenantRoute,
  getTenantAccessDeniedRedirect,
} from '@/lib/admin-permissions';

interface ProtectedRouteProps {
  children: React.ReactNode;
  requirePlatformAdmin?: boolean;
  requireTenantAdmin?: boolean;
  requireTenantAccess?: boolean;
}

export function ProtectedRoute({
  children,
  requirePlatformAdmin = false,
  requireTenantAdmin = false,
  requireTenantAccess = false,
}: ProtectedRouteProps) {
  const {
    isAuthenticated,
    isLoading,
    isPlatformSuperadmin,
    isTenantAdmin,
    isCustomerSupport,
    profileStatus,
    profileError,
    refreshProfile,
    signOut,
  } = useAuth();
  const location = useLocation();

  if (isLoading || (isAuthenticated && (profileStatus === 'loading' || profileStatus === 'idle'))) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <Loader2 className="h-8 w-8 animate-spin text-primary" />
      </div>
    );
  }

  if (isAuthenticated && profileStatus === 'error') {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="max-w-md text-center space-y-4">
          <h2 className="text-lg font-semibold">We couldn't load your profile</h2>
          <p className="text-sm text-muted-foreground">
            {profileError || 'Please check your connection and try again.'}
          </p>
          <div className="flex items-center justify-center gap-3">
            <Button onClick={refreshProfile}>Retry</Button>
            <Button variant="outline" onClick={signOut}>
              Sign out
            </Button>
          </div>
        </div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to={ROUTES.LOGIN} state={{ from: location }} replace />;
  }

  if (requirePlatformAdmin && !isPlatformSuperadmin) {
    return <Navigate to={ROUTES.TENANT_ADMIN.DASHBOARD} replace />;
  }

  if (requireTenantAdmin && !isTenantAdmin && !isPlatformSuperadmin) {
    return <Navigate to={ROUTES.LOGIN} replace />;
  }

  if (
    requireTenantAccess &&
    !canAccessTenantRoute(
      { isPlatformSuperadmin, isTenantAdmin, isCustomerSupport },
      location.pathname,
    )
  ) {
    return (
      <Navigate
        to={getTenantAccessDeniedRedirect({
          isPlatformSuperadmin,
          isTenantAdmin,
          isCustomerSupport,
        })}
        replace
      />
    );
  }

  return <>{children}</>;
}
