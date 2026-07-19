/**
 * Consolidated Platform Settings pages for the tightened IA. Each page groups
 * related concerns into in-page TABS (instead of many nav rows), reusing the
 * REAL components via category/section filters. See docs/SettingsIARedesign.md.
 */
import { PageHeader } from "@/components/common/PageHeader";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { ComingSoon } from "@/components/common/ComingSoon";
import { TenantIntegrationSettings } from "@/components/features/TenantIntegrationSettings";
import { GeneralContent } from "@/pages/tenant-admin/settings/General";
import { BrandingContent } from "@/pages/tenant-admin/settings/Branding";
import { ProductUsageTrackingContent } from "@/pages/tenant-admin/settings/ProductUsageTracking";
import { WebhooksReal } from "./WebhooksReal";
import { DomainDeploymentPage } from "./NewPages";
import { Link } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Workflow } from "lucide-react";
import { N8nConnectionPanel } from "@/components/features/comms-automations/N8nConnectionPanel";
import { SmsProviderPanel } from "@/components/features/comms-automations/SmsProviderPanel";

/* ---------------- General: Localization · Signup · Branding · Domain ------- */
export function GeneralPage() {
  return (
    <div className="max-w-4xl space-y-6">
      <PageHeader title="General" description="Tenant basics, branding and where the app lives." />
      <Tabs defaultValue="localization">
        <TabsList>
          <TabsTrigger value="localization">Localization</TabsTrigger>
          <TabsTrigger value="signup">Signup</TabsTrigger>
          <TabsTrigger value="branding">Branding</TabsTrigger>
          <TabsTrigger value="domain">Domain & Deployment</TabsTrigger>
        </TabsList>
        <TabsContent value="localization" className="mt-4">
          {/* Localization + Orders cancel-window + Allowed States */}
          <GeneralContent only={["localization"]} />
        </TabsContent>
        <TabsContent value="signup" className="mt-4">
          <GeneralContent only={["users"]} />
        </TabsContent>
        <TabsContent value="branding" className="mt-4">
          <BrandingContent />
        </TabsContent>
        <TabsContent value="domain" className="mt-4">
          {/* Apps (real) + custom-domain & deployment (Coming-soon) */}
          <DomainDeploymentPage />
        </TabsContent>
      </Tabs>
    </div>
  );
}

/* ---------------- Communications: Email · SMS · Push · Support ------------- */
export function CommunicationsPage() {
  return (
    <div className="max-w-4xl space-y-6">
      <PageHeader
        title="Communications"
        description="Outbound marketing, ops, support and in-app notifications via our built-in channels (template + Resend + Twilio). To forward events to an external service, use the outbound webhooks under Developer."
      />
      <Tabs defaultValue="email">
        <TabsList className="flex-wrap">
          <TabsTrigger value="email">Email</TabsTrigger>
          <TabsTrigger value="sms">SMS</TabsTrigger>
          <TabsTrigger value="push">Push</TabsTrigger>
          <TabsTrigger value="support">Support</TabsTrigger>
          <TabsTrigger value="automations">Automations</TabsTrigger>
          <TabsTrigger value="n8n">n8n</TabsTrigger>
        </TabsList>

        <TabsContent value="email" className="mt-4 space-y-6">
          {/* Real: Resend (email distribution) + the tenant email template/test
              (General "communication" tab). */}
          <TenantIntegrationSettings only={["email-distribution"]} />
          <GeneralContent only={["communication"]} />
        </TabsContent>

        <TabsContent value="sms" className="mt-4">
          <SmsProviderPanel />
        </TabsContent>

        <TabsContent value="push" className="mt-4">
          {/* Real: OneSignal — in-app notification channel. */}
          <TenantIntegrationSettings only={["push-notifications"]} />
        </TabsContent>

        <TabsContent value="support" className="mt-4 space-y-6">
          {/* Real: Intercom (customer support) + support content (General "support"). */}
          <TenantIntegrationSettings only={["customer-support"]} />
          <GeneralContent only={["support"]} />
        </TabsContent>

        <TabsContent value="automations" className="mt-4 space-y-4">
          <div className="rounded-lg border bg-card p-6">
            <div className="flex items-start justify-between gap-4">
              <div className="space-y-1">
                <h3 className="flex items-center gap-2 text-base font-semibold">
                  <Workflow className="h-5 w-5 text-primary" /> Communications Automations
                </h3>
                <p className="text-sm text-muted-foreground">
                  Build no-code email &amp; SMS journeys triggered by events, subscription lifecycle
                  (e.g. 3 days before renewal) and order status — with native n8n hand-off. The full
                  builder lives in the Automations workspace.
                </p>
              </div>
              <Button asChild>
                <Link to="/tenant-admin/automations">Open builder</Link>
              </Button>
            </div>
          </div>
        </TabsContent>

        <TabsContent value="n8n" className="mt-4">
          <N8nConnectionPanel />
        </TabsContent>
      </Tabs>
    </div>
  );
}

/* ---------------- Payments (Stripe) --------------------------------------- */
export function PaymentsPage() {
  return (
    <div className="max-w-4xl space-y-6">
      <PageHeader title="Payments" description="Payment provider configuration." />
      <TenantIntegrationSettings only={["payment-providers"]} />
    </div>
  );
}

/* ---------------- Order Lifecycle: Pharmacy · Shipping -------------------- */
export function OrderLifecyclePage() {
  return (
    <div className="max-w-4xl space-y-6">
      <PageHeader
        title="Order Lifecycle"
        description="Integrations that fulfill orders and emit order events (pharmacy, shipping)."
      />
      <Tabs defaultValue="pharmacy">
        <TabsList>
          <TabsTrigger value="pharmacy">Pharmacy</TabsTrigger>
          <TabsTrigger value="shipping">Shipping</TabsTrigger>
        </TabsList>
        <TabsContent value="pharmacy" className="mt-4">
          <TenantIntegrationSettings only={["pharmacy"]} />
        </TabsContent>
        <TabsContent value="shipping" className="mt-4">
          <TenantIntegrationSettings only={["shipping"]} />
        </TabsContent>
      </Tabs>
    </div>
  );
}

/* ---------------- Developer: API Keys · Webhooks · Usage Tracking --------- */
export function DeveloperPage() {
  return (
    <div className="max-w-4xl space-y-6">
      <PageHeader
        title="Developer"
        description="Programmatic access, event webhooks and product-usage tracking."
      />
      <Tabs defaultValue="webhooks">
        <TabsList>
          <TabsTrigger value="webhooks">Webhooks</TabsTrigger>
          <TabsTrigger value="api-keys">API Keys</TabsTrigger>
          <TabsTrigger value="usage">Usage Tracking</TabsTrigger>
        </TabsList>
        <TabsContent value="webhooks" className="mt-4">
          <WebhooksReal />
        </TabsContent>
        <TabsContent value="api-keys" className="mt-4">
          <ComingSoon
            title="API Keys"
            description="Programmatic access keys for external consumers of the platform API."
            bullets={["Generate named key/secret pairs (secret shown once).", "Scope, rotate and revoke; see last-used activity."]}
          />
        </TabsContent>
        <TabsContent value="usage" className="mt-4">
          <ProductUsageTrackingContent />
        </TabsContent>
      </Tabs>
    </div>
  );
}
