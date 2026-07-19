import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import { checkRateLimit, getCorsHeaders } from "./helpers.ts";
import { dateTime } from "../_shared/dayjs.ts";

Deno.serve(async (req: Request): Promise<Response> => {
  const corsHeaders = getCorsHeaders(req);

  function jsonResponse(
    data: unknown,
    status = 200,
    headers: Record<string, string> = {},
  ) {
    return new Response(JSON.stringify(data), {
      status,
      headers: {
        ...corsHeaders,
        "Content-Type": "application/json",
        ...headers,
      },
    });
  }

  function errorResponse(code: string, message: string, status = 400) {
    return jsonResponse({ error: { code, message } }, status);
  }

  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  const clientIp = req.headers.get("x-forwarded-for") || "unknown";
  const rateCheck = checkRateLimit(clientIp);

  if (!rateCheck.allowed) {
    return errorResponse(
      "RATE_LIMIT_EXCEEDED",
      "Too many requests. Please try again later.",
      429,
    );
  }

  const url = new URL(req.url);
  let path = url.pathname;
  path = path.replace(/^\/functions\/v1/, "");
  if (path.startsWith("/healthtracking-api")) {
    path = path.slice("/healthtracking-api".length);
  }
  if (!path.startsWith("/")) path = `/${path}`;
  if (path.length > 1 && path.endsWith("/")) path = path.slice(0, -1);

  const supabaseUrl = Deno.env.get("SUPABASE_URL");
  const supabaseAnonKey = Deno.env.get("SUPABASE_ANON_KEY");
  const authHeader = req.headers.get("Authorization");

  if (!supabaseUrl || !supabaseAnonKey) {
    return errorResponse("SERVER_ERROR", "Missing Supabase configuration", 500);
  }

  const supabase = createClient(supabaseUrl, supabaseAnonKey, {
    global: {
      headers: authHeader ? { Authorization: authHeader } : {},
    },
  });

  type ErrorResult = { error: Response };
  type AuthenticatedPatientResult =
    | {
      patient: {
        id: string;
        tenant_id: string;
      };
    }
    | ErrorResult;
  type InjectionSiteRow = {
    id: string;
    label: string;
    image_url: string;
    is_active: boolean;
    display_order: number;
    created_at: string;
    updated_at: string;
  };
  type InjectionSitesResult = { data: InjectionSiteRow[] } | ErrorResult;
  const shotIntakeSelect =
    "id, medication_id, dosage_strength, pain_level, intake_date, created_at, updated_at, injection_site:tenant_injection_site_definitions!medication_shot_intakes_injection_site_id_fkey(id, label, image_url, is_active, display_order, created_at, updated_at)";
  const bodyMeasurementEntrySelect =
    "id, chest_inches, waist_inches, hips_inches, arms_inches, measured_at, created_at, updated_at";

  async function getAuthenticatedPatient(): Promise<
    AuthenticatedPatientResult
  > {
    if (!authHeader) {
      return {
        error: errorResponse(
          "UNAUTHORIZED",
          "Authorization header required",
          401,
        ),
      };
    }

    const {
      data: { user },
      error: userError,
    } = await supabase.auth.getUser();

    if (userError || !user) {
      return {
        error: errorResponse("UNAUTHORIZED", "Invalid or expired token", 401),
      };
    }

    const { data: patient, error: patientError } = await supabase
      .from("patients")
      .select("id, tenant_id")
      .eq("auth_user_id", user.id)
      .maybeSingle();

    if (patientError) {
      console.error("Patient fetch error:", patientError);
      return {
        error: errorResponse(
          "FETCH_ERROR",
          "Failed to fetch patient profile",
          500,
        ),
      };
    }

    if (!patient) {
      return {
        error: errorResponse("NOT_FOUND", "Patient profile not found", 404),
      };
    }

    return {
      patient: {
        id: patient.id,
        tenant_id: patient.tenant_id,
      },
    };
  }

  async function getTenantInjectionSites(
    tenantId: string,
  ): Promise<InjectionSitesResult> {
    const { data, error } = await supabase
      .from("tenant_injection_site_definitions")
      .select(
        "id, label, image_url, is_active, display_order, created_at, updated_at",
      )
      .eq("tenant_id", tenantId)
      .eq("is_active", true)
      .order("display_order", { ascending: true })
      .order("label", { ascending: true });

    if (error) {
      console.error("Injection site definitions fetch error:", error);
      return {
        error: errorResponse(
          "FETCH_ERROR",
          "Failed to fetch injection site definitions",
          500,
        ),
      };
    }

    return { data: (data ?? []) as InjectionSiteRow[] };
  }

  type ShotIntakeRow = {
    id: string;
    medication_id: string;
    injection_site:
      | InjectionSiteRow
      | InjectionSiteRow[]
      | null;
    dosage_strength: number;
    pain_level: number;
    intake_date: string;
    created_at: string;
    updated_at: string;
  };

  function serializeShotIntake(row: ShotIntakeRow) {
    const injectionSite = Array.isArray(row.injection_site)
      ? row.injection_site[0] ?? null
      : row.injection_site ?? null;

    return {
      id: row.id,
      medication_id: row.medication_id,
      injection_site: injectionSite,
      dosage_strength: row.dosage_strength,
      pain_level: row.pain_level,
      intake_date: row.intake_date,
      created_at: row.created_at,
      updated_at: row.updated_at,
    };
  }

  type BodyMeasurementEntryRow = {
    id: string;
    chest_inches: number;
    waist_inches: number;
    hips_inches: number;
    arms_inches: number;
    measured_at: string;
    created_at: string;
    updated_at: string;
  };

  function serializeBodyMeasurementEntry(row: BodyMeasurementEntryRow) {
    return {
      id: row.id,
      chest_inches: row.chest_inches,
      waist_inches: row.waist_inches,
      hips_inches: row.hips_inches,
      arms_inches: row.arms_inches,
      measured_at: row.measured_at,
      created_at: row.created_at,
      updated_at: row.updated_at,
    };
  }

  try {
    if (req.method === "GET" && path === "/injection_sites") {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      const injectionSitesResult = await getTenantInjectionSites(
        authResult.patient.tenant_id,
      );
      if ("error" in injectionSitesResult) return injectionSitesResult.error;

      return jsonResponse({ data: injectionSitesResult.data });
    }

    if (req.method === "GET" && path === "/shot_tracker") {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      const { data, error } = await supabase
        .from("medication_shot_intakes")
        .select(shotIntakeSelect)
        .eq("patient_id", authResult.patient.id)
        .order("intake_date", { ascending: false })
        .order("created_at", { ascending: false });

      if (error) {
        console.error("Intake fetch error:", error);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch medication intake records",
          500,
        );
      }

      return jsonResponse({
        data: (data ?? []).map((entry) =>
          serializeShotIntake(entry as ShotIntakeRow)
        ),
      });
    }

    if (req.method === "GET" && path === "/weight_tracker") {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      const { data, error } = await supabase
        .from("patient_weight_entries")
        .select(
          "id, weight_value, weight_unit, weighed_at, created_at, updated_at",
        )
        .eq("patient_id", authResult.patient.id)
        .order("weighed_at", { ascending: false })
        .order("created_at", { ascending: false });

      if (error) {
        console.error("Weight fetch error:", error);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch weight history",
          500,
        );
      }

      return jsonResponse({ data });
    }

    if (req.method === "GET" && path === "/body_measurement_tracker") {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      const { data, error } = await supabase
        .from("patient_body_measurement_entries")
        .select(bodyMeasurementEntrySelect)
        .eq("patient_id", authResult.patient.id)
        .order("measured_at", { ascending: false })
        .order("created_at", { ascending: false });

      if (error) {
        console.error("Body measurement fetch error:", error);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch body measurement history",
          500,
        );
      }

      return jsonResponse({
        data: (data ?? []).map((entry) =>
          serializeBodyMeasurementEntry(entry as BodyMeasurementEntryRow)
        ),
      });
    }

    if (req.method === "GET" && path === "/mood_tracker") {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      const { data, error } = await supabase
        .from("patient_mood_change_entries")
        .select(
          "id, mood_change_definition_id, mood_change_label, recorded_at, created_at, updated_at",
        )
        .eq("patient_id", authResult.patient.id)
        .order("recorded_at", { ascending: false })
        .order("created_at", { ascending: false });

      if (error) {
        console.error("Mood fetch error:", error);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch mood history",
          500,
        );
      }

      return jsonResponse({ data });
    }

    if (req.method === "GET" && path === "/activity_tracker") {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      const { data, error } = await supabase
        .from("patient_activity_entries")
        .select(
          "id, activity_definition_id, activity_label, recorded_at, created_at, updated_at",
        )
        .eq("patient_id", authResult.patient.id)
        .order("recorded_at", { ascending: false })
        .order("created_at", { ascending: false });

      if (error) {
        console.error("Activity fetch error:", error);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch activity history",
          500,
        );
      }

      return jsonResponse({ data });
    }

    if (req.method === "GET" && path === "/symptoms") {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      const { data, error } = await supabase
        .from("tenant_symptom_definitions")
        .select("id, label, is_active, display_order, created_at, updated_at")
        .eq("tenant_id", authResult.patient.tenant_id)
        .eq("is_active", true)
        .order("display_order", { ascending: true })
        .order("label", { ascending: true });

      if (error) {
        console.error("Symptom definitions fetch error:", error);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch symptom definitions",
          500,
        );
      }

      return jsonResponse({ data });
    }

    if (req.method === "GET" && path === "/activities") {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      const { data, error } = await supabase
        .from("tenant_activity_definitions")
        .select("id, label, is_active, display_order, created_at, updated_at")
        .eq("tenant_id", authResult.patient.tenant_id)
        .eq("is_active", true)
        .order("display_order", { ascending: true })
        .order("label", { ascending: true });

      if (error) {
        console.error("Activity definitions fetch error:", error);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch activity definitions",
          500,
        );
      }

      return jsonResponse({ data });
    }

    if (
      req.method === "GET" && (path === "/moods" || path === "/mood_changes")
    ) {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      const { data, error } = await supabase
        .from("tenant_mood_change_definitions")
        .select("id, label, is_active, display_order, created_at, updated_at")
        .eq("tenant_id", authResult.patient.tenant_id)
        .eq("is_active", true)
        .order("display_order", { ascending: true })
        .order("label", { ascending: true });

      if (error) {
        console.error("Mood change definitions fetch error:", error);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch mood change definitions",
          500,
        );
      }

      return jsonResponse({ data });
    }

    if (req.method === "GET" && path === "/energy_tracker") {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      const { data, error } = await supabase
        .from("patient_energy_entries")
        .select("id, energy_value, recorded_at, created_at, updated_at")
        .eq("patient_id", authResult.patient.id)
        .order("recorded_at", { ascending: false })
        .order("created_at", { ascending: false });

      if (error) {
        console.error("Energy fetch error:", error);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch energy history",
          500,
        );
      }

      return jsonResponse({ data });
    }

    if (
      req.method === "DELETE" && path.match(/^\/energy_tracker\/[a-f0-9-]+$/)
    ) {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      const entryId = path.split("/")[2];

      const { data, error } = await supabase
        .from("patient_energy_entries")
        .delete()
        .eq("id", entryId)
        .eq("patient_id", authResult.patient.id)
        .select("id");

      if (error) {
        console.error("Energy delete error:", error);
        return errorResponse(
          "DELETE_ERROR",
          "Failed to delete energy entry",
          500,
        );
      }

      if (!data || data.length === 0) {
        return errorResponse("NOT_FOUND", "Energy entry not found", 404);
      }

      return jsonResponse({ data: { id: entryId, deleted: true } });
    }

    if (
      req.method === "DELETE" && path.match(/^\/activity_tracker\/[a-f0-9-]+$/)
    ) {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      const entryId = path.split("/")[2];

      const { data, error } = await supabase
        .from("patient_activity_entries")
        .delete()
        .eq("id", entryId)
        .eq("patient_id", authResult.patient.id)
        .select("id");

      if (error) {
        console.error("Activity delete error:", error);
        return errorResponse(
          "DELETE_ERROR",
          "Failed to delete activity entry",
          500,
        );
      }

      if (!data || data.length === 0) {
        return errorResponse("NOT_FOUND", "Activity entry not found", 404);
      }

      return jsonResponse({ data: { id: entryId, deleted: true } });
    }

    if (req.method === "DELETE" && path.match(/^\/mood_tracker\/[a-f0-9-]+$/)) {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      const entryId = path.split("/")[2];

      const { data, error } = await supabase
        .from("patient_mood_change_entries")
        .delete()
        .eq("id", entryId)
        .eq("patient_id", authResult.patient.id)
        .select("id");

      if (error) {
        console.error("Mood delete error:", error);
        return errorResponse(
          "DELETE_ERROR",
          "Failed to delete mood entry",
          500,
        );
      }

      if (!data || data.length === 0) {
        return errorResponse("NOT_FOUND", "Mood entry not found", 404);
      }

      return jsonResponse({ data: { id: entryId, deleted: true } });
    }

    if (req.method === "GET" && path === "/symptom_tracker") {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      const { data, error } = await supabase
        .from("patient_symptom_entries")
        .select(
          "id, symptom_definition_id, symptom_label, recorded_at, created_at, updated_at",
        )
        .eq("patient_id", authResult.patient.id)
        .order("recorded_at", { ascending: false })
        .order("created_at", { ascending: false });

      if (error) {
        console.error("Symptom fetch error:", error);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch symptom history",
          500,
        );
      }

      return jsonResponse({ data });
    }

    if (
      req.method === "DELETE" && path.match(/^\/symptom_tracker\/[a-f0-9-]+$/)
    ) {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      const entryId = path.split("/")[2];

      const { data, error } = await supabase
        .from("patient_symptom_entries")
        .delete()
        .eq("id", entryId)
        .eq("patient_id", authResult.patient.id)
        .select("id");

      if (error) {
        console.error("Symptom delete error:", error);
        return errorResponse(
          "DELETE_ERROR",
          "Failed to delete symptom entry",
          500,
        );
      }

      if (!data || data.length === 0) {
        return errorResponse("NOT_FOUND", "Symptom entry not found", 404);
      }

      return jsonResponse({ data: { id: entryId, deleted: true } });
    }

    if (req.method === "POST" && path === "/shot_tracker") {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      let payload: Record<string, unknown>;
      try {
        payload = (await req.json()) as Record<string, unknown>;
      } catch (_error) {
        return errorResponse(
          "INVALID_JSON",
          "Request body must be valid JSON",
          400,
        );
      }

      if (payload.shot_location !== undefined) {
        return errorResponse(
          "VALIDATION_ERROR",
          "shot_location has been removed; use injection_site_id",
          400,
        );
      }

      const dosageRaw = payload.dosage_strength;
      const medicationId = typeof payload.medication_id === "string"
        ? payload.medication_id
        : "";
      const intakeDate = typeof payload.intake_date === "string"
        ? payload.intake_date
        : "";
      const painLevelRaw = payload.pain_level;
      const injectionSiteId = typeof payload.injection_site_id === "string"
        ? payload.injection_site_id.trim()
        : "";

      if (!injectionSiteId) {
        return errorResponse(
          "VALIDATION_ERROR",
          "injection_site_id is required",
          400,
        );
      }

      const { data: injectionSite, error: injectionSiteError } = await supabase
        .from("tenant_injection_site_definitions")
        .select("id, label")
        .eq("tenant_id", authResult.patient.tenant_id)
        .eq("id", injectionSiteId)
        .eq("is_active", true)
        .maybeSingle();

      if (injectionSiteError) {
        console.error("Injection site fetch error:", injectionSiteError);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch injection site",
          500,
        );
      }

      if (!injectionSite) {
        return errorResponse(
          "VALIDATION_ERROR",
          "injection_site_id is invalid or inactive",
          400,
        );
      }

      const dosageStrength = Number(dosageRaw);
      if (!Number.isFinite(dosageStrength)) {
        return errorResponse(
          "VALIDATION_ERROR",
          "dosage_strength must be a number",
          400,
        );
      }

      const painLevel = Number(painLevelRaw);
      if (!Number.isInteger(painLevel) || painLevel < 0 || painLevel > 5) {
        return errorResponse(
          "VALIDATION_ERROR",
          "pain_level must be an integer between 0 and 5",
          400,
        );
      }

      if (!medicationId) {
        return errorResponse(
          "VALIDATION_ERROR",
          "medication_id is required",
          400,
        );
      }

      if (!intakeDate || Number.isNaN(Date.parse(intakeDate))) {
        return errorResponse(
          "VALIDATION_ERROR",
          "intake_date must be a valid ISO date",
          400,
        );
      }

      const { data, error } = await supabase
        .from("medication_shot_intakes")
        .insert({
          tenant_id: authResult.patient.tenant_id,
          patient_id: authResult.patient.id,
          medication_id: medicationId,
          injection_site_id: injectionSite.id,
          dosage_strength: dosageStrength,
          pain_level: painLevel,
          intake_date: intakeDate,
        })
        .select(shotIntakeSelect)
        .single();

      if (error) {
        console.error("Intake insert error:", error);
        return errorResponse(
          "INSERT_ERROR",
          "Failed to save medication intake record",
          500,
        );
      }

      return jsonResponse(
        { data: serializeShotIntake(data as ShotIntakeRow) },
        201,
      );
    }

    if (
      req.method === "DELETE" &&
      (path === "/shot_tracker" || path.startsWith("/shot_tracker/"))
    ) {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      let intakeId = "";

      if (path.startsWith("/shot_tracker/")) {
        intakeId = path.slice("/shot_tracker/".length).trim();
      }

      if (!intakeId) {
        intakeId = url.searchParams.get("id")?.trim() ?? "";
      }

      if (!intakeId) {
        const contentType = req.headers.get("content-type") ?? "";
        if (contentType.includes("application/json")) {
          try {
            const payload = (await req.json()) as Record<string, unknown>;
            intakeId = typeof payload.id === "string"
              ? payload.id
              : typeof payload.intake_id === "string"
              ? payload.intake_id
              : "";
            intakeId = intakeId.trim();
          } catch (_error) {
            return errorResponse(
              "INVALID_JSON",
              "Request body must be valid JSON",
              400,
            );
          }
        }
      }

      if (!intakeId) {
        return errorResponse("VALIDATION_ERROR", "id is required", 400);
      }

      const { data, error } = await supabase
        .from("medication_shot_intakes")
        .delete()
        .eq("patient_id", authResult.patient.id)
        .eq("id", intakeId)
        .select(shotIntakeSelect);

      if (error) {
        console.error("Intake delete error:", error);
        return errorResponse(
          "DELETE_ERROR",
          "Failed to delete medication intake record",
          500,
        );
      }

      if (!data || data.length === 0) {
        return errorResponse(
          "NOT_FOUND",
          "Medication intake record not found",
          404,
        );
      }

      return jsonResponse({
        data: serializeShotIntake(data[0] as ShotIntakeRow),
      });
    }

    if (req.method === "POST" && path === "/weight_tracker") {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      let payload: Record<string, unknown>;
      try {
        payload = (await req.json()) as Record<string, unknown>;
      } catch (_error) {
        return errorResponse(
          "INVALID_JSON",
          "Request body must be valid JSON",
          400,
        );
      }

      const weightRaw = payload.weight ?? payload.weight_value;
      const weightValue = Number(weightRaw);
      if (!Number.isFinite(weightValue) || weightValue <= 0) {
        return errorResponse(
          "VALIDATION_ERROR",
          "weight must be a positive number",
          400,
        );
      }

      const unitRaw = typeof payload.weight_unit === "string"
        ? payload.weight_unit.trim().toLowerCase()
        : typeof payload.unit === "string"
        ? payload.unit.trim().toLowerCase()
        : "";

      let weightUnit = unitRaw || "lb";
      if (
        weightUnit === "lbs" || weightUnit === "pound" ||
        weightUnit === "pounds"
      ) {
        weightUnit = "lb";
      } else if (
        weightUnit === "kgs" || weightUnit === "kilogram" ||
        weightUnit === "kilograms"
      ) {
        weightUnit = "kg";
      }

      if (weightUnit !== "lb" && weightUnit !== "kg") {
        return errorResponse(
          "VALIDATION_ERROR",
          "weight_unit must be 'lb' or 'kg'",
          400,
        );
      }

      const weighedAtRaw = typeof payload.weighed_at === "string"
        ? payload.weighed_at
        : typeof payload.recorded_at === "string"
        ? payload.recorded_at
        : typeof payload.measurement_date === "string"
        ? payload.measurement_date
        : "";

      const weighedAt = weighedAtRaw || dateTime().toISOString();
      if (weighedAtRaw && Number.isNaN(Date.parse(weighedAtRaw))) {
        return errorResponse(
          "VALIDATION_ERROR",
          "weighed_at must be a valid ISO date",
          400,
        );
      }

      const { data, error } = await supabase
        .from("patient_weight_entries")
        .insert({
          tenant_id: authResult.patient.tenant_id,
          patient_id: authResult.patient.id,
          weight_value: weightValue,
          weight_unit: weightUnit,
          weighed_at: weighedAt,
        })
        .select(
          "id, weight_value, weight_unit, weighed_at, created_at, updated_at",
        )
        .single();

      if (error) {
        console.error("Weight insert error:", error);
        return errorResponse(
          "INSERT_ERROR",
          "Failed to save weight entry",
          500,
        );
      }

      return jsonResponse({ data }, 201);
    }

    if (req.method === "POST" && path === "/body_measurement_tracker") {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      let payload: Record<string, unknown>;
      try {
        payload = (await req.json()) as Record<string, unknown>;
      } catch (_error) {
        return errorResponse(
          "INVALID_JSON",
          "Request body must be valid JSON",
          400,
        );
      }

      const measurementFields = [
        "chest_inches",
        "waist_inches",
        "hips_inches",
        "arms_inches",
      ] as const;
      const measurements: Record<(typeof measurementFields)[number], number> = {
        chest_inches: 0,
        waist_inches: 0,
        hips_inches: 0,
        arms_inches: 0,
      };

      for (const field of measurementFields) {
        const value = Number(payload[field]);
        if (!Number.isFinite(value) || value <= 0) {
          return errorResponse(
            "VALIDATION_ERROR",
            `${field} must be a positive number`,
            400,
          );
        }
        measurements[field] = value;
      }

      const measuredAtRaw = typeof payload.measured_at === "string"
        ? payload.measured_at
        : typeof payload.recorded_at === "string"
        ? payload.recorded_at
        : typeof payload.measurement_date === "string"
        ? payload.measurement_date
        : "";

      const measuredAt = measuredAtRaw || dateTime().toISOString();
      if (measuredAtRaw && Number.isNaN(Date.parse(measuredAtRaw))) {
        return errorResponse(
          "VALIDATION_ERROR",
          "measured_at must be a valid ISO date",
          400,
        );
      }

      const { data, error } = await supabase
        .from("patient_body_measurement_entries")
        .insert({
          tenant_id: authResult.patient.tenant_id,
          patient_id: authResult.patient.id,
          chest_inches: measurements.chest_inches,
          waist_inches: measurements.waist_inches,
          hips_inches: measurements.hips_inches,
          arms_inches: measurements.arms_inches,
          measured_at: measuredAt,
        })
        .select(bodyMeasurementEntrySelect)
        .single();

      if (error) {
        console.error("Body measurement insert error:", error);
        return errorResponse(
          "INSERT_ERROR",
          "Failed to save body measurement entry",
          500,
        );
      }

      return jsonResponse(
        {
          data: serializeBodyMeasurementEntry(data as BodyMeasurementEntryRow),
        },
        201,
      );
    }

    if (req.method === "POST" && path === "/mood_tracker") {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      let payload: Record<string, unknown>;
      try {
        payload = (await req.json()) as Record<string, unknown>;
      } catch (_error) {
        return errorResponse(
          "INVALID_JSON",
          "Request body must be valid JSON",
          400,
        );
      }

      const moodIds = Array.isArray(payload.mood_ids)
        ? payload.mood_ids
          .filter((value) => typeof value === "string")
          .map((value) => value.trim())
          .filter(Boolean)
        : [];

      const singleMoodId = typeof payload.mood_id === "string"
        ? payload.mood_id.trim()
        : "";
      const resolvedMoodIds = moodIds.length > 0
        ? moodIds
        : singleMoodId
        ? [singleMoodId]
        : [];

      const recordedAtRaw = typeof payload.recorded_at === "string"
        ? payload.recorded_at
        : typeof payload.logged_at === "string"
        ? payload.logged_at
        : "";

      const recordedAt = recordedAtRaw || dateTime().toISOString();
      if (recordedAtRaw && Number.isNaN(Date.parse(recordedAtRaw))) {
        return errorResponse(
          "VALIDATION_ERROR",
          "recorded_at must be a valid ISO date",
          400,
        );
      }

      if (resolvedMoodIds.length === 0) {
        return errorResponse("VALIDATION_ERROR", "mood_ids is required", 400);
      }

      const { data: definitions, error: definitionsError } = await supabase
        .from("tenant_mood_change_definitions")
        .select("id, label, is_active")
        .eq("tenant_id", authResult.patient.tenant_id)
        .in("id", resolvedMoodIds);

      if (definitionsError) {
        console.error("Mood definitions fetch error:", definitionsError);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch mood definitions",
          500,
        );
      }

      const activeDefinitions = (definitions ?? []).filter((definition) =>
        definition.is_active
      );
      const definitionMap = new Map(
        activeDefinitions.map((definition) => [definition.id, definition]),
      );
      const missingIds = resolvedMoodIds.filter((id) => !definitionMap.has(id));

      if (missingIds.length > 0) {
        return errorResponse(
          "VALIDATION_ERROR",
          "One or more mood_ids are invalid or inactive",
          400,
        );
      }

      const insertPayload = resolvedMoodIds.map((id) => {
        const definition = definitionMap.get(id)!;
        return {
          tenant_id: authResult.patient.tenant_id,
          patient_id: authResult.patient.id,
          mood_change_definition_id: definition.id,
          mood_change_label: definition.label,
          recorded_at: recordedAt,
        };
      });

      const { data, error } = await supabase
        .from("patient_mood_change_entries")
        .insert(insertPayload)
        .select(
          "id, mood_change_definition_id, mood_change_label, recorded_at, created_at, updated_at",
        );

      if (error) {
        console.error("Mood insert error:", error);
        return errorResponse("INSERT_ERROR", "Failed to save mood entry", 500);
      }

      return jsonResponse({ data }, 201);
    }

    if (req.method === "POST" && path === "/activity_tracker") {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      let payload: Record<string, unknown>;
      try {
        payload = (await req.json()) as Record<string, unknown>;
      } catch (_error) {
        return errorResponse(
          "INVALID_JSON",
          "Request body must be valid JSON",
          400,
        );
      }

      const activityIds = Array.isArray(payload.activity_ids)
        ? payload.activity_ids
          .filter((value) => typeof value === "string")
          .map((value) => value.trim())
          .filter(Boolean)
        : [];

      const singleActivityId = typeof payload.activity_id === "string"
        ? payload.activity_id.trim()
        : "";
      const resolvedActivityIds = activityIds.length > 0
        ? activityIds
        : singleActivityId
        ? [singleActivityId]
        : [];

      const recordedAtRaw = typeof payload.recorded_at === "string"
        ? payload.recorded_at
        : typeof payload.logged_at === "string"
        ? payload.logged_at
        : "";

      const recordedAt = recordedAtRaw || dateTime().toISOString();
      if (recordedAtRaw && Number.isNaN(Date.parse(recordedAtRaw))) {
        return errorResponse(
          "VALIDATION_ERROR",
          "recorded_at must be a valid ISO date",
          400,
        );
      }

      if (resolvedActivityIds.length === 0) {
        return errorResponse(
          "VALIDATION_ERROR",
          "activity_ids is required",
          400,
        );
      }

      const { data: definitions, error: definitionsError } = await supabase
        .from("tenant_activity_definitions")
        .select("id, label, is_active")
        .eq("tenant_id", authResult.patient.tenant_id)
        .in("id", resolvedActivityIds);

      if (definitionsError) {
        console.error("Activity definitions fetch error:", definitionsError);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch activity definitions",
          500,
        );
      }

      const activeDefinitions = (definitions ?? []).filter((definition) =>
        definition.is_active
      );
      const definitionMap = new Map(
        activeDefinitions.map((definition) => [definition.id, definition]),
      );
      const missingIds = resolvedActivityIds.filter((id) =>
        !definitionMap.has(id)
      );

      if (missingIds.length > 0) {
        return errorResponse(
          "VALIDATION_ERROR",
          "One or more activity_ids are invalid or inactive",
          400,
        );
      }

      const insertPayload = resolvedActivityIds.map((id) => {
        const definition = definitionMap.get(id)!;
        return {
          tenant_id: authResult.patient.tenant_id,
          patient_id: authResult.patient.id,
          activity_definition_id: definition.id,
          activity_label: definition.label,
          recorded_at: recordedAt,
        };
      });

      const { data, error } = await supabase
        .from("patient_activity_entries")
        .insert(insertPayload)
        .select(
          "id, activity_definition_id, activity_label, recorded_at, created_at, updated_at",
        );

      if (error) {
        console.error("Activity insert error:", error);
        return errorResponse(
          "INSERT_ERROR",
          "Failed to save activity entry",
          500,
        );
      }

      return jsonResponse({ data }, 201);
    }

    if (req.method === "POST" && path === "/energy_tracker") {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      let payload: Record<string, unknown>;
      try {
        payload = (await req.json()) as Record<string, unknown>;
      } catch (_error) {
        return errorResponse(
          "INVALID_JSON",
          "Request body must be valid JSON",
          400,
        );
      }

      const energyRaw = payload.energy_value ?? payload.energy_score ??
        payload.energy_level ?? payload.energy;
      const energyValue = Number(energyRaw);
      if (
        !Number.isInteger(energyValue) || energyValue < 1 || energyValue > 10
      ) {
        return errorResponse(
          "VALIDATION_ERROR",
          "energy_value must be an integer between 1 and 10",
          400,
        );
      }

      const recordedAtRaw = typeof payload.recorded_at === "string"
        ? payload.recorded_at
        : typeof payload.logged_at === "string"
        ? payload.logged_at
        : "";

      const recordedAt = recordedAtRaw || dateTime().toISOString();
      if (recordedAtRaw && Number.isNaN(Date.parse(recordedAtRaw))) {
        return errorResponse(
          "VALIDATION_ERROR",
          "recorded_at must be a valid ISO date",
          400,
        );
      }

      const { data, error } = await supabase
        .from("patient_energy_entries")
        .insert({
          tenant_id: authResult.patient.tenant_id,
          patient_id: authResult.patient.id,
          energy_value: energyValue,
          recorded_at: recordedAt,
        })
        .select("id, energy_value, recorded_at, created_at, updated_at")
        .single();

      if (error) {
        console.error("Energy insert error:", error);
        return errorResponse(
          "INSERT_ERROR",
          "Failed to save energy entry",
          500,
        );
      }

      return jsonResponse({ data }, 201);
    }

    if (req.method === "POST" && path === "/symptom_tracker") {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      let payload: Record<string, unknown>;
      try {
        payload = (await req.json()) as Record<string, unknown>;
      } catch (_error) {
        return errorResponse(
          "INVALID_JSON",
          "Request body must be valid JSON",
          400,
        );
      }

      const symptomIds = Array.isArray(payload.symptom_ids)
        ? payload.symptom_ids
          .filter((value) => typeof value === "string")
          .map((value) => value.trim())
          .filter(Boolean)
        : [];

      const singleSymptomId = typeof payload.symptom_id === "string"
        ? payload.symptom_id.trim()
        : "";
      const resolvedSymptomIds = symptomIds.length > 0
        ? symptomIds
        : singleSymptomId
        ? [singleSymptomId]
        : [];

      const recordedAtRaw = typeof payload.recorded_at === "string"
        ? payload.recorded_at
        : typeof payload.logged_at === "string"
        ? payload.logged_at
        : "";

      const recordedAt = recordedAtRaw || dateTime().toISOString();
      if (recordedAtRaw && Number.isNaN(Date.parse(recordedAtRaw))) {
        return errorResponse(
          "VALIDATION_ERROR",
          "recorded_at must be a valid ISO date",
          400,
        );
      }

      if (resolvedSymptomIds.length > 0) {
        const { data: definitions, error: definitionsError } = await supabase
          .from("tenant_symptom_definitions")
          .select("id, label, is_active")
          .eq("tenant_id", authResult.patient.tenant_id)
          .in("id", resolvedSymptomIds);

        if (definitionsError) {
          console.error("Symptom definitions fetch error:", definitionsError);
          return errorResponse(
            "FETCH_ERROR",
            "Failed to fetch symptom definitions",
            500,
          );
        }

        const activeDefinitions = (definitions ?? []).filter((definition) =>
          definition.is_active
        );
        const definitionMap = new Map(
          activeDefinitions.map((definition) => [definition.id, definition]),
        );
        const missingIds = resolvedSymptomIds.filter((id) =>
          !definitionMap.has(id)
        );

        if (missingIds.length > 0) {
          return errorResponse(
            "VALIDATION_ERROR",
            "One or more symptom_ids are invalid or inactive",
            400,
          );
        }

        const insertPayload = resolvedSymptomIds.map((id) => {
          const definition = definitionMap.get(id)!;
          return {
            tenant_id: authResult.patient.tenant_id,
            patient_id: authResult.patient.id,
            symptom_definition_id: definition.id,
            symptom_label: definition.label,
            recorded_at: recordedAt,
          };
        });

        const { data, error } = await supabase
          .from("patient_symptom_entries")
          .insert(insertPayload)
          .select(
            "id, symptom_definition_id, symptom_label, recorded_at, created_at, updated_at",
          );

        if (error) {
          console.error("Symptom insert error:", error);
          return errorResponse(
            "INSERT_ERROR",
            "Failed to save symptom entry",
            500,
          );
        }

        return jsonResponse({ data }, 201);
      }

      const symptomLabelRaw = typeof payload.symptom_label === "string"
        ? payload.symptom_label
        : typeof payload.symptom === "string"
        ? payload.symptom
        : typeof payload.symptom_name === "string"
        ? payload.symptom_name
        : "";
      const symptomLabel = symptomLabelRaw.trim();

      if (!symptomLabel) {
        return errorResponse(
          "VALIDATION_ERROR",
          "symptom_label or symptom_ids is required",
          400,
        );
      }

      const { data, error } = await supabase
        .from("patient_symptom_entries")
        .insert({
          tenant_id: authResult.patient.tenant_id,
          patient_id: authResult.patient.id,
          symptom_label: symptomLabel,
          recorded_at: recordedAt,
        })
        .select(
          "id, symptom_definition_id, symptom_label, recorded_at, created_at, updated_at",
        )
        .single();

      if (error) {
        console.error("Symptom insert error:", error);
        return errorResponse(
          "INSERT_ERROR",
          "Failed to save symptom entry",
          500,
        );
      }

      return jsonResponse({ data }, 201);
    }

    if (
      req.method === "DELETE" && path.match(/^\/weight_tracker\/[a-f0-9-]+$/)
    ) {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      const entryId = path.split("/")[2];

      const { data, error } = await supabase
        .from("patient_weight_entries")
        .delete()
        .eq("id", entryId)
        .eq("patient_id", authResult.patient.id)
        .select("id")
        .maybeSingle();

      if (error) {
        console.error("Weight delete error:", error);
        return errorResponse(
          "DELETE_ERROR",
          "Failed to delete weight entry",
          500,
        );
      }

      if (!data) {
        return errorResponse("NOT_FOUND", "Weight entry not found", 404);
      }

      return jsonResponse({ message: "Weight entry deleted", data }, 200);
    }

    if (
      req.method === "DELETE" &&
      path.match(/^\/body_measurement_tracker\/[a-f0-9-]+$/)
    ) {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      const entryId = path.split("/")[2];

      const { data, error } = await supabase
        .from("patient_body_measurement_entries")
        .delete()
        .eq("id", entryId)
        .eq("patient_id", authResult.patient.id)
        .select("id")
        .maybeSingle();

      if (error) {
        console.error("Body measurement delete error:", error);
        return errorResponse(
          "DELETE_ERROR",
          "Failed to delete body measurement entry",
          500,
        );
      }

      if (!data) {
        return errorResponse(
          "NOT_FOUND",
          "Body measurement entry not found",
          404,
        );
      }

      return jsonResponse({
        message: "Body measurement entry deleted",
        data,
      }, 200);
    }

    return errorResponse(
      "NOT_FOUND",
      `Endpoint ${req.method} ${path} not found`,
      404,
    );
  } catch (error) {
    console.error("Healthtracking API error:", error);
    return errorResponse("SERVER_ERROR", "Unexpected server error", 500);
  }
});
