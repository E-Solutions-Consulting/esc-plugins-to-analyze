/**
 * The four genuinely NEW / restructured mockup pages:
 *   - Domain & Deployment  (adds the missing custom-domain config)
 *   - Providers            (adds the per-provider RTDH validation secret)
 *   - Questionnaires        (unified Patient + Medical, ordered provider -> product)
 *   - API Keys & Webhooks   (mint keys; forward selected events outbound)
 *
 * MOCKUP ONLY — static placeholder data, no persistence.
 */
import { useState } from "react";
import { PageHeader } from "@/components/common/PageHeader";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "@/components/ui/tabs";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  ArrowRight,
  CheckCircle2,
  Copy,
  Globe,
  KeyRound,
  Loader2,
  Plus,
  RefreshCw,
  Rocket,
  RotateCcw,
  ShieldCheck,
  Smartphone,
  Webhook,
} from "lucide-react";
import { useQuery } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/stores/authStore";
import { ComingSoon } from "@/components/common/ComingSoon";
import { GeneralContent } from "@/pages/tenant-admin/settings/General";
import { TenantIntegrationSettings } from "@/components/features/TenantIntegrationSettings";
import { ProductMedicalQuestionnaires } from "@/components/features/ProductMedicalQuestionnaires";
import { PatientQuestionnaires } from "@/components/features/PatientQuestionnaires";
import { NewBadge, SectionCard } from "./mockup-ui";
import { WebhooksReal } from "./WebhooksReal";

/* ------------------------------------------------------------------ */
/* Domain & Deployment                                                 */
/* ------------------------------------------------------------------ */
export function DomainDeploymentPage() {
  return (
    <div className="max-w-4xl space-y-6">
      <PageHeader
        title="Domain & Deployment"
        description="The single place to configure where this tenant's patient app lives and track its rollout."
      />

      {/* Custom domain — net-new, not built yet. */}
      <ComingSoon
        title="Custom Domain"
        description="Serve the patient app on your own hostname (e.g. app.carelink.com)."
        bullets={[
          "Set a custom domain; verify DNS and provision SSL.",
          "Falls back to the tenant *.allia subdomain until verified.",
        ]}
      />

      {/* REAL App Surfaces — the live General "Apps" tab (web base URL, iOS/Android
          store URLs, QR), now homed here. Same component/state/save as General. */}
      <GeneralContent only={["apps"]} />

      {/* Deployment promotion/rollback — net-new orchestration, not built yet. */}
      <ComingSoon
        title="Patient UI Deployment"
        description="Promote the patient app across environments and roll back from here."
        bullets={[
          "Promote Testing (staging) → Production for web + mobile.",
          "Redeploy the current version or roll back to a previous one.",
          "Version history per environment.",
        ]}
      />
    </div>
  );
}

/* Deployment control for the patient UI (CareLink): promote Testing -> Prod,
   redeploy, and roll back to a previous version. Mirrors the real branch
   promotion model (development -> staging -> main). */
const PROMOTION_ENVS = [
  {
    key: "testing",
    name: "Testing (staging)",
    branch: "staging",
    deployedAt: "today 09:42",
    state: "Deployed",
    surfaces: [
      { name: "Web", icon: Globe, version: "v2.15.0-rc.2", state: "Deployed" },
      { name: "iOS", icon: Smartphone, version: "v2.15.0-rc.2", state: "TestFlight" },
      { name: "Android", icon: Smartphone, version: "v2.15.0-rc.2", state: "Internal" },
    ],
  },
  {
    key: "production",
    name: "Production",
    branch: "main",
    deployedAt: "3 days ago",
    state: "Live",
    surfaces: [
      { name: "Web", icon: Globe, version: "v2.14.0", state: "Live" },
      { name: "iOS", icon: Smartphone, version: "v2.13.1", state: "Live" },
      { name: "Android", icon: Smartphone, version: "v2.13.1", state: "Live" },
    ],
  },
];

const VERSION_HISTORY = [
  { v: "v2.15.0-rc.2", env: "Testing", at: "today 09:42", by: "ci", current: true },
  { v: "v2.15.0-rc.1", env: "Testing", at: "yesterday", by: "ci", current: false },
  { v: "v2.14.0", env: "Production", at: "3 days ago", by: "eliano", current: true },
  { v: "v2.13.1", env: "Production", at: "2 weeks ago", by: "eliano", current: false },
  { v: "v2.13.0", env: "Production", at: "1 month ago", by: "ci", current: false },
];

