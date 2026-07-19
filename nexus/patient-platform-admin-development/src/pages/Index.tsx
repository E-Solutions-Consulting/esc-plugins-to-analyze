import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '@/stores/authStore';
import { ROUTES } from '@/lib/constants';
import { Loader2 } from 'lucide-react';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

const Index = () => {
  const navigate = useNavigate();
  const { isAuthenticated, isLoading, isPlatformSuperadmin, tenants } = useAuth();
  useDocumentTitle('Loading');

  useEffect(() => {
    if (isLoading) return;

    if (!isAuthenticated) {
      navigate(ROUTES.LOGIN);
      return;
    }

    // Redirect based on user role and tenant membership
    if (isPlatformSuperadmin && tenants.length === 0) {
      navigate(ROUTES.PLATFORM_ADMIN.DASHBOARD);
    } else {
      navigate(ROUTES.TENANT_ADMIN.DASHBOARD);
    }
  }, [isAuthenticated, isLoading, isPlatformSuperadmin, tenants, navigate]);

  return (
    <div className="min-h-screen flex items-center justify-center bg-background">
      <div className="text-center">
        <Loader2 className="h-8 w-8 animate-spin text-primary mx-auto mb-4" />
        <p className="text-muted-foreground">Loading...</p>
      </div>
    </div>
  );
};

export default Index;
