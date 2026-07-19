import { beforeEach, describe, expect, it } from "vitest";
import {
  applyTenantTheme,
  clearTenantTheme,
  hexToHsl,
  type ThemeTarget,
} from "./tenant-theme";

const BRELLO = {
  primary_color: "#1F0159", // deep purple
  secondary_color: "#6A45C4",
  accent_color: "#EFD269", // gold
};

/** Stand-in for document.documentElement — this suite runs without a DOM. */
function createTarget() {
  const props = new Map<string, string>();
  const target: ThemeTarget = {
    style: {
      setProperty: (name: string, value: string) => void props.set(name, value),
      removeProperty: (name: string) => {
        const prev = props.get(name) ?? "";
        props.delete(name);
        return prev;
      },
    } as ThemeTarget["style"],
  };
  return { target, token: (name: string) => props.get(name) ?? "" };
}

let ctx: ReturnType<typeof createTarget>;
beforeEach(() => {
  ctx = createTarget();
});

describe("hexToHsl", () => {
  it("converts a hex to the triplet shadcn expects", () => {
    expect(hexToHsl("#FFFFFF")).toBe("0 0% 100%");
    expect(hexToHsl("#000000")).toBe("0 0% 0%");
  });

  it("tolerates a missing # and stray whitespace", () => {
    expect(hexToHsl(" 1F0159 ")).toBe(hexToHsl("#1F0159"));
  });

  it("returns null for junk rather than emitting a broken CSS value", () => {
    expect(hexToHsl("purple")).toBeNull();
    expect(hexToHsl("#12")).toBeNull();
  });
});

describe("applyTenantTheme", () => {
  it("maps the brand primary onto the shadcn primary tokens", () => {
    applyTenantTheme(BRELLO, ctx.target);
    const primary = hexToHsl(BRELLO.primary_color);

    expect(ctx.token("--primary")).toBe(primary);
    expect(ctx.token("--ring")).toBe(primary);
    expect(ctx.token("--sidebar-primary")).toBe(primary);
    expect(ctx.token("--tenant-primary")).toBe(primary);
  });

  it("leaves shadcn's neutral surface tokens alone", () => {
    applyTenantTheme(BRELLO, ctx.target);

    // --accent drives hover:bg-accent, --secondary drives card/dialog surfaces.
    // Painting either with a brand hex would turn every hover state gold.
    expect(ctx.token("--accent")).toBe("");
    expect(ctx.token("--secondary")).toBe("");
    expect(ctx.token("--muted")).toBe("");
  });

  it("exposes brand secondary/accent as opt-in --tenant-* variables", () => {
    applyTenantTheme(BRELLO, ctx.target);
    expect(ctx.token("--tenant-accent")).toBe(hexToHsl(BRELLO.accent_color));
    expect(ctx.token("--tenant-secondary")).toBe(
      hexToHsl(BRELLO.secondary_color),
    );
  });

  it("uses white text on a dark brand colour", () => {
    applyTenantTheme({ primary_color: "#1F0159" }, ctx.target);
    expect(ctx.token("--primary-foreground")).toBe("0 0% 100%");
  });

  it("uses dark text on a pale brand colour", () => {
    // Gold: white text on it would be unreadable.
    applyTenantTheme({ primary_color: "#EFD269" }, ctx.target);
    expect(ctx.token("--primary-foreground")).toBe("222.2 47.4% 11.2%");
  });

  it("ignores an unparseable colour instead of writing garbage", () => {
    applyTenantTheme({ primary_color: "not-a-colour" }, ctx.target);
    expect(ctx.token("--primary")).toBe("");
  });

  it("reverts to stylesheet defaults on clear (logout / tenant switch)", () => {
    applyTenantTheme(BRELLO, ctx.target);
    expect(ctx.token("--primary")).not.toBe("");

    clearTenantTheme(ctx.target);

    expect(ctx.token("--primary")).toBe("");
    expect(ctx.token("--primary-foreground")).toBe("");
    expect(ctx.token("--tenant-primary")).toBe("");
  });

  it("clears when a tenant has no branding row", () => {
    applyTenantTheme(BRELLO, ctx.target);
    applyTenantTheme(null, ctx.target);
    expect(ctx.token("--primary")).toBe("");
  });
});
