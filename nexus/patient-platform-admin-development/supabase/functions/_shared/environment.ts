const NON_LIVE_HINTS = [
  "staging",
  "stage",
  "sandbox",
  "test",
  "dev",
  "development",
  "local",
];

const LIVE_HINTS = [
  "production",
  "prod",
  "live",
];

const DEVELOPMENT_HINTS = [
  "dev",
  "development",
];

const STAGING_HINTS = [
  "staging",
  "stage",
];

function getEnvironmentHints(): string {
  return [
    Deno.env.get("APP_ENV"),
    Deno.env.get("ENVIRONMENT"),
    Deno.env.get("SUPABASE_ENV"),
  ]
    .filter((value): value is string => Boolean(value))
    .join(" ")
    .toLowerCase();
}

function getRuntimeEnvironment(): string {
  return Deno.env.get("DENO_ENV")?.trim().toLowerCase() || "";
}

function getExplicitAppEnvironment(): string {
  return [
    Deno.env.get("APP_ENV"),
    Deno.env.get("ENVIRONMENT"),
    Deno.env.get("SUPABASE_ENV"),
  ]
    .find((value) => Boolean(value && value.trim()))?.trim().toLowerCase() ||
    "";
}

function getNonLiveProjectRefs(): string[] {
  const configuredRefs = Deno.env.get("NON_LIVE_SUPABASE_PROJECT_REFS") || "";
  const parsedConfiguredRefs = configuredRefs
    .split(",")
    .map((value) => value.trim())
    .filter(Boolean);

  return Array.from(
    new Set([
      "rhzrxfckhogjppjsioyn", // historical staging ref
      ...parsedConfiguredRefs,
    ]),
  );
}

function getLiveProjectRefs(): string[] {
  const configuredRefs = Deno.env.get("LIVE_SUPABASE_PROJECT_REFS") || "";
  const parsedConfiguredRefs = configuredRefs
    .split(",")
    .map((value) => value.trim())
    .filter(Boolean);

  return Array.from(
    new Set([
      "dfejvhgwqhywmtxyxkyo", // current production ref
      ...parsedConfiguredRefs,
    ]),
  );
}

function getDevelopmentProjectRefs(): string[] {
  const configuredRefs = Deno.env.get("DEVELOPMENT_SUPABASE_PROJECT_REFS") ||
    "";
  const parsedConfiguredRefs = configuredRefs
    .split(",")
    .map((value) => value.trim())
    .filter(Boolean);

  return Array.from(
    new Set([
      "sunzxjnbgtknqeivljtd", // current development ref
      ...parsedConfiguredRefs,
    ]),
  );
}

function getStagingProjectRefs(): string[] {
  const configuredRefs = Deno.env.get("STAGING_SUPABASE_PROJECT_REFS") || "";
  const parsedConfiguredRefs = configuredRefs
    .split(",")
    .map((value) => value.trim())
    .filter(Boolean);

  return Array.from(
    new Set([
      "rhzrxfckhogjppjsioyn", // current/historical staging ref
      ...parsedConfiguredRefs,
    ]),
  );
}

function includesHint(value: string, hints: string[]): boolean {
  return hints.some((hint) => value.includes(hint));
}

function getProjectRef(currentSupabaseUrl: string): string {
  try {
    return new URL(currentSupabaseUrl).hostname.toLowerCase().split(".")[0];
  } catch {
    return "";
  }
}

export function isDevelopmentEnvironment(currentSupabaseUrl: string): boolean {
  const explicitAppEnvironment = getExplicitAppEnvironment();
  if (explicitAppEnvironment) {
    if (includesHint(explicitAppEnvironment, DEVELOPMENT_HINTS)) {
      return true;
    }

    if (
      includesHint(explicitAppEnvironment, STAGING_HINTS) ||
      includesHint(explicitAppEnvironment, LIVE_HINTS)
    ) {
      return false;
    }
  }

  const projectRef = getProjectRef(currentSupabaseUrl);
  return Boolean(projectRef) &&
    getDevelopmentProjectRefs().includes(projectRef);
}

export function isStagingEnvironment(currentSupabaseUrl: string): boolean {
  const explicitAppEnvironment = getExplicitAppEnvironment();
  if (explicitAppEnvironment) {
    if (includesHint(explicitAppEnvironment, STAGING_HINTS)) {
      return true;
    }

    if (
      includesHint(explicitAppEnvironment, DEVELOPMENT_HINTS) ||
      includesHint(explicitAppEnvironment, LIVE_HINTS)
    ) {
      return false;
    }
  }

  const projectRef = getProjectRef(currentSupabaseUrl);
  return Boolean(projectRef) && getStagingProjectRefs().includes(projectRef);
}

export function isProductionEnvironment(currentSupabaseUrl: string): boolean {
  const explicitAppEnvironment = getExplicitAppEnvironment();
  if (explicitAppEnvironment) {
    if (includesHint(explicitAppEnvironment, LIVE_HINTS)) {
      return true;
    }

    if (
      includesHint(explicitAppEnvironment, DEVELOPMENT_HINTS) ||
      includesHint(explicitAppEnvironment, STAGING_HINTS)
    ) {
      return false;
    }
  }

  const projectRef = getProjectRef(currentSupabaseUrl);
  return Boolean(projectRef) && getLiveProjectRefs().includes(projectRef);
}

export type DeploymentEnvironment =
  | "development"
  | "staging"
  | "production";

export function getDeploymentEnvironment(
  currentSupabaseUrl: string,
): DeploymentEnvironment | null {
  if (isDevelopmentEnvironment(currentSupabaseUrl)) return "development";
  if (isStagingEnvironment(currentSupabaseUrl)) return "staging";
  if (isProductionEnvironment(currentSupabaseUrl)) return "production";
  return null;
}

export function isNonLiveEnvironment(currentSupabaseUrl: string): boolean {
  const explicitAppEnvironment = getExplicitAppEnvironment();
  if (explicitAppEnvironment) {
    if (
      includesHint(explicitAppEnvironment, NON_LIVE_HINTS)
    ) {
      return true;
    }

    if (includesHint(explicitAppEnvironment, LIVE_HINTS)) {
      return false;
    }
  }

  const environmentHints = getEnvironmentHints();
  if (includesHint(environmentHints, NON_LIVE_HINTS)) {
    return true;
  }

  const runtimeEnvironment = getRuntimeEnvironment();
  if (runtimeEnvironment) {
    if (includesHint(runtimeEnvironment, NON_LIVE_HINTS)) {
      return true;
    }
  }

  try {
    const hostname = new URL(currentSupabaseUrl).hostname.toLowerCase();
    if (
      hostname === "localhost" ||
      hostname.startsWith("localhost:") ||
      hostname.startsWith("127.") ||
      hostname.endsWith(".local")
    ) {
      return true;
    }

    const projectRef = hostname.split(".")[0];
    if (getNonLiveProjectRefs().includes(projectRef)) {
      return true;
    }

    if (getLiveProjectRefs().includes(projectRef)) {
      return false;
    }

    // If no explicit environment was provided, default unknown Supabase refs to non-live.
    // This prevents DENO_ENV=production defaults from disabling test-mode behavior in dev projects.
    if (hostname.endsWith(".supabase.co")) {
      return true;
    }

    return false;
  } catch {
    return false;
  }
}
