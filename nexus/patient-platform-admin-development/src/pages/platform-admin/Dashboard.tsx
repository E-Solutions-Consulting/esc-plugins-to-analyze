import { useQuery } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { useAuth } from '@/stores/authStore';
import { AdminLayout } from '@/components/layouts/AdminLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Building2, Users, Flag, TrendingUp } from 'lucide-react';

export function PlatformDashboardContent() {
  const { isPlatformSuperadmin } = useAuth();

  const { data: metrics, isLoading } = useQuery({
    queryKey: ['platform-dashboard-metrics'],
    queryFn: async () => {
      const [tenantsRes, adminUsersRes, featureFlagsRes] = await Promise.all([
        supabase.from('tenants').select('id, status', { count: 'exact' }),
        supabase.from('admin_users').select('id', { count: 'exact' }),
        supabase.from('feature_flags').select('id', { count: 'exact' }),
      ]);

      const tenants = tenantsRes.data || [];

      return {
        totalTenants: tenantsRes.count || 0,
        activeTenants: tenants.filter((t) => t.status === 'active').length,
        totalAdminUsers: adminUsersRes.count || 0,
        totalFeatureFlags: featureFlagsRes.count || 0,
      };
    },
    enabled: isPlatformSuperadmin,
  });

  const statCards = [
    {
      title: 'Total Tenants',
      value: metrics?.totalTenants || 0,
      description: `${metrics?.activeTenants || 0} active`,
      icon: Building2,
    },
    {
      title: 'Admin Users',
      value: metrics?.totalAdminUsers || 0,
      description: 'Across all tenants',
      icon: Users,
    },
    {
      title: 'Feature Flags',
      value: metrics?.totalFeatureFlags || 0,
      description: 'Global flags defined',
      icon: Flag,
    },
    {
      title: 'Platform Health',
      value: '99.9%',
      description: 'Uptime this month',
      icon: TrendingUp,
    },
  ];

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
        title="Platform Dashboard"
        description="Platform-wide overview and metrics"
      />

      {/* Stats Grid */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4 mb-8">
        {statCards.map((stat) => (
          <Card key={stat.title}>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">
                {stat.title}
              </CardTitle>
              <stat.icon className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              {isLoading ? (
                <Skeleton className="h-8 w-20" />
              ) : (
                <>
                  <div className="text-2xl font-bold">{stat.value}</div>
                  <p className="text-xs text-muted-foreground">{stat.description}</p>
                </>
              )}
            </CardContent>
          </Card>
        ))}
      </div>

      {/* Note about PHI restriction */}
      <Card className="border-yellow-200 bg-yellow-50 dark:border-yellow-900 dark:bg-yellow-900/20">
        <CardHeader>
          <CardTitle className="text-yellow-800 dark:text-yellow-200">PHI Restriction Notice</CardTitle>
          <CardDescription className="text-yellow-700 dark:text-yellow-300">
            As a Platform Superadmin, you do not have access to patient data, order details, or subscription analytics. 
            This restriction is in place to maintain HIPAA compliance and protect patient privacy.
          </CardDescription>
        </CardHeader>
      </Card>
    </>
  );
}

/** Original route entry point — keeps the standalone page working unchanged. */
export default function PlatformDashboard() {
  return (
    <AdminLayout variant="platform">
      <PlatformDashboardContent />
    </AdminLayout>
  );
}
