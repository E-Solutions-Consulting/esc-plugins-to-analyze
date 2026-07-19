/**
 * Catalog mockup pages for the proposed IA — Products and Medications.
 *
 * These restore the Product/Medication settings in the redesigned structure and,
 * importantly, surface the per-STATE + per-PRODUCT provider routing
 * (product_provider_platform_load_balancing_rule_sets / _allocations):
 * a default allocation plus per-state overrides, each splitting traffic across
 * the product's enabled providers by percentage.
 *
 * Note on the redesign: the medical-questionnaire Jotform IDs that today live on
 * the Product's Provider Platforms tab MOVE to Settings → Questionnaires. What
 * stays on the Product is provider enablement, SKUs, and per-state routing.
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
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
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
import { ExternalLink, MapPin, Pencil, Plus, Trash2 } from "lucide-react";
import { Link } from "react-router-dom";
import { MockImageUpload, SectionCard } from "./mockup-ui";
import { SETTINGS_V2_BASE } from "./SettingsV2Layout";
import { MedicationsContent } from "@/pages/tenant-admin/catalog/Medications";

/* ------------------------------------------------------------------ */
/* Products list                                                       */
/* ------------------------------------------------------------------ */
const PRODUCTS = [
  { sku: "SEMA-MO", name: "Semaglutide (monthly)", type: "Subscription", price: "$299/mo", enabled: true, providers: 2 },
  { sku: "TIRZ-MO", name: "Tirzepatide (monthly)", type: "Subscription", price: "$499/mo", enabled: true, providers: 1 },
  { sku: "NAD-OT", name: "NAD+ booster", type: "One-time", price: "$149", enabled: false, providers: 1 },
];

