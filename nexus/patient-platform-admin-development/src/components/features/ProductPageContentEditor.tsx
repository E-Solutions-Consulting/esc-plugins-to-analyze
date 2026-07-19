import { GalleryImagesEditor } from "@/components/features/GalleryImagesEditor";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { ImageUpload } from "@/components/common/ImageUpload";
import {
  ArrowDown,
  ArrowUp,
  FileText,
  Image as ImageIcon,
  Loader2,
  Plus,
  Save,
  Trash2,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";

/* ------------------------------- draft model ------------------------------- */

/**
 * A local, always-defined mirror of ProductPdpContent so the form never has to
 * juggle `undefined` fields while editing. Empty strings and empty arrays here
 * are pruned back to absent on save (see toPdp), matching what the import script
 * writes — we never persist `{"about": {}}` or empty keys.
 */
interface PdpDraft {
  badge: string;
  subBadge: string;
  shortDesc: string;
  description: string;
  images: string[];
  about: {
    heading: string;
    image: string;
    paragraphs: ProductPdpParagraph[];
    note: string;
    benefitsHeading: string;
    benefits: ProductPdpBenefit[];
    citations: string[];
  };
  includes: string[];
  steps: ProductPdpStep[];
}

const emptyDraft = (): PdpDraft => ({
  badge: "",
  subBadge: "",
  shortDesc: "",
  description: "",
  images: [],
  about: {
    heading: "",
    image: "",
    paragraphs: [],
    note: "",
    benefitsHeading: "",
    benefits: [],
    citations: [],
  },
  includes: [],
  steps: [],
});

const toDraft = (pdp: ProductPdpContent | null | undefined): PdpDraft => {
  const base = emptyDraft();
  if (!pdp) return base;
  return {
    badge: pdp.badge ?? "",
    subBadge: pdp.subBadge ?? "",
    shortDesc: pdp.shortDesc ?? "",
    description: pdp.description ?? "",
    images: [...(pdp.images ?? [])],
    about: {
      heading: pdp.about?.heading ?? "",
      image: pdp.about?.image ?? "",
      paragraphs: (pdp.about?.paragraphs ?? []).map((p) => ({ ...p })),
      note: pdp.about?.note ?? "",
      benefitsHeading: pdp.about?.benefitsHeading ?? "",
      benefits: (pdp.about?.benefits ?? []).map((b) => ({ ...b })),
      citations: [...(pdp.about?.citations ?? [])],
    },
    includes: [...(pdp.includes ?? [])],
    steps: (pdp.steps ?? []).map((s) => ({ ...s })),
  };
};

const str = (s: string) => {
  const t = s.trim();
  return t === "" ? undefined : t;
};

const arr = <T,>(a: T[]): T[] | undefined => (a.length ? a : undefined);

/** Draft → ProductPdpContent, dropping every empty field (mirrors the import
 *  script's prune, so hand-editing writes the same shape the importer does). */
const toPdp = (d: PdpDraft): ProductPdpContent => {
  const paragraphs = d.about.paragraphs
    .map((p) => {
      const text = str(p.text);
      if (!text) return undefined;
      return { text, ...(p.citation ? { citation: p.citation } : {}) };
    })
    .filter(Boolean) as ProductPdpParagraph[];

  const benefits = d.about.benefits
    .map((b) => {
      const lead = str(b.lead);
      if (!lead) return undefined;
      return {
        lead,
        ...(str(b.rest ?? "") ? { rest: str(b.rest ?? "") } : {}),
        ...(b.citation ? { citation: b.citation } : {}),
      };
    })
    .filter(Boolean) as ProductPdpBenefit[];

  const citations = d.about.citations.map((c) => c.trim()).filter(Boolean);
  const images = d.images.map((i) => i.trim()).filter(Boolean);
  const includes = d.includes.map((i) => i.trim()).filter(Boolean);
  const steps = d.steps
    .map((s) => {
      const title = str(s.title ?? "");
      const description = str(s.description ?? "");
      const image = str(s.image ?? "");
      if (!title && !description && !image) return undefined;
      return {
        ...(title ? { title } : {}),
        ...(description ? { description } : {}),
        ...(image ? { image } : {}),
      };
    })
    .filter(Boolean) as ProductPdpStep[];

  const about = {
    heading: str(d.about.heading),
    image: str(d.about.image),
    paragraphs: arr(paragraphs),
    note: str(d.about.note),
    benefitsHeading: str(d.about.benefitsHeading),
    benefits: arr(benefits),
    citations: arr(citations),
  };
  const hasAbout = Object.values(about).some((v) => v !== undefined);

  return {
    badge: str(d.badge),
    subBadge: str(d.subBadge),
    shortDesc: str(d.shortDesc),
    description: str(d.description),
    images: arr(images),
    about: hasAbout ? about : undefined,
    includes: arr(includes),
    steps: arr(steps),
  };
};

/* --------------------------------- props ---------------------------------- */

interface ProductPageContentEditorProps {
  /** Current content, read from products.metadata.pdp. */
  value: ProductPdpContent | null | undefined;
  /** Tenant id — the folder uploads land in, matching product images. */
  tenantId: string;
  /** Persist the edited content. Resolves when the write completes. */
  onSave: (pdp: ProductPdpContent) => Promise<void> | void;
  isSaving?: boolean;
  readOnly?: boolean;
}

/**
 * Edit `products.metadata.pdp` — the editorial content of the patient-facing
 * product page: gallery, badges, the "About" block (heading, image, paragraphs
 * with citations, benefits, references), page-specific inclusions and the
 * path-to-care steps.
 *
 * View/Edit like the Product Information card: read-only until Edit, a single
 * Save at the top that commits the whole block. On save the draft is pruned so
 * the persisted JSON matches what the one-off importer writes.
 */
export function ProductPageContentEditor({
  value,
  tenantId,
  onSave,
  isSaving,
  readOnly,
}: ProductPageContentEditorProps) {
  const [isEditing, setIsEditing] = useState(false);
  const [draft, setDraft] = useState<PdpDraft>(() => toDraft(value));

  // Re-seed the draft whenever the saved value changes and we're not mid-edit,
  // so an external refetch is reflected without clobbering in-progress edits.
  useEffect(() => {
    if (!isEditing) setDraft(toDraft(value));
  }, [value, isEditing]);

  const isEmpty = useMemo(() => {
    const p = toPdp(toDraft(value));
    return Object.values(p).every((v) => v === undefined);
  }, [value]);

  const patch = (next: Partial<PdpDraft>) =>
    setDraft((d) => ({ ...d, ...next }));
  const patchAbout = (next: Partial<PdpDraft["about"]>) =>
    setDraft((d) => ({ ...d, about: { ...d.about, ...next } }));

  const handleCancel = () => {
    setDraft(toDraft(value));
    setIsEditing(false);
  };

  const handleSave = async () => {
    await onSave(toPdp(draft));
    setIsEditing(false);
  };

  /* --------------------------- list helpers --------------------------- */

  const moveIn = <T,>(list: T[], index: number, delta: number): T[] => {
    const target = index + delta;
    if (target < 0 || target >= list.length) return list;
    const copy = [...list];
    [copy[index], copy[target]] = [copy[target], copy[index]];
    return copy;
  };

  return (
    <Card>
      <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <CardTitle className="flex items-center gap-2 text-lg">
            <FileText className="h-5 w-5 text-primary" />
            Page Content
          </CardTitle>
          <CardDescription>
            The editorial content of the patient-facing product page — gallery,
            badges, the &ldquo;About&rdquo; section and its citations, page
            inclusions, and the path-to-care steps. Stored on the product; read
            by the patient app and, later, the marketing site.
          </CardDescription>
        </div>
        {!isEditing ? (
          !readOnly && (
            <Button variant="outline" onClick={() => setIsEditing(true)}>
              Edit
            </Button>
          )
        ) : (
          <div className="flex gap-2">
            <Button variant="outline" onClick={handleCancel}>
              Cancel
            </Button>
            <Button onClick={handleSave} disabled={isSaving}>
              {isSaving && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              <Save className="mr-2 h-4 w-4" />
              Save
            </Button>
          </div>
        )}
      </CardHeader>

      <CardContent className="space-y-8">
        {!isEditing && isEmpty ? (
          <p className="text-sm text-muted-foreground">
            No page content yet. Click Edit to add a gallery, an About section
            and the rest of the product page copy.
          </p>
        ) : isEditing ? (
          <>
            {/* --------------------------- Header / text -------------------------- */}
            <section className="space-y-4">
              <SectionTitle
                title="Header"
                hint="Badges and the short copy shown at the top of the page."
              />
              <div className="grid gap-4 md:grid-cols-2">
                <Field label="Badge" hint='Small pill, e.g. "Best Seller".'>
                  <Input
                    value={draft.badge}
                    maxLength={40}
                    onChange={(e) => patch({ badge: e.target.value })}
                  />
                </Field>
                <Field label="Secondary badge">
                  <Input
                    value={draft.subBadge}
                    maxLength={40}
                    onChange={(e) => patch({ subBadge: e.target.value })}
                  />
                </Field>
              </div>
              <Field
                label="Short description"
                hint="One-line teaser under the title."
              >
                <Textarea
                  value={draft.shortDesc}
                  rows={2}
                  maxLength={300}
                  onChange={(e) => patch({ shortDesc: e.target.value })}
                />
              </Field>
              <Field
                label="Description"
                hint='Longer "learn more" lede paragraph.'
              >
                <Textarea
                  value={draft.description}
                  rows={3}
                  maxLength={800}
                  onChange={(e) => patch({ description: e.target.value })}
                />
              </Field>
            </section>

            {/* ------------------------------ Gallery ----------------------------- */}
            <section className="space-y-3 border-t pt-6">
              <SectionTitle
                title="Gallery"
                hint="Product images. Upload files or paste URLs; the first is the hero."
              />
              <GalleryImagesEditor
                value={draft.images}
                folder={tenantId}
                onChange={(images) => patch({ images })}
              />
            </section>

            {/* ------------------------------- About ------------------------------ */}
            <section className="space-y-4 border-t pt-6">
              <SectionTitle
                title="About"
                hint='The "What is …?" explainer: body, benefits and the references behind them.'
              />
              <div className="grid gap-4 md:grid-cols-2">
                <Field label="Heading" hint='e.g. "What is Tirzepatide?"'>
                  <Input
                    value={draft.about.heading}
                    maxLength={120}
                    onChange={(e) => patchAbout({ heading: e.target.value })}
                  />
                </Field>
                <Field label="Illustration" hint="Image beside the body.">
                  <ImageUpload
                    bucket="product-images"
                    folder={tenantId}
                    value={draft.about.image || null}
                    onChange={(url) => patchAbout({ image: url ?? "" })}
                  />
                </Field>
              </div>

              {/* Paragraphs */}
              <div className="space-y-2">
                <Label>Paragraphs</Label>
                <p className="text-xs text-muted-foreground">
                  Body text. A citation number points at the matching entry in
                  the References list below.
                </p>
                {draft.about.paragraphs.map((p, index) => (
                  <div
                    key={index}
                    className="flex items-start gap-2 rounded-md border bg-muted/10 p-2"
                  >
                    <Textarea
                      value={p.text}
                      rows={2}
                      placeholder="Paragraph text"
                      className="flex-1"
                      onChange={(e) => {
                        const copy = [...draft.about.paragraphs];
                        copy[index] = { ...copy[index], text: e.target.value };
                        patchAbout({ paragraphs: copy });
                      }}
                    />
                    <div className="w-20 shrink-0">
                      <Input
                        type="number"
                        min={1}
                        placeholder="Cite #"
                        value={p.citation ?? ""}
                        onChange={(e) => {
                          const copy = [...draft.about.paragraphs];
                          const n = parseInt(e.target.value, 10);
                          copy[index] = {
                            ...copy[index],
                            citation: Number.isFinite(n) ? n : undefined,
                          };
                          patchAbout({ paragraphs: copy });
                        }}
                      />
                    </div>
                    <RowControls
                      onUp={() =>
                        patchAbout({
                          paragraphs: moveIn(draft.about.paragraphs, index, -1),
                        })
                      }
                      onDown={() =>
                        patchAbout({
                          paragraphs: moveIn(draft.about.paragraphs, index, 1),
                        })
                      }
                      onRemove={() =>
                        patchAbout({
                          paragraphs: draft.about.paragraphs.filter(
                            (_, i) => i !== index,
                          ),
                        })
                      }
                      isFirst={index === 0}
                      isLast={index === draft.about.paragraphs.length - 1}
                    />
                  </div>
                ))}
                <AddButton
                  label="Add paragraph"
                  onClick={() =>
                    patchAbout({
                      paragraphs: [...draft.about.paragraphs, { text: "" }],
                    })
                  }
                />
              </div>

              <Field
                label="Note"
                hint="Callout under the paragraphs, e.g. which medication the plan includes."
              >
                <Textarea
                  value={draft.about.note}
                  rows={2}
                  onChange={(e) => patchAbout({ note: e.target.value })}
                />
              </Field>

              {/* Benefits */}
              <Field
                label="Benefits heading"
                hint='e.g. "Studies examine GLP-1&rsquo;s potential role in:"'
              >
                <Input
                  value={draft.about.benefitsHeading}
                  onChange={(e) =>
                    patchAbout({ benefitsHeading: e.target.value })
                  }
                />
              </Field>
              <div className="space-y-2">
                <Label>Benefits</Label>
                <p className="text-xs text-muted-foreground">
                  Each benefit is a bold lead-in and the rest of the sentence,
                  optionally with a citation.
                </p>
                {draft.about.benefits.map((b, index) => (
                  <div
                    key={index}
                    className="flex items-start gap-2 rounded-md border bg-muted/10 p-2"
                  >
                    <Input
                      value={b.lead}
                      placeholder="Lead (bold)"
                      className="flex-1"
                      onChange={(e) => {
                        const copy = [...draft.about.benefits];
                        copy[index] = { ...copy[index], lead: e.target.value };
                        patchAbout({ benefits: copy });
                      }}
                    />
                    <Input
                      value={b.rest ?? ""}
                      placeholder="rest of the sentence"
                      className="flex-1"
                      onChange={(e) => {
                        const copy = [...draft.about.benefits];
                        copy[index] = { ...copy[index], rest: e.target.value };
                        patchAbout({ benefits: copy });
                      }}
                    />
                    <div className="w-20 shrink-0">
                      <Input
                        type="number"
                        min={1}
                        placeholder="Cite #"
                        value={b.citation ?? ""}
                        onChange={(e) => {
                          const copy = [...draft.about.benefits];
                          const n = parseInt(e.target.value, 10);
                          copy[index] = {
                            ...copy[index],
                            citation: Number.isFinite(n) ? n : undefined,
                          };
                          patchAbout({ benefits: copy });
                        }}
                      />
                    </div>
                    <RowControls
                      onUp={() =>
                        patchAbout({
                          benefits: moveIn(draft.about.benefits, index, -1),
                        })
                      }
                      onDown={() =>
                        patchAbout({
                          benefits: moveIn(draft.about.benefits, index, 1),
                        })
                      }
                      onRemove={() =>
                        patchAbout({
                          benefits: draft.about.benefits.filter(
                            (_, i) => i !== index,
                          ),
                        })
                      }
                      isFirst={index === 0}
                      isLast={index === draft.about.benefits.length - 1}
                    />
                  </div>
                ))}
                <AddButton
                  label="Add benefit"
                  onClick={() =>
                    patchAbout({
                      benefits: [...draft.about.benefits, { lead: "", rest: "" }],
                    })
                  }
                />
              </div>

              {/* Citations */}
              <div className="space-y-2">
                <Label>References</Label>
                <p className="text-xs text-muted-foreground">
                  Numbered from 1 in this order. A paragraph or benefit citation
                  number refers to the entry at that position.
                </p>
                {draft.about.citations.map((c, index) => (
                  <div key={index} className="flex items-start gap-2">
                    <span className="mt-2 w-6 shrink-0 text-right text-sm text-muted-foreground">
                      {index + 1}.
                    </span>
                    <Textarea
                      value={c}
                      rows={2}
                      placeholder="Author AB, et al. Title. Journal. Year;Vol:Pages"
                      className="flex-1"
                      onChange={(e) => {
                        const copy = [...draft.about.citations];
                        copy[index] = e.target.value;
                        patchAbout({ citations: copy });
                      }}
                    />
                    <RowControls
                      onUp={() =>
                        patchAbout({
                          citations: moveIn(draft.about.citations, index, -1),
                        })
                      }
                      onDown={() =>
                        patchAbout({
                          citations: moveIn(draft.about.citations, index, 1),
                        })
                      }
                      onRemove={() =>
                        patchAbout({
                          citations: draft.about.citations.filter(
                            (_, i) => i !== index,
                          ),
                        })
                      }
                      isFirst={index === 0}
                      isLast={index === draft.about.citations.length - 1}
                    />
                  </div>
                ))}
                <AddButton
                  label="Add reference"
                  onClick={() =>
                    patchAbout({ citations: [...draft.about.citations, ""] })
                  }
                />
              </div>
            </section>

            {/* ----------------------------- Includes ----------------------------- */}
            <section className="space-y-2 border-t pt-6">
              <SectionTitle
                title="Includes"
                hint="Page-specific inclusion bullets (distinct from the checkout's What's Included)."
              />
              {draft.includes.map((item, index) => (
                <div key={index} className="flex items-center gap-2">
                  <Input
                    value={item}
                    placeholder="Included item"
                    onChange={(e) => {
                      const copy = [...draft.includes];
                      copy[index] = e.target.value;
                      patch({ includes: copy });
                    }}
                  />
                  <RowControls
                    onUp={() => patch({ includes: moveIn(draft.includes, index, -1) })}
                    onDown={() => patch({ includes: moveIn(draft.includes, index, 1) })}
                    onRemove={() =>
                      patch({
                        includes: draft.includes.filter((_, i) => i !== index),
                      })
                    }
                    isFirst={index === 0}
                    isLast={index === draft.includes.length - 1}
                  />
                </div>
              ))}
              <AddButton
                label="Add item"
                onClick={() => patch({ includes: [...draft.includes, ""] })}
              />
            </section>

            {/* ------------------------------- Steps ------------------------------ */}
            <section className="space-y-3 border-t pt-6">
              <SectionTitle
                title="Path to care"
                hint="The steps a patient goes through, in order."
              />
              {draft.steps.map((s, index) => (
                <div
                  key={index}
                  className="space-y-2 rounded-md border bg-muted/10 p-3"
                >
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium text-muted-foreground">
                      Step {index + 1}
                    </span>
                    <RowControls
                      onUp={() => patch({ steps: moveIn(draft.steps, index, -1) })}
                      onDown={() => patch({ steps: moveIn(draft.steps, index, 1) })}
                      onRemove={() =>
                        patch({
                          steps: draft.steps.filter((_, i) => i !== index),
                        })
                      }
                      isFirst={index === 0}
                      isLast={index === draft.steps.length - 1}
                    />
                  </div>
                  <Input
                    value={s.title ?? ""}
                    placeholder="Step title"
                    onChange={(e) => {
                      const copy = [...draft.steps];
                      copy[index] = { ...copy[index], title: e.target.value };
                      patch({ steps: copy });
                    }}
                  />
                  <Textarea
                    value={s.description ?? ""}
                    rows={2}
                    placeholder="Step description"
                    onChange={(e) => {
                      const copy = [...draft.steps];
                      copy[index] = {
                        ...copy[index],
                        description: e.target.value,
                      };
                      patch({ steps: copy });
                    }}
                  />
                  <div className="flex items-center gap-2">
                    <ImageIcon className="h-4 w-4 shrink-0 text-muted-foreground" />
                    <Input
                      value={s.image ?? ""}
                      placeholder="Image URL (optional)"
                      onChange={(e) => {
                        const copy = [...draft.steps];
                        copy[index] = { ...copy[index], image: e.target.value };
                        patch({ steps: copy });
                      }}
                    />
                  </div>
                </div>
              ))}
              <AddButton
                label="Add step"
                onClick={() =>
                  patch({
                    steps: [
                      ...draft.steps,
                      { title: "", description: "", image: "" },
                    ],
                  })
                }
              />
            </section>
          </>
        ) : (
          <PdpReadOnly pdp={toPdp(toDraft(value))} />
        )}
      </CardContent>
    </Card>
  );
}

/* ------------------------------ small helpers ------------------------------ */

function SectionTitle({ title, hint }: { title: string; hint?: string }) {
  return (
    <div>
      <p className="text-sm font-semibold">{title}</p>
      {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
    </div>
  );
}

function Field({
  label,
  hint,
  children,
}: {
  label: string;
  hint?: string;
  children: React.ReactNode;
}) {
  return (
    <div className="space-y-1.5">
      <Label>{label}</Label>
      {children}
      {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
    </div>
  );
}

function AddButton({
  label,
  onClick,
}: {
  label: string;
  onClick: () => void;
}) {
  return (
    <Button type="button" variant="outline" size="sm" onClick={onClick}>
      <Plus className="mr-2 h-4 w-4" />
      {label}
    </Button>
  );
}

function RowControls({
  onUp,
  onDown,
  onRemove,
  isFirst,
  isLast,
}: {
  onUp: () => void;
  onDown: () => void;
  onRemove: () => void;
  isFirst: boolean;
  isLast: boolean;
}) {
  return (
    <div className="flex shrink-0 items-center">
      <Button
        type="button"
        variant="ghost"
        size="icon"
        disabled={isFirst}
        onClick={onUp}
        aria-label="Move up"
      >
        <ArrowUp className="h-4 w-4" />
      </Button>
      <Button
        type="button"
        variant="ghost"
        size="icon"
        disabled={isLast}
        onClick={onDown}
        aria-label="Move down"
      >
        <ArrowDown className="h-4 w-4" />
      </Button>
      <Button
        type="button"
        variant="ghost"
        size="icon"
        onClick={onRemove}
        aria-label="Remove"
        className="text-destructive hover:bg-destructive/10 hover:text-destructive"
      >
        <Trash2 className="h-4 w-4" />
      </Button>
    </div>
  );
}

/* ------------------------------ read-only view ----------------------------- */

function PdpReadOnly({ pdp }: { pdp: ProductPdpContent }) {
  return (
    <div className="space-y-6">
      {(pdp.badge || pdp.subBadge || pdp.shortDesc || pdp.description) && (
        <div className="space-y-2">
          <SectionTitle title="Header" />
          {(pdp.badge || pdp.subBadge) && (
            <div className="flex flex-wrap gap-2">
              {pdp.badge && (
                <span className="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">
                  {pdp.badge}
                </span>
              )}
              {pdp.subBadge && (
                <span className="inline-flex items-center rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">
                  {pdp.subBadge}
                </span>
              )}
            </div>
          )}
          {pdp.shortDesc && <p className="text-sm">{pdp.shortDesc}</p>}
          {pdp.description && (
            <p className="text-sm text-muted-foreground">{pdp.description}</p>
          )}
        </div>
      )}

      {pdp.images?.length ? (
        <div className="space-y-2">
          <SectionTitle title={`Gallery (${pdp.images.length})`} />
          <div className="flex flex-wrap gap-2">
            {pdp.images.map((url, i) => (
              <img
                key={i}
                src={url}
                alt={`Gallery ${i + 1}`}
                className="h-20 w-20 rounded border bg-white object-contain p-1"
              />
            ))}
          </div>
        </div>
      ) : null}

      {pdp.about && (
        <div className="space-y-3">
          <SectionTitle title="About" />
          {pdp.about.heading && (
            <p className="font-medium">{pdp.about.heading}</p>
          )}
          {pdp.about.paragraphs?.map((p, i) => (
            <p key={i} className="text-sm text-muted-foreground">
              {p.text}
              {p.citation ? (
                <sup className="ml-0.5 text-primary">[{p.citation}]</sup>
              ) : null}
            </p>
          ))}
          {pdp.about.note && (
            <p className="rounded-md border bg-muted/20 p-2 text-sm">
              {pdp.about.note}
            </p>
          )}
          {pdp.about.benefits?.length ? (
            <div>
              {pdp.about.benefitsHeading && (
                <p className="text-sm font-medium">
                  {pdp.about.benefitsHeading}
                </p>
              )}
              <ul className="mt-1 space-y-1">
                {pdp.about.benefits.map((b, i) => (
                  <li key={i} className="text-sm">
                    <span className="font-medium">{b.lead}</span>
                    {b.rest ? ` ${b.rest}` : ""}
                    {b.citation ? (
                      <sup className="ml-0.5 text-primary">[{b.citation}]</sup>
                    ) : null}
                  </li>
                ))}
              </ul>
            </div>
          ) : null}
          {pdp.about.citations?.length ? (
            <div>
              <p className="text-xs font-medium text-muted-foreground">
                References
              </p>
              <ol className="mt-1 list-decimal space-y-1 pl-5 text-xs text-muted-foreground">
                {pdp.about.citations.map((c, i) => (
                  <li key={i}>{c}</li>
                ))}
              </ol>
            </div>
          ) : null}
        </div>
      )}

      {pdp.includes?.length ? (
        <div className="space-y-1">
          <SectionTitle title="Includes" />
          <ul className="list-disc space-y-1 pl-5 text-sm">
            {pdp.includes.map((item, i) => (
              <li key={i}>{item}</li>
            ))}
          </ul>
        </div>
      ) : null}

      {pdp.steps?.length ? (
        <div className="space-y-2">
          <SectionTitle title="Path to care" />
          <ol className="space-y-2">
            {pdp.steps.map((s, i) => (
              <li key={i} className="rounded-md border bg-muted/10 p-2 text-sm">
                <span className="font-medium">
                  {i + 1}. {s.title || "Untitled step"}
                </span>
                {s.description && (
                  <p className="mt-0.5 text-muted-foreground">
                    {s.description}
                  </p>
                )}
              </li>
            ))}
          </ol>
        </div>
      ) : null}
    </div>
  );
}
