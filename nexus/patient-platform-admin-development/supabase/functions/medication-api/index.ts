import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import {
  checkRateLimit,
  getCorsHeaders,
  isPlanBlockingMedicationEligibility,
} from "./helpers.ts";

Deno.serve(async (req): Promise<Response> => {
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
  if (path.startsWith("/medication-api")) {
    path = path.slice("/medication-api".length);
  }
  if (!path.startsWith("/")) path = `/${path}`;
  if (path.length > 1 && path.endsWith("/")) path = path.slice(0, -1);

  const supabaseUrl = Deno.env.get("SUPABASE_URL");
  const supabaseAnonKey = Deno.env.get("SUPABASE_ANON_KEY");
  const authHeader = req.headers.get("Authorization");
  const supabaseServiceRoleKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");

  if (!supabaseUrl || !supabaseAnonKey) {
    return errorResponse("SERVER_ERROR", "Missing Supabase configuration", 500);
  }

  const supabase = createClient(supabaseUrl, supabaseAnonKey, {
    global: {
      headers: authHeader ? { Authorization: authHeader } : {},
    },
  });

  const supabaseAdmin = supabaseServiceRoleKey
    ? createClient(supabaseUrl, supabaseServiceRoleKey, {
      auth: {
        autoRefreshToken: false,
        persistSession: false,
      },
    })
    : null;

  type AuthenticatedPatientResult =
    | {
      user: unknown;
      patient: {
        id: string;
        tenant_id: string;
      };
    }
    | {
      error: Response;
    };

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

    return { user, patient };
  }

  try {
    if (req.method === "GET" && path === "/medications") {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      if (!supabaseAdmin) {
        return errorResponse(
          "SERVER_ERROR",
          "Missing Supabase configuration",
          500,
        );
      }

      const { data: orders, error: ordersError } = await supabaseAdmin
        .from("orders")
        .select("product_id")
        .eq("patient_id", authResult.patient.id)
        .not("product_id", "is", null);

      if (ordersError) {
        console.error("Orders fetch error:", ordersError);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch patient orders",
          500,
        );
      }

      const productIds = Array.from(
        new Set(
          (orders ?? []).map((order) => order.product_id).filter(Boolean),
        ),
      );

      if (productIds.length === 0) {
        return jsonResponse({ data: [] });
      }

      const { data: productMedications, error: productMedicationsError } =
        await supabaseAdmin
          .from("product_medications")
          .select(
            "product_id, medication_id, medication:medications(id, title, description, image_url, provider_sku, form, is_enabled)",
          )
          .in("product_id", productIds);

      if (productMedicationsError) {
        console.error(
          "Product medications fetch error:",
          productMedicationsError,
        );
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch medications for patient",
          500,
        );
      }

      const medicationMap = new Map<
        string,
        {
          id: string;
          title: string;
          description: string | null;
          image_url: string | null;
          provider_sku: string | null;
          form: string | null;
          is_enabled: boolean;
        }
      >();

      for (const entry of productMedications ?? []) {
        const medication = entry.medication as unknown as {
          id: string;
          title: string;
          description: string | null;
          image_url: string | null;
          provider_sku: string | null;
          form: string | null;
          is_enabled: boolean;
        } | null;

        if (!medication) continue;
        medicationMap.set(medication.id, medication);
      }

      const medicationIds = Array.from(medicationMap.keys());

      const { data: capabilityAssignments, error: capabilityError } =
        await supabaseAdmin
          .from("medication_capability_assignments")
          .select(
            "medication_id, capability:medication_capabilities(id, key, name, description, is_active, display_order)",
          )
          .in("medication_id", medicationIds);

      if (capabilityError) {
        console.error("Capability fetch error:", capabilityError);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch medication capabilities",
          500,
        );
      }

      const capabilitiesByMedication = new Map<
        string,
        Array<{
          id: string;
          key: string;
          name: string;
          description: string | null;
          is_active: boolean;
          display_order: number;
        }>
      >();

      for (const assignment of capabilityAssignments ?? []) {
        const capability = assignment.capability as unknown as {
          id: string;
          key: string;
          name: string;
          description: string | null;
          is_active: boolean;
          display_order: number;
        } | null;

        if (!capability) continue;
        const list = capabilitiesByMedication.get(assignment.medication_id) ??
          [];
        list.push(capability);
        capabilitiesByMedication.set(assignment.medication_id, list);
      }

      const data = Array.from(medicationMap.values()).map((medication) => {
        const capabilities =
          capabilitiesByMedication.get(medication.id)?.sort((a, b) => {
            if (a.display_order !== b.display_order) {
              return a.display_order - b.display_order;
            }
            return a.name.localeCompare(b.name);
          }) ?? [];

        return {
          ...medication,
          capabilities,
        };
      });

      data.sort((a, b) => a.title.localeCompare(b.title));

      return jsonResponse({ data });
    }

    if (
      req.method === "GET" &&
      path.match(/^\/products\/[a-f0-9-]+\/eligibility$/)
    ) {
      const authResult = await getAuthenticatedPatient();
      if ("error" in authResult) return authResult.error;

      if (!supabaseAdmin) {
        return errorResponse(
          "SERVER_ERROR",
          "Missing Supabase configuration",
          500,
        );
      }

      const productId = path.split("/")[2];

      const { data: product, error: productError } = await supabaseAdmin
        .from("products")
        .select("id, name")
        .eq("id", productId)
        .eq("tenant_id", authResult.patient.tenant_id)
        .eq("is_enabled", true)
        .maybeSingle();

      if (productError) {
        console.error("Product fetch error:", productError);
        return errorResponse("FETCH_ERROR", "Failed to fetch product", 500);
      }

      if (!product) {
        return errorResponse(
          "PRODUCT_NOT_FOUND",
          "Product not found or not available",
          404,
        );
      }

      const {
        data: targetProductMedications,
        error: targetProductMedicationsError,
      } = await supabaseAdmin
        .from("product_medications")
        .select("medication_id, medication:medications(id, title)")
        .eq("product_id", product.id);

      if (targetProductMedicationsError) {
        console.error(
          "Target product medications fetch error:",
          targetProductMedicationsError,
        );
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch product medications",
          500,
        );
      }

      const targetMedications = (targetProductMedications ?? [])
        .map((entry) => ({
          id: entry.medication_id,
          title: (entry.medication as unknown as {
            id: string;
            title: string;
          } | null)?.title ?? null,
        }))
        .filter((medication) => Boolean(medication.id));

      const targetMedicationIds = Array.from(
        new Set(targetMedications.map((medication) => medication.id)),
      );

      if (targetMedicationIds.length === 0) {
        return jsonResponse({
          data: {
            product_id: product.id,
            product_name: product.name,
            is_eligible: true,
            message:
              "Product is eligible because it has no linked medications.",
            conflicting_plans: [],
            conflicting_medications: [],
          },
        });
      }

      const { data: patientPlans, error: patientPlansError } =
        await supabaseAdmin
          .from("subscriptions")
          .select(
            "id, product_id, status, expires_at, cancelled_at, product:products(id, name)",
          )
          .eq("tenant_id", authResult.patient.tenant_id)
          .eq("patient_id", authResult.patient.id)
          .not("product_id", "is", null);

      if (patientPlansError) {
        console.error("Patient plans fetch error:", patientPlansError);
        return errorResponse(
          "FETCH_ERROR",
          "Failed to fetch patient plans",
          500,
        );
      }

      const blockingPlans = (patientPlans ?? []).filter((plan) =>
        isPlanBlockingMedicationEligibility({
          status: plan.status,
          expires_at: plan.expires_at,
          cancelled_at: plan.cancelled_at,
        })
      );

      if (blockingPlans.length === 0) {
        return jsonResponse({
          data: {
            product_id: product.id,
            product_name: product.name,
            is_eligible: true,
            message:
              "Product is eligible because the patient has no active plans using the same medication.",
            conflicting_plans: [],
            conflicting_medications: [],
          },
        });
      }

      const blockingProductIds = Array.from(
        new Set(
          blockingPlans
            .map((plan) => plan.product_id)
            .filter((value): value is string =>
              typeof value === "string" && value.length > 0
            ),
        ),
      );

      const {
        data: blockingPlanMedications,
        error: blockingPlanMedicationsError,
      } = await supabaseAdmin
        .from("product_medications")
        .select("product_id, medication_id, medication:medications(id, title)")
        .in("product_id", blockingProductIds)
        .in("medication_id", targetMedicationIds);

      if (blockingPlanMedicationsError) {
        console.error(
          "Blocking plan medications fetch error:",
          blockingPlanMedicationsError,
        );
        return errorResponse(
          "FETCH_ERROR",
          "Failed to compare patient plans",
          500,
        );
      }

      const overlappingMedicationsByProductId = new Map<
        string,
        Array<{ id: string; title: string | null }>
      >();

      for (const entry of blockingPlanMedications ?? []) {
        const medicationsForProduct =
          overlappingMedicationsByProductId.get(entry.product_id) ?? [];
        const medication = entry.medication as unknown as {
          id: string;
          title: string;
        } | null;

        if (
          !medicationsForProduct.some((item) => item.id === entry.medication_id)
        ) {
          medicationsForProduct.push({
            id: entry.medication_id,
            title: medication?.title ?? null,
          });
        }

        overlappingMedicationsByProductId.set(
          entry.product_id,
          medicationsForProduct,
        );
      }

      const conflictingPlans = blockingPlans
        .filter((plan) => {
          const productId = typeof plan.product_id === "string"
            ? plan.product_id
            : "";
          return productId.length > 0 &&
            overlappingMedicationsByProductId.has(productId);
        })
        .map((plan) => {
          const planProductId = plan.product_id as string;
          const medications =
            overlappingMedicationsByProductId.get(planProductId) ?? [];
          return {
            id: plan.id,
            status: plan.status,
            expires_at: plan.expires_at,
            cancelled_at: plan.cancelled_at,
            product: {
              id: planProductId,
              name: (plan.product as unknown as {
                id: string;
                name: string;
              } | null)?.name ?? null,
            },
            medications,
          };
        });

      const conflictingMedications = Array.from(
        new Map(
          conflictingPlans
            .flatMap((plan) => plan.medications)
            .map((medication) => [medication.id, medication]),
        ).values(),
      );

      const isEligible = conflictingPlans.length === 0;

      return jsonResponse({
        data: {
          product_id: product.id,
          product_name: product.name,
          is_eligible: isEligible,
          message: isEligible
            ? "Product is eligible because the patient has no active plans using the same medication."
            : "Patient is not eligible because there is already an active plan using the same medication.",
          conflicting_plans: conflictingPlans,
          conflicting_medications: conflictingMedications,
        },
      });
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

    return errorResponse(
      "NOT_FOUND",
      `Endpoint ${req.method} ${path} not found`,
      404,
    );
  } catch (error) {
    console.error("Medication API error:", error);
    return errorResponse("SERVER_ERROR", "Unexpected server error", 500);
  }
});