export function ProductsPage() {
  return (
    <div className="max-w-4xl space-y-6">
      <PageHeader
        title="Products"
        description="Sellable products: pricing, linked medications, providers and per-state routing."
        actions={
          <Button size="sm">
            <Plus className="h-4 w-4 mr-1" /> New product
          </Button>
        }
      />
      <SectionCard title="All products">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Product</TableHead>
              <TableHead>SKU</TableHead>
              <TableHead>Type</TableHead>
              <TableHead>Price</TableHead>
              <TableHead>Providers</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className="w-20" />
            </TableRow>
          </TableHeader>
          <TableBody>
            {PRODUCTS.map((p) => (
              <TableRow key={p.sku}>
                <TableCell className="font-medium">{p.name}</TableCell>
                <TableCell className="font-mono text-xs">{p.sku}</TableCell>
                <TableCell>{p.type}</TableCell>
                <TableCell>{p.price}</TableCell>
                <TableCell>{p.providers}</TableCell>
                <TableCell>
                  <Badge variant={p.enabled ? "default" : "secondary"} className="text-xs">
                    {p.enabled ? "Enabled" : "Disabled"}
                  </Badge>
                </TableCell>
                <TableCell>
                  <Button asChild variant="ghost" size="sm">
                    <Link to={`${SETTINGS_V2_BASE}/products/detail`}>Open</Link>
                  </Button>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </SectionCard>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* Product detail (with per-state provider routing)                    */
/* ------------------------------------------------------------------ */
const ENABLED_PROVIDERS = ["Telegra", "MD Integrations"];

interface RuleSet {
  id: string;
  label: string;
  states: string[];
  isDefault: boolean;
  allocations: Record<string, number>;
}

const INITIAL_RULES: RuleSet[] = [
  {
    id: "default",
    label: "Default",
    states: [],
    isDefault: true,
    allocations: { Telegra: 50, "MD Integrations": 50 },
  },
  {
    id: "ca",
    label: "California",
    states: ["CA"],
    isDefault: false,
    allocations: { Telegra: 80, "MD Integrations": 20 },
  },
  {
    id: "ny-fl",
    label: "New York, Florida",
    states: ["NY", "FL"],
    isDefault: false,
    allocations: { Telegra: 30, "MD Integrations": 70 },
  },
];

export function ProductDetailPage() {
  return (
    <div className="max-w-4xl space-y-6">
      <PageHeader
        title="Semaglutide (monthly)"
        description="SKU SEMA-MO · Subscription · $299/mo"
        backUrl={`${SETTINGS_V2_BASE}/products`}
      />

      <Tabs defaultValue="routing">
        <TabsList>
          <TabsTrigger value="details">Details</TabsTrigger>
          <TabsTrigger value="medications">Medications</TabsTrigger>
          <TabsTrigger value="faqs">FAQs</TabsTrigger>
          <TabsTrigger value="providers">Providers</TabsTrigger>
          <TabsTrigger value="routing">State Routing</TabsTrigger>
          <TabsTrigger value="payment">Payment</TabsTrigger>
        </TabsList>

        <TabsContent value="details" className="mt-4">
          <SectionCard title="Details">
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label>Name</Label>
                <Input defaultValue="Semaglutide (monthly)" />
              </div>
              <div className="space-y-2">
                <Label>SKU</Label>
                <Input defaultValue="SEMA-MO" />
              </div>
            </div>
            <div className="space-y-2">
              <Label>Description</Label>
              <Textarea
                className="min-h-28"
                defaultValue="A monthly GLP-1 program for eligible patients, including compounded semaglutide medication and ongoing care team support."
              />
            </div>
            <MockImageUpload
              label="Product image"
              description="Shown in patient-facing checkout and product catalog surfaces."
              previewClassName="h-40 w-40"
            />
            <div className="flex items-center gap-2">
              <Switch defaultChecked />
              <span className="text-sm">Enabled</span>
            </div>
          </SectionCard>
        </TabsContent>

        <TabsContent value="medications" className="mt-4">
          <SectionCard
            title="Linked medications"
            actions={
              <Button variant="outline" size="sm">
                <Plus className="h-4 w-4 mr-1" /> Add medication
              </Button>
            }
          >
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Medication</TableHead>
                  <TableHead>Quantity</TableHead>
                  <TableHead>Instructions</TableHead>
                  <TableHead className="w-20" />
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow>
                  <TableCell className="font-medium">Semaglutide injectable</TableCell>
                  <TableCell>1</TableCell>
                  <TableCell className="text-muted-foreground">Weekly subcutaneous</TableCell>
                  <TableCell>
                    <Button variant="ghost" size="sm" className="text-destructive">
                      Delete
                    </Button>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </SectionCard>
        </TabsContent>

        <TabsContent value="faqs" className="mt-4">
          <SectionCard
            title="Product FAQs"
            description="Frequently asked questions shown in patient-facing product APIs."
            actions={
              <Button variant="outline" size="sm">
                <Plus className="h-4 w-4 mr-1" /> Add FAQ
              </Button>
            }
          >
            <div className="grid gap-4 sm:grid-cols-[1fr_120px]">
              <div className="space-y-2">
                <Label>Question</Label>
                <Input defaultValue="How quickly will my medication ship?" />
              </div>
              <div className="space-y-2">
                <Label>Display order</Label>
                <Input type="number" defaultValue="1" />
              </div>
              <div className="space-y-2 sm:col-span-2">
                <Label>Answer</Label>
                <Textarea
                  className="min-h-24"
                  defaultValue="After clinical approval, orders are routed to the configured provider platform and fulfillment timing depends on the selected pharmacy and patient state."
                />
              </div>
            </div>

            <div className="space-y-2">
              {[
                {
                  order: 1,
                  question: "How quickly will my medication ship?",
                  answer: "After clinical approval, orders are routed to the configured provider platform and fulfillment timing depends on the selected pharmacy and patient state.",
                },
                {
                  order: 2,
                  question: "Can patients pause or cancel?",
                  answer: "Subscription changes are handled according to the tenant's product terms and support workflow.",
                },
              ].map((faq) => (
                <div key={faq.order} className="rounded-md border bg-muted/10 p-3 space-y-2">
                  <div className="flex items-center justify-between gap-3">
                    <span className="text-xs text-muted-foreground">Display order: {faq.order}</span>
                    <div className="flex items-center gap-1">
                      <Button type="button" variant="ghost" size="sm">
                        <Pencil className="h-4 w-4" />
                        <span className="sr-only">Edit FAQ</span>
                      </Button>
                      <Button type="button" variant="ghost" size="sm" className="text-destructive">
                        <Trash2 className="h-4 w-4" />
                        <span className="sr-only">Delete FAQ</span>
                      </Button>
                    </div>
                  </div>
                  <p className="font-medium text-sm">{faq.question}</p>
                  <p className="text-sm text-muted-foreground whitespace-pre-wrap">{faq.answer}</p>
                </div>
              ))}
            </div>
          </SectionCard>
        </TabsContent>

        <TabsContent value="providers" className="mt-4 space-y-4">
          <SectionCard
            title="Provider platforms"
            description="Which providers can fulfill this product, plus their SKUs."
          >
            {ENABLED_PROVIDERS.map((name) => (
              <div key={name} className="rounded-lg border p-3 space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">{name}</span>
                  <Switch defaultChecked />
                </div>
                <div className="grid gap-3 sm:grid-cols-2">
                  <div className="space-y-1.5">
                    <Label className="text-xs">Provider product SKU</Label>
                    <Input className="h-8 text-xs" defaultValue={name === "Telegra" ? "pvt::sema-mo" : "mdi-sema-001"} />
                  </div>
                  <div className="space-y-1.5">
                    <Label className="text-xs">Variation SKU</Label>
                    <Input className="h-8 text-xs" placeholder="optional" />
                  </div>
                </div>
              </div>
            ))}
            <div className="rounded-md border border-dashed p-3 text-sm text-muted-foreground flex items-center gap-2">
              <ExternalLink className="h-4 w-4" />
              Medical questionnaire form IDs now live in{" "}
              <Link to={`${SETTINGS_V2_BASE}/questionnaires`} className="underline">
                Settings → Questionnaires
              </Link>
              .
            </div>
          </SectionCard>
        </TabsContent>

        <TabsContent value="routing" className="mt-4">
          <StateRouting />
        </TabsContent>

        <TabsContent value="payment" className="mt-4 space-y-4">
          <SectionCard
            title="Pricing & billing"
            description="Mirrors the current product payment fields."
          >
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label>Price (USD)</Label>
                <Input defaultValue="299.00" />
              </div>
              <div className="space-y-2">
                <Label>Payment type</Label>
                <Input defaultValue="Subscription" />
              </div>
              <div className="space-y-2">
                <Label>Subscription interval</Label>
                <Input defaultValue="month" />
              </div>
              <div className="space-y-2">
                <Label>Interval count</Label>
                <Input defaultValue="1" />
              </div>
              <div className="space-y-2">
                <Label>Renewal lead days</Label>
                <Input defaultValue="3" />
              </div>
            </div>
          </SectionCard>

          <SectionCard
            title="Payment providers"
            description="Which configured payment providers can charge for this product."
          >
            <div className="rounded-lg border p-3 space-y-3">
              <div className="flex items-center justify-between">
                <span className="flex items-center gap-2 text-sm font-medium">
                  Stripe
                  <Badge variant="outline" className="text-[10px]">stripe</Badge>
                </span>
                <Switch defaultChecked />
              </div>
              {/* Stripe sub-config: coupons / promo codes, as today */}
              <div className="border-t pt-3 space-y-2">
                <div className="flex items-center justify-between">
                  <Label className="text-xs">Allow promotion codes at checkout</Label>
                  <Switch defaultChecked />
                </div>
                <Label className="text-xs">Coupons</Label>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Code</TableHead>
                      <TableHead>Discount</TableHead>
                      <TableHead>Duration</TableHead>
                      <TableHead className="w-16" />
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow>
                      <TableCell className="font-mono text-xs">WELCOME20</TableCell>
                      <TableCell>20% off</TableCell>
                      <TableCell>3 months</TableCell>
                      <TableCell>
                        <Button variant="ghost" size="sm" className="text-destructive">
                          Remove
                        </Button>
                      </TableCell>
                    </TableRow>
                    <TableRow>
                      <TableCell className="font-mono text-xs">FIRSTMONTH</TableCell>
                      <TableCell>$50 off</TableCell>
                      <TableCell>once</TableCell>
                      <TableCell>
                        <Button variant="ghost" size="sm" className="text-destructive">
                          Remove
                        </Button>
                      </TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
                <Button variant="outline" size="sm">
                  <Plus className="h-4 w-4 mr-1" /> Add coupon
                </Button>
              </div>
            </div>
          </SectionCard>
        </TabsContent>
      </Tabs>
    </div>
  );
}

function allocationTotal(a: Record<string, number>) {
  return Object.values(a).reduce((s, n) => s + (Number(n) || 0), 0);
}

function StateRouting() {
  const [rules] = useState(INITIAL_RULES);
  return (
    <SectionCard
      title={
        <span className="flex items-center gap-2">
          <MapPin className="h-4 w-4" /> Provider routing by state
        </span>
      }
      description="Decide which provider fulfills an order based on the patient's state. The Default rule applies to any state without an override. Allocations split traffic across providers and must total 100%."
    >
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead className="w-44">Rule / States</TableHead>
            {ENABLED_PROVIDERS.map((p) => (
              <TableHead key={p}>{p} %</TableHead>
            ))}
            <TableHead className="w-24">Total</TableHead>
            <TableHead className="w-16" />
          </TableRow>
        </TableHeader>
        <TableBody>
          {rules.map((r) => {
            const total = allocationTotal(r.allocations);
            return (
              <TableRow key={r.id}>
                <TableCell>
                  {r.isDefault ? (
                    <Badge variant="secondary" className="text-xs">
                      Default (all other states)
                    </Badge>
                  ) : (
                    <div className="flex flex-wrap gap-1">
                      {r.states.map((s) => (
                        <Badge key={s} variant="outline" className="text-xs">
                          {s}
                        </Badge>
                      ))}
                    </div>
                  )}
                </TableCell>
                {ENABLED_PROVIDERS.map((p) => (
                  <TableCell key={p}>
                    <Input
                      type="number"
                      className="h-8 w-20 text-xs"
                      defaultValue={r.allocations[p] ?? 0}
                    />
                  </TableCell>
                ))}
                <TableCell>
                  <Badge variant={total === 100 ? "default" : "destructive"} className="text-xs">
                    {total}%
                  </Badge>
                </TableCell>
                <TableCell>
                  {!r.isDefault && (
                    <Button variant="ghost" size="sm" className="text-destructive">
                      Remove
                    </Button>
                  )}
                </TableCell>
              </TableRow>
            );
          })}
        </TableBody>
      </Table>
      <Button variant="outline" size="sm">
        <Plus className="h-4 w-4 mr-1" /> Add state override
      </Button>
    </SectionCard>
  );
}

/* ------------------------------------------------------------------ */
/* Medications                                                         */
/* ------------------------------------------------------------------ */

/** Real medications list (reuses the catalog MedicationsContent — same data,
 *  create dialog, and navigation into the real medication detail editor). */
export function MedicationsPage() {
  return (
    <div className="max-w-5xl space-y-6">
      <MedicationsContent />
    </div>
  );
}

export function MedicationDetailPage() {
  return <MedicationEditorPage mode="edit" />;
}

export function NewMedicationPage() {
  return <MedicationEditorPage mode="new" />;
}

const MEDICATION_CAPABILITIES = [
  { id: "weight", name: "Weight Tracker", description: "Allows patients to log body weight." },
  { id: "body-measurement", name: "Body Measurement", description: "Allows patients to log waist, hip, and related measurements." },
  { id: "shot-counter", name: "Shot Counter", description: "Enables injection tracking for injectable medications." },
  { id: "pill-counter", name: "Pill Counter", description: "Enables pill intake tracking for tablet medications." },
  { id: "energy", name: "Energy Tracker", description: "Allows patients to log energy levels." },
  { id: "mood", name: "Mood Tracker", description: "Allows patients to log mood check-ins." },
  { id: "symptoms", name: "Symptoms Tracker", description: "Allows patients to report symptoms." },
];

function MedicationEditorPage({ mode }: { mode: "edit" | "new" }) {
  const isNew = mode === "new";
  const [selectedCapabilities, setSelectedCapabilities] = useState(
    new Set(["weight", "body-measurement", "shot-counter", "mood", "symptoms"]),
  );

  const toggleCapability = (id: string) => {
    setSelectedCapabilities((current) => {
      const next = new Set(current);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  return (
    <div className="max-w-4xl space-y-6">
      <PageHeader
        title={isNew ? "New medication" : "Semaglutide injectable"}
        description={isNew ? "Create a medication and configure patient-tracking capabilities." : "Injectable solution · Weight loss"}
        backUrl={`${SETTINGS_V2_BASE}/medications`}
      />
      <SectionCard title="Details">
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-2">
            <Label>Name</Label>
            <Input defaultValue={isNew ? "" : "Semaglutide injectable"} placeholder="Medication name" />
          </div>
          <div className="space-y-2">
            <Label>Form</Label>
            <Input defaultValue={isNew ? "" : "Injectable solution"} placeholder="Medication form" />
          </div>
          <div className="space-y-2 sm:col-span-2">
            <Label>Description</Label>
            <Textarea
              defaultValue={isNew ? "" : "Compounded semaglutide medication used in eligible weight-management programs."}
              placeholder="Patient-facing medication description"
            />
          </div>
          <div className="sm:col-span-2">
            <MockImageUpload
              label="Medication image"
              description="Shown wherever medication details need a patient-facing image."
              previewClassName="h-40 w-40"
            />
          </div>
        </div>
      </SectionCard>
      <SectionCard
        title="Medication capabilities"
        description="Choose which patient-tracking modules are available for this medication."
      >
        <div className="flex flex-wrap gap-1">
          {MEDICATION_CAPABILITIES.filter((capability) =>
            selectedCapabilities.has(capability.id),
          ).map((capability) => (
            <Badge key={capability.id} variant="outline" className="text-xs">
              {capability.name}
            </Badge>
          ))}
        </div>
        <div className="grid gap-3 sm:grid-cols-2">
          {MEDICATION_CAPABILITIES.map((capability) => (
            <label
              key={capability.id}
              className="flex cursor-pointer items-start gap-3 rounded-md border p-3"
            >
              <Checkbox
                checked={selectedCapabilities.has(capability.id)}
                onCheckedChange={() => toggleCapability(capability.id)}
              />
              <span className="space-y-1">
                <span className="block text-sm font-medium">{capability.name}</span>
                <span className="block text-xs text-muted-foreground">
                  {capability.description}
                </span>
              </span>
            </label>
          ))}
        </div>
      </SectionCard>
      <SectionCard
        title="Provider identifiers"
        description="Medication-level identifiers used by provider platforms."
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-2">
            <Label>MDI Offering ID</Label>
            <Input
              className="font-mono text-xs"
              defaultValue="mdi-sema-offering-001"
              placeholder="Enter the MDI Offering ID"
            />
            <p className="text-xs text-muted-foreground">
              MD Integrations uses this medication-level value when creating case offerings.
            </p>
          </div>
        </div>
      </SectionCard>
    </div>
  );
}
