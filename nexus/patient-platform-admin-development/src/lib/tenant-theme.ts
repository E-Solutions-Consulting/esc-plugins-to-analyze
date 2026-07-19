/**
 * Applies the active tenant's brand colours to Nexus as CSS variables.
 *
 * Nexus is shared by every tenant, so its palette is not hardcoded to one brand:
 * the colours configured per tenant in Settings → Branding are applied at runtime
 * and reverted when the tenant changes or the admin logs out.
 *
 * Only the tokens that legitimately carry brand identity are overridden.
 * `--secondary`, `--accent` and `--muted` are deliberately NOT touched: in
 * shadcn/ui those are neutral UI surfaces (hover states, card and dialog
 * backgrounds), not brand slots — painting them with a brand hex turns every
 * hover state into that colour. The tenant's secondary/accent hexes are exposed
 * as `--tenant-*` for components that want to opt into them explicitly.
 */

/** Tokens we overwrite, so they can be cleanly reverted. */
const THEMED_TOKENS = [
  "--primary",
  "--ring",
  "--sidebar-primary",
  "--sidebar-ring",
  "--tenant-primary",
  "--tenant-secondary",
  "--tenant-accent",
] as const;

/** Converts `#1F0159` to the `H S% L%` triplet shadcn's `hsl(var(--x))` expects. */
export function hexToHsl(hex: string): string | null {
  const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex.trim());
  if (!result) return null;

  const r = parseInt(result[1], 16) / 255;
  const g = parseInt(result[2], 16) / 255;
  const b = parseInt(result[3], 16) / 255;

  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  const l = (max + min) / 2;
  let h = 0;
  let s = 0;

  if (max !== min) {
    const d = max - min;
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    switch (max) {
      case r:
        h = ((g - b) / d + (g < b ? 6 : 0)) / 6;
        break;
      case g:
        h = ((b - r) / d + 2) / 6;
        break;
      case b:
        h = ((r - g) / d + 4) / 6;
        break;
    }
  }

  return `${Math.round(h * 360)} ${Math.round(s * 100)}% ${Math.round(l * 100)}%`;
}

/**
 * Relative luminance per WCAG, used to decide whether text on the brand colour
 * should be white or near-black. Brello's primary is very dark, Allia's default
 * blue is mid-tone — hardcoding white would fail on a pale brand colour.
 */
function relativeLuminance(hex: string): number | null {
  const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex.trim());
  if (!result) return null;

  const channel = (value: string) => {
    const srgb = parseInt(value, 16) / 255;
    return srgb <= 0.03928 ? srgb / 12.92 : ((srgb + 0.055) / 1.055) ** 2.4;
  };

  return (
    0.2126 * channel(result[1]) +
    0.7152 * channel(result[2]) +
    0.0722 * channel(result[3])
  );
}

export interface TenantThemeColors {
  primary_color?: string | null;
  secondary_color?: string | null;
  accent_color?: string | null;
}

/** The subset of an element's style API this module needs (injectable for tests). */
export interface ThemeTarget {
  style: Pick<CSSStyleDeclaration, "setProperty" | "removeProperty">;
}

/** Applies the tenant's colours to the document root (or a supplied element). */
export function applyTenantTheme(
  branding: TenantThemeColors | null,
  target?: ThemeTarget,
): void {
  const root = target ?? document.documentElement;
  if (!branding) {
    clearTenantTheme(root);
    return;
  }

  const primary = branding.primary_color
    ? hexToHsl(branding.primary_color)
    : null;
  const secondary = branding.secondary_color
    ? hexToHsl(branding.secondary_color)
    : null;
  const accent = branding.accent_color ? hexToHsl(branding.accent_color) : null;

  if (primary) {
    root.style.setProperty("--primary", primary);
    root.style.setProperty("--ring", primary);
    root.style.setProperty("--sidebar-primary", primary);
    root.style.setProperty("--sidebar-ring", primary);
    root.style.setProperty("--tenant-primary", primary);

    // Keep text on the brand colour readable across light and dark brands.
    const luminance = relativeLuminance(branding.primary_color ?? "");
    if (luminance !== null) {
      root.style.setProperty(
        "--primary-foreground",
        luminance > 0.45 ? "222.2 47.4% 11.2%" : "0 0% 100%",
      );
    }
  }

  // Brand secondary/accent are exposed but not mapped onto shadcn's neutral
  // surface tokens — see the note at the top of this file.
  if (secondary) root.style.setProperty("--tenant-secondary", secondary);
  if (accent) root.style.setProperty("--tenant-accent", accent);
}

/** Reverts to the stylesheet defaults (logout, or tenant with no branding). */
export function clearTenantTheme(target?: ThemeTarget): void {
  const root = target ?? document.documentElement;
  for (const token of THEMED_TOKENS) {
    root.style.removeProperty(token);
  }
  root.style.removeProperty("--primary-foreground");
}
