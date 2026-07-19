import { AdminLayout } from '@/components/layouts/AdminLayout';
import { PageHeader } from '@/components/common/PageHeader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { toast } from 'sonner';
import { Activity, ArrowUpRight, Globe, Rocket, Smartphone, TabletSmartphone } from 'lucide-react';
import { useMemo, useState } from 'react';

type Platform = 'web' | 'android' | 'ios';
type DeploymentState = 'deployed' | 'in_progress' | 'queued';

interface UsageMetric {
  platform: Platform;
  label: string;
  icon: typeof Globe;
  monthlyActiveUsers: number;
  avgDailySessions: number;
  avgSessionDuration: string;
  adoptionRate: number;
  trend: string;
}

interface PlatformDeployment {
  platform: Platform;
  label: string;
  icon: typeof Globe;
  currentVersion: string;
  targetVersion: string;
  progress: number;
  status: DeploymentState;
  lastUpdated: string;
  notes: string;
}

const usageMetrics: UsageMetric[] = [
  {
    platform: 'web',
    label: 'Web',
    icon: Globe,
    monthlyActiveUsers: 14820,
    avgDailySessions: 4620,
    avgSessionDuration: '8m 14s',
    adoptionRate: 92,
    trend: '+6.1%',
  },
  {
    platform: 'android',
    label: 'Android',
    icon: Smartphone,
    monthlyActiveUsers: 9730,
    avgDailySessions: 3050,
    avgSessionDuration: '11m 02s',
    adoptionRate: 84,
    trend: '+4.7%',
  },
  {
    platform: 'ios',
    label: 'Apple (iOS/iPad)',
    icon: TabletSmartphone,
    monthlyActiveUsers: 11240,
    avgDailySessions: 3380,
    avgSessionDuration: '10m 25s',
    adoptionRate: 88,
    trend: '+5.4%',
  },
];

const initialDeployments: PlatformDeployment[] = [
  {
    platform: 'web',
    label: 'Web',
    icon: Globe,
    currentVersion: '2.8.4',
    targetVersion: '2.9.0',
    progress: 100,
    status: 'deployed',
    lastUpdated: '12 minutes ago',
    notes: 'Production stable',
  },
  {
    platform: 'android',
    label: 'Android',
    icon: Smartphone,
    currentVersion: '1.12.3',
    targetVersion: '1.13.0',
    progress: 72,
    status: 'in_progress',
    lastUpdated: '5 minutes ago',
    notes: 'Phased rollout in progress',
  },
  {
    platform: 'ios',
    label: 'Apple (iOS/iPad)',
    icon: TabletSmartphone,
    currentVersion: '3.4.1',
    targetVersion: '3.5.0',
    progress: 35,
    status: 'queued',
    lastUpdated: '1 hour ago',
    notes: 'Waiting for release window',
  },
];

const deploymentStatusStyles: Record<DeploymentState, { label: string; className: string }> = {
  deployed: {
    label: 'Deployed',
    className: 'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800',
  },
  in_progress: {
    label: 'In Progress',
    className: 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800',
  },
  queued: {
    label: 'Queued',
    className: 'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800',
  },
};

function incrementPatchVersion(version: string): string {
  const segments = version.split('.');
  const patch = Number(segments.at(-1));
  if (Number.isNaN(patch)) return version;
  segments[segments.length - 1] = String(patch + 1);
  return segments.join('.');
}