function PatientUiDeployment() {
  return (
    <SectionCard
      title={
        <>
          Patient UI Deployment (CareLink) <NewBadge />
        </>
      }
      description="Promote the patient app from Testing to Production, redeploy the current version, or roll back to a previous one."
    >
      {/* Promotion pipeline */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-stretch">
        {PROMOTION_ENVS.map((env, i) => (
          <div key={env.key} className="contents">
            <div className="flex-1 rounded-lg border p-4">
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium">{env.name}</span>
                <Badge
                  variant={env.key === "production" ? "default" : "secondary"}
                  className="text-xs"
                >
                  {env.state}
                </Badge>
              </div>
              <p className="mt-1 text-xs text-muted-foreground">
                branch <code>{env.branch}</code> · {env.deployedAt}
              </p>
              {/* Patient UI spans web + mobile surfaces */}
              <div className="mt-3 space-y-1.5">
                {env.surfaces.map((s) => (
                  <div
                    key={s.name}
                    className="flex items-center justify-between gap-2 text-sm"
                  >
                    <span className="flex items-center gap-2 text-muted-foreground">
                      <s.icon className="h-3.5 w-3.5" /> {s.name}
                    </span>
                    <span className="flex items-center gap-2">
                      <span className="font-mono text-xs">{s.version}</span>
                      <Badge variant="outline" className="text-[10px]">
                        {s.state}
                      </Badge>
                    </span>
                  </div>
                ))}
              </div>
              <div className="mt-3 flex gap-2">
                <Button size="sm" variant="outline">
                  <RefreshCw className="h-3.5 w-3.5 mr-1" /> Redeploy
                </Button>
                {env.key === "testing" ? (
                  <Button size="sm">
                    <Rocket className="h-3.5 w-3.5 mr-1" /> Promote to Prod
                  </Button>
                ) : (
                  <Button size="sm" variant="outline">
                    <RotateCcw className="h-3.5 w-3.5 mr-1" /> Roll back
                  </Button>
                )}
              </div>
            </div>
            {i < PROMOTION_ENVS.length - 1 && (
              <div className="flex items-center justify-center sm:px-1">
                <ArrowRight className="h-5 w-5 text-muted-foreground rotate-90 sm:rotate-0" />
              </div>
            )}
          </div>
        ))}
      </div>

      {/* Version history with per-version redeploy / rollback */}
      <div>
        <Label className="mb-2 block">Version history</Label>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Version</TableHead>
              <TableHead>Environment</TableHead>
              <TableHead>Deployed</TableHead>
              <TableHead>By</TableHead>
              <TableHead className="w-48 text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {VERSION_HISTORY.map((row) => (
              <TableRow key={`${row.env}-${row.v}`}>
                <TableCell className="font-mono text-xs">
                  {row.v}
                  {row.current && (
                    <Badge variant="secondary" className="ml-2 text-[10px]">
                      current
                    </Badge>
                  )}
                </TableCell>
                <TableCell>{row.env}</TableCell>
                <TableCell>{row.at}</TableCell>
                <TableCell>{row.by}</TableCell>
                <TableCell className="text-right">
                  <Button variant="ghost" size="sm" disabled={row.current}>
                    <RefreshCw className="h-3.5 w-3.5 mr-1" /> Redeploy
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    disabled={row.current}
                    className="text-amber-700"
                  >
                    <RotateCcw className="h-3.5 w-3.5 mr-1" /> Roll back
                  </Button>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    </SectionCard>
  );
}

/* ------------------------------------------------------------------ */
/* Providers (with RTDH validation secret)                             */
/* ------------------------------------------------------------------ */
const PROVIDERS = [
  { key: "telegramd", name: "Telegra", enabled: true, fields: ["Affiliate username", "Affiliate password", "Base URL"] },
  { key: "md_integrations", name: "MD Integrations", enabled: true, fields: ["Client ID", "Client secret", "Backend URL"] },
  { key: "zito_care", name: "Zito Care", enabled: false, fields: ["Access token", "Base URL"] },
];

export function ProvidersPage() {
  return (
    <div className="max-w-4xl space-y-6">
      <PageHeader
        title="Providers"
        description="Configure clinical provider platforms first — Questionnaires and Products depend on them."
      />

      {PROVIDERS.map((p) => (
        <SectionCard
          key={p.key}
          title={
            <>
              {p.name}
              <Badge variant={p.enabled ? "default" : "secondary"} className="text-xs">
                {p.enabled ? "Enabled" : "Disabled"}
              </Badge>
            </>
          }
          description={`Provider key: ${p.key}`}
          actions={<Switch defaultChecked={p.enabled} />}
        >
          <div className="grid gap-4 sm:grid-cols-2">
            {p.fields.map((f) => (
              <div key={f} className="space-y-2">
                <Label>{f}</Label>
                <Input
                  type={/secret|password|token/i.test(f) ? "password" : "text"}
                  placeholder={f}
                  defaultValue={/secret|password|token/i.test(f) ? "••••••••••" : ""}
                />
              </div>
            ))}
          </div>

          {/* The genuinely new bit */}
          <div className="rounded-lg border border-emerald-300 bg-emerald-50/50 p-4 dark:bg-emerald-950/20">
            <div className="flex items-center gap-2 text-sm font-medium">
              <ShieldCheck className="h-4 w-4 text-emerald-600" />
              RTDH webhook validation secret <NewBadge />
            </div>
            <p className="mt-1 text-sm text-muted-foreground">
              Secret used to validate inbound {p.name} → RTDH webhooks. Today this lives only in
              Google GCP with no admin surface; this is where it would be owned and rotated.
            </p>
            <div className="mt-3 flex items-center gap-2">
              <Input readOnly defaultValue="whsec_•••••••••••••••••••••••••••" className="font-mono text-xs" />
              <Button variant="outline" size="sm">
                <Copy className="h-3.5 w-3.5 mr-1" /> Copy
              </Button>
              <Button variant="outline" size="sm">
                <RefreshCw className="h-3.5 w-3.5 mr-1" /> Rotate
              </Button>
            </div>
            <p className="mt-2 text-xs text-muted-foreground">
              Last verified delivery: 14 min ago · signing scheme: <code>t=,sha256=</code>
            </p>
          </div>
        </SectionCard>
      ))}
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* Questionnaires (unified Patient + Medical)                          */
/* ------------------------------------------------------------------ */


/**
 * Medical questionnaire — REAL, grouped by product. The medical questionnaire
 * depends on BOTH provider and product, so it lists each product and renders the
 * shared per-product editor (ProductMedicalQuestionnaires) — the same component
 * used on Catalog → Product → Provider Platforms. Per provider on the product:
 * Direct (native) | Jotform (new-order + renewal form IDs). Persisted to
 * product_provider_platforms.
 */
function MedicalQuestionnaires() {
  const { currentTenantId } = useAuth();
  const { data: products = [], isLoading } = useQuery({
    queryKey: ["products", "medical-questionnaires", currentTenantId],
    queryFn: async () => {
      if (!currentTenantId) return [];
      // Only products that contain at least one medication need medical
      // questionnaires (the inner join drops product-less / non-med products).
      const { data, error } = await supabase
        .from("products")
        .select("id, name, product_medications!inner(medication_id)")
        .eq("tenant_id", currentTenantId)
        .order("name", { ascending: true });
      if (error) throw error;
      // Dedupe (a product with N medications returns N rows via the join).
      const seen = new Set<string>();
      return ((data ?? []) as { id: string; name: string }[]).filter((p) => {
        if (seen.has(p.id)) return false;
        seen.add(p.id);
        return true;
      });
    },
    enabled: !!currentTenantId,
  });

  return (
    <div className="space-y-4">
      <p className="text-sm text-muted-foreground">
        The medical questionnaire depends on <strong>both</strong> the provider and
        the product. For each product, every enabled provider can run it{" "}
        <strong>Direct</strong> (the provider’s native questionnaire) or via{" "}
        <strong>Jotform</strong> (new-order + renewal form IDs). Configure providers
        and SKUs on the product itself; the questionnaire IDs are edited here.
      </p>

      {isLoading
        ? (
          <div className="flex items-center justify-center py-8">
            <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
          </div>
        )
        : products.length === 0
        ? (
          <p className="text-sm text-muted-foreground">
            No products yet. Create a product under Catalog → Products first.
          </p>
        )
        : (
          products.map((product) => (
            <SectionCard key={product.id} title={product.name}>
              <ProductMedicalQuestionnaires productId={product.id} />
            </SectionCard>
          ))
        )}
    </div>
  );
}

export function QuestionnairesPage() {
  return (
    <div className="max-w-4xl space-y-6">
      <PageHeader
        title="Questionnaires"
        description="Renamed from “Forms”. One home for both the Patient and Medical questionnaires — organized by the provider they depend on. Each can run Direct (provider-native) or via our Jotform integration."
      />

      <Tabs defaultValue="patient">
        <TabsList>
          <TabsTrigger value="connection">Connection</TabsTrigger>
          <TabsTrigger value="patient">Patient</TabsTrigger>
          <TabsTrigger value="medical">Medical</TabsTrigger>
        </TabsList>

        <TabsContent value="connection" className="mt-4">
          {/* Real Jotform connection (the "forms" integration). */}
          <TenantIntegrationSettings only={["forms"]} />
        </TabsContent>

        <TabsContent value="patient" className="mt-4">
          <PatientQuestionnaires />
        </TabsContent>

        <TabsContent value="medical" className="mt-4">
          <MedicalQuestionnaires />
        </TabsContent>
      </Tabs>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* API Keys & Webhooks                                                 */
/* ------------------------------------------------------------------ */
const EVENT_CATALOG = [
  "order.created",
  "order.paid",
  "questionnaire.submitted",
  "provider.approved",
  "prescription.shipped",
  "order.delivered",
  "subscription.renewed",
  "subscription.cancelled",
];

/** Outbound webhooks defined in the Webhooks tab — selectable when attaching to a key. */
const EXISTING_WEBHOOKS = [
  { id: "wh_n8n", label: "n8n events (hooks.n8n.io/…/allia-events)" },
  { id: "wh_attentive", label: "Attentive marketing (api.attentive.com/…)" },
];

const API_KEYS = [
  {
    id: "ak1",
    name: "n8n automation",
    prefix: "ak_live_9f2a…",
    created: "2026-05-01",
    lastUsed: "3h ago",
    webhook: "attach" as const,
    attachedId: "wh_n8n",
  },
  {
    id: "ak2",
    name: "Attentive marketing",
    prefix: "ak_live_b71c…",
    created: "2026-04-18",
    lastUsed: "1d ago",
    webhook: "none" as const,
    attachedId: "",
  },
];

type KeyWebhookMode = "none" | "attach" | "inline";

/** A single API key card with its optional outbound-webhook configuration. */
function ApiKeyCard({ apiKey }: { apiKey: (typeof API_KEYS)[number] }) {
  const [mode, setMode] = useState<KeyWebhookMode>(apiKey.webhook);
  const [events, setEvents] = useState<string[]>(["order.paid"]);
  const toggleEvent = (e: string) =>
    setEvents((s) => (s.includes(e) ? s.filter((x) => x !== e) : [...s, e]));

  return (
    <div className="rounded-lg border p-4 space-y-4">
      <div className="flex items-start justify-between gap-4">
        <div>
          <p className="text-sm font-medium">{apiKey.name}</p>
          <p className="font-mono text-xs text-muted-foreground">{apiKey.prefix}</p>
          <p className="text-xs text-muted-foreground">
            created {apiKey.created} · last used {apiKey.lastUsed}
          </p>
        </div>
        <Button variant="ghost" size="sm" className="text-destructive">
          Revoke
        </Button>
      </div>

      {/* Per-key outbound webhook */}
      <div className="rounded-md border bg-muted/30 p-3 space-y-3">
        <div className="flex items-center gap-2 text-sm font-medium">
          <Webhook className="h-4 w-4" /> Outbound webhook for this key
        </div>
        <RadioGroup
          value={mode}
          onValueChange={(v) => setMode(v as KeyWebhookMode)}
          className="gap-2"
        >
          <label className="flex items-center gap-2 text-sm">
            <RadioGroupItem value="none" /> None
          </label>
          <label className="flex items-center gap-2 text-sm">
            <RadioGroupItem value="attach" /> Attach an existing webhook
          </label>
          <label className="flex items-center gap-2 text-sm">
            <RadioGroupItem value="inline" /> Define a new webhook for this key
          </label>
        </RadioGroup>

        {mode === "attach" && (
          <div className="space-y-2">
            <Label className="text-xs">Webhook</Label>
            <Select defaultValue={apiKey.attachedId || undefined}>
              <SelectTrigger className="h-9">
                <SelectValue placeholder="Select a webhook…" />
              </SelectTrigger>
              <SelectContent>
                {EXISTING_WEBHOOKS.map((w) => (
                  <SelectItem key={w.id} value={w.id}>
                    {w.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <p className="text-xs text-muted-foreground">
              Managed in the Outbound Webhooks tab.
            </p>
          </div>
        )}

        {mode === "inline" && (
          <div className="space-y-3">
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label className="text-xs">Endpoint URL</Label>
                <Input className="h-9" placeholder="https://hooks.example.com/…" />
              </div>
              <div className="space-y-1.5">
                <Label className="text-xs">Signing secret</Label>
                <Input className="h-9" type="password" defaultValue="••••••••••" />
              </div>
            </div>
            <div>
              <Label className="text-xs mb-1.5 block">Events to forward</Label>
              <div className="grid gap-2 sm:grid-cols-2">
                {EVENT_CATALOG.map((e) => (
                  <label key={e} className="flex items-center gap-2 rounded-md border bg-background p-2 text-sm">
                    <Checkbox checked={events.includes(e)} onCheckedChange={() => toggleEvent(e)} />
                    <span className="font-mono text-xs">{e}</span>
                  </label>
                ))}
              </div>
            </div>
            <p className="text-xs text-muted-foreground">
              This standalone webhook will also appear in the Outbound Webhooks tab.
            </p>
          </div>
        )}
      </div>
    </div>
  );
}

function ApiKeys() {
  return (
    <SectionCard
      title={
        <>
          API Keys <NewBadge />
        </>
      }
      description="Named key/secret pairs for external consumers of our API. The secret is shown once at creation. Each key can also forward selected events via its own outbound webhook — attach an existing one or define a new standalone one inline."
      actions={
        <Button size="sm">
          <Plus className="h-4 w-4 mr-1" /> New key
        </Button>
      }
    >
      {API_KEYS.map((k) => (
        <ApiKeyCard key={k.id} apiKey={k} />
      ))}
    </SectionCard>
  );
}

export function ApiKeysWebhooksPage() {
  return (
    <div className="max-w-4xl space-y-6">
      <PageHeader
        title="API Keys & Webhooks"
        description="Connect external engines (n8n, Attentive, automation) to platform events and data."
      />

      <Tabs defaultValue="webhooks">
        <TabsList>
          <TabsTrigger value="webhooks">
            <Webhook className="h-4 w-4 mr-1.5" /> Outbound Webhooks
          </TabsTrigger>
          <TabsTrigger value="keys">
            <KeyRound className="h-4 w-4 mr-1.5" /> API Keys
          </TabsTrigger>
          <TabsTrigger value="data-api">
            <KeyRound className="h-4 w-4 mr-1.5" /> Data API
          </TabsTrigger>
        </TabsList>

        {/* Webhooks = real, working feature */}
        <TabsContent value="webhooks" className="mt-4">
          <WebhooksReal />
        </TabsContent>

        {/* API Keys = coming soon */}
        <TabsContent value="keys" className="mt-4">
          <ComingSoon
            title="API Keys"
            description="Programmatic access keys for external consumers of the platform API."
            bullets={[
              "Generate named key/secret pairs (secret shown once).",
              "Scope keys to specific capabilities.",
              "Rotate and revoke; see last-used activity.",
            ]}
          />
        </TabsContent>

        {/* Data API = coming soon */}
        <TabsContent value="data-api" className="mt-4">
          <ComingSoon
            title="Data API"
            description="Read access to your tenant's data for reporting and integrations."
            bullets={[
              "Query orders, subscriptions and usage via a stable API.",
              "Token-authenticated, tenant-scoped, read-only.",
              "Pairs with API Keys for authentication.",
            ]}
          />
        </TabsContent>
      </Tabs>
    </div>
  );
}