export default function TenantDeployments() {
  const [deployments, setDeployments] = useState<PlatformDeployment[]>(initialDeployments);

  const totalMonthlyUsers = useMemo(
    () => usageMetrics.reduce((acc, metric) => acc + metric.monthlyActiveUsers, 0),
    []
  );

  const handleTriggerDeployment = (platform: Platform) => {
    setDeployments((current) =>
      current.map((deployment) => {
        if (deployment.platform !== platform) {
          return deployment;
        }

        if (deployment.status === 'queued') {
          toast.success(`${deployment.label} deployment started (mock).`);
          return {
            ...deployment,
            status: 'in_progress',
            progress: 12,
            lastUpdated: 'Just now',
            notes: 'Rollout started',
          };
        }

        if (deployment.status === 'in_progress') {
          const nextProgress = Math.min(deployment.progress + 28, 100);
          if (nextProgress >= 100) {
            toast.success(`${deployment.label} deployment completed (mock).`);
            return {
              ...deployment,
              status: 'deployed',
              progress: 100,
              currentVersion: deployment.targetVersion,
              targetVersion: incrementPatchVersion(deployment.targetVersion),
              lastUpdated: 'Just now',
              notes: 'Production stable',
            };
          }

          toast.message(`${deployment.label} rollout advanced to ${nextProgress}% (mock).`);
          return {
            ...deployment,
            progress: nextProgress,
            lastUpdated: 'Just now',
            notes: 'Phased rollout in progress',
          };
        }

        const nextTarget = incrementPatchVersion(deployment.currentVersion);
        toast.success(`${deployment.label} new deployment queued (mock).`);
        return {
          ...deployment,
          status: 'queued',
          progress: 0,
          targetVersion: nextTarget,
          lastUpdated: 'Just now',
          notes: 'Ready to start',
        };
      })
    );
  };

  return (
    <AdminLayout variant="tenant">
      <PageHeader
        title="Deployments"
        description="Track and manage tenant deployment workflows"
      />

      <div className="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle className="text-lg flex items-center gap-2">
              <Activity className="h-5 w-5 text-primary" />
              Usage Metrics by Platform
            </CardTitle>
            <CardDescription>
              Operational dashboard view by platform. Values are currently mock UI data.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {usageMetrics.map((metric) => {
              const share = Math.round((metric.monthlyActiveUsers / totalMonthlyUsers) * 100);
              return (
                <Card key={metric.platform} className="border-border/60">
                  <CardHeader className="pb-3">
                    <div className="flex items-center justify-between">
                      <CardTitle className="text-base">{metric.label}</CardTitle>
                      <metric.icon className="h-4 w-4 text-muted-foreground" />
                    </div>
                    <CardDescription>
                      <span className="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                        <ArrowUpRight className="h-3.5 w-3.5" />
                        {metric.trend} this month
                      </span>
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <p className="text-xs text-muted-foreground">Monthly Active Users</p>
                        <p className="text-xl font-semibold">{metric.monthlyActiveUsers.toLocaleString()}</p>
                      </div>
                      <div>
                        <p className="text-xs text-muted-foreground">Daily Sessions</p>
                        <p className="text-xl font-semibold">{metric.avgDailySessions.toLocaleString()}</p>
                      </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <p className="text-xs text-muted-foreground">Avg Session</p>
                        <p className="text-sm font-medium">{metric.avgSessionDuration}</p>
                      </div>
                      <div>
                        <p className="text-xs text-muted-foreground">Adoption</p>
                        <p className="text-sm font-medium">{metric.adoptionRate}%</p>
                      </div>
                    </div>
                    <div className="space-y-2">
                      <div className="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Traffic Share</span>
                        <span>{share}%</span>
                      </div>
                      <Progress value={share} className="h-2" />
                    </div>
                  </CardContent>
                </Card>
              );
            })}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-lg flex items-center gap-2">
              <Rocket className="h-5 w-5 text-primary" />
              Deployment Status
            </CardTitle>
            <CardDescription>
              Current online versions and rollout progress by platform.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {deployments.map((deployment) => {
              const status = deploymentStatusStyles[deployment.status];
              const actionLabel =
                deployment.status === 'deployed'
                  ? 'Trigger New Deployment'
                  : deployment.status === 'in_progress'
                    ? 'Advance Rollout'
                    : 'Start Deployment';

              return (
                <Card key={deployment.platform} className="border-border/60">
                  <CardContent className="p-5 space-y-4">
                    <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                      <div className="space-y-1">
                        <div className="flex items-center gap-2">
                          <deployment.icon className="h-4 w-4 text-muted-foreground" />
                          <h3 className="font-semibold">{deployment.label}</h3>
                          <Badge variant="outline" className={status.className}>
                            {status.label}
                          </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">{deployment.notes}</p>
                      </div>
                      <Button
                        className="w-full md:w-auto"
                        variant={deployment.status === 'deployed' ? 'outline' : 'default'}
                        onClick={() => handleTriggerDeployment(deployment.platform)}
                      >
                        {actionLabel}
                      </Button>
                    </div>

                    <div className="grid gap-3 md:grid-cols-2">
                      <div className="rounded-md border border-border/60 bg-muted/30 px-3 py-2">
                        <p className="text-xs text-muted-foreground">Current version online</p>
                        <p className="text-sm font-medium">{deployment.currentVersion}</p>
                      </div>
                      <div className="rounded-md border border-border/60 bg-muted/30 px-3 py-2">
                        <p className="text-xs text-muted-foreground">Target version</p>
                        <p className="text-sm font-medium">{deployment.targetVersion}</p>
                      </div>
                    </div>

                    <div className="space-y-2">
                      <div className="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Rollout progress</span>
                        <span>{deployment.progress}%</span>
                      </div>
                      <Progress value={deployment.progress} className="h-2.5" />
                    </div>

                    <p className="text-xs text-muted-foreground">
                      Last updated: {deployment.lastUpdated}
                    </p>
                  </CardContent>
                </Card>
              );
            })}
          </CardContent>
        </Card>
      </div>
    </AdminLayout>
  );
}
