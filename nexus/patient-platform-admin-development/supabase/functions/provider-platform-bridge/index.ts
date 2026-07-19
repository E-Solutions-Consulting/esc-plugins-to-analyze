import {
  fetchOrderById,
  fetchTenantIntegrationForTenantByKey,
  getCorsHeaders,
  getSupabaseAdminClient,
  getSupabaseAuthClient,
  jsonResponse,
  resolveOrderProviderPlatformLink,
  userHasOrderAccess,
} from "./common.ts";
import {
  handleTelegraAnswerLocationRequest,
  handleTelegraPatientQuestionnaireRequest,
  handleTelegraQuestionnairesRequest,
  handleTelegraResolveProductVariationRequest,
  handleTelegraSymptomsRequest,
  handleTelegraUpdatePatientProfileRequest,
} from "./telegra.ts";
import {
  handleJotformSubmissionProcessing,
  handleMdiMedicalQuestionsRequest,
  handleMdiPatientQuestionnaireRequest,
  handleMdiQuestionnairesRequest,
  handleMdiUpdatePatientProfileRequest,
} from "./mdi.ts";
import {
  handleJotformFormValidationRequest,
  handleJotformFormWebhooksRequest,
  handleJotformFormWebhookUpdateRequest,
  handleJotformPatientQuestionnaireGenerationRequest,
  handleJotformWebhookSyncRequest,
  JOTFORM_INTEGRATION_KEY,
  type JotformSubmissionQuestionnaireType,
  resolvePatientQuestionnairePresentation,
} from "./jotform.ts";
import {
  normalizeProviderPlatformBridgePath,
  normalizeProviderPlatformIdentifier,
} from "./helpers.ts";

Deno.serve(async (req) => {
  const requestId = crypto.randomUUID();

  if (req.method === "OPTIONS") {
    return new Response(null, { status: 204, headers: getCorsHeaders(req) });
  }

  try {
    const url = new URL(req.url);
    const path = normalizeProviderPlatformBridgePath(url.pathname);
    const authHeader = req.headers.get("Authorization");

    if (!["GET", "POST", "PUT"].includes(req.method)) {
      return jsonResponse(
        req,
        {
          error: "Method not allowed",
          message:
            "Use GET, POST, or PUT for provider platform bridge requests",
        },
        405,
      );
    }

    // ---------------------------------------------------------------------------
    // Internal service-to-service endpoint (service role key auth)
    // ---------------------------------------------------------------------------
    const processJotformSubmissionPathMatch = req.method === "POST"
      ? path.match(
        /^\/internal\/order\/([a-f0-9-]+)\/process-jotform-submission$/i,
      )
      : null;

    if (processJotformSubmissionPathMatch) {
      const internalOrderId = processJotformSubmissionPathMatch[1]?.trim();
      if (!internalOrderId) {
        return jsonResponse(
          req,
          {
            error: "Missing orderId",
            message: "Provide orderId in the request path",
          },
          400,
        );
      }

      const supabaseServiceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY");
      const token = authHeader?.replace(/^Bearer\s+/i, "").trim();
      if (!supabaseServiceKey || !token || token !== supabaseServiceKey) {
        return jsonResponse(
          req,
          {
            error: "Unauthorized",
            message: "Invalid service key for internal endpoint",
          },
          401,
        );
      }

      let body: Record<string, unknown> | null = null;
      try {
        body = (await req.json()) as Record<string, unknown>;
      } catch {
        body = null;
      }

      const submissionId =
        typeof body?.submissionId === "string" && body.submissionId.trim()
          ? body.submissionId.trim()
          : null;
      const questionnaireTypeRaw = typeof body?.questionnaireType === "string"
        ? body.questionnaireType.trim()
        : null;
      const questionnaireType = questionnaireTypeRaw ===
            "patient_questionnaire" ||
          questionnaireTypeRaw === "medical_questionnaire"
        ? questionnaireTypeRaw as JotformSubmissionQuestionnaireType
        : null;

      if (!submissionId) {
        return jsonResponse(
          req,
          {
            error: "Missing submissionId",
            message: "Provide submissionId in the request body",
          },
          400,
        );
      }

      if (!questionnaireType) {
        return jsonResponse(
          req,
          {
            error: "Missing questionnaireType",
            message:
              "Provide questionnaireType as patient_questionnaire or medical_questionnaire in the request body",
          },
          400,
        );
      }

      const supabase = getSupabaseAdminClient(requestId);
      return await handleJotformSubmissionProcessing({
        supabase,
        req,
        orderId: internalOrderId,
        submissionId,
        questionnaireType,
        requestId,
      });
    }

    // ---------------------------------------------------------------------------
    // Public endpoints (user JWT auth)
    // ---------------------------------------------------------------------------

    const questionnairePathMatch = req.method === "GET"
      ? path.match(/^\/get-questionnaires\/([a-f0-9-]+)$/i)
      : null;
    const patientQuestionnairePathMatch = req.method === "GET"
      ? path.match(/^\/get-patient-questionnaire\/([a-f0-9-]+)$/i)
      : null;
    const symptomsPathMatch = req.method === "GET" && path === "/symptoms";
    const updatePatientProfilePathMatch = req.method === "POST"
      ? path.match(/^\/order\/([a-f0-9-]+)\/patient-profile$/i)
      : null;
    const answerLocationPathMatch = req.method === "POST"
      ? path.match(/^\/order\/([a-f0-9-]+)\/questionnaire-answer-location$/i)
      : null;
    const mdiMedicalQuestionsPathMatch = req.method === "POST"
      ? path.match(/^\/order\/([a-f0-9-]+)\/mdi-medical-questions$/i)
      : null;
    const resolveProductVariationPathMatch = req.method === "POST" &&
      path === "/telegra-product-variation";
    const validateJotformFormPathMatch = req.method === "POST" &&
      path === "/jotform-form-validation";
    const jotformFormWebhooksPathMatch =
      (req.method === "POST" || req.method === "PUT") &&
      path === "/jotform-form-webhooks";
    const jotformWebhookSyncPathMatch = req.method === "POST" &&
      path === "/jotform-webhook-sync";
    const generateJotformPatientQuestionnairePathMatch = req.method ===
        "POST" &&
      path === "/jotform-patient-questionnaire";

    if (
      !questionnairePathMatch &&
      !symptomsPathMatch &&
      !patientQuestionnairePathMatch &&
      !updatePatientProfilePathMatch &&
      !answerLocationPathMatch &&
      !mdiMedicalQuestionsPathMatch &&
      !resolveProductVariationPathMatch &&
      !validateJotformFormPathMatch &&
      !jotformFormWebhooksPathMatch &&
      !jotformWebhookSyncPathMatch &&
      !generateJotformPatientQuestionnairePathMatch
    ) {
      return jsonResponse(
        req,
        {
          error: "Not found",
          message:
            "Supported paths: GET /get-questionnaires/:orderId, GET /get-patient-questionnaire/:orderId, GET /symptoms, POST /order/:orderId/patient-profile, POST /order/:orderId/questionnaire-answer-location, POST /telegra-product-variation, POST /jotform-form-validation, POST/PUT /jotform-form-webhooks, POST /jotform-webhook-sync, POST /jotform-patient-questionnaire, POST /order/:orderId/mdi-medical-questions",
        },
        404,
      );
    }

    const orderId = questionnairePathMatch?.[1]?.trim() ||
      patientQuestionnairePathMatch?.[1]?.trim() ||
      updatePatientProfilePathMatch?.[1]?.trim() ||
      answerLocationPathMatch?.[1]?.trim() ||
      mdiMedicalQuestionsPathMatch?.[1]?.trim() ||
      null;

    if (!symptomsPathMatch && !orderId) {
      if (
        resolveProductVariationPathMatch || validateJotformFormPathMatch ||
        jotformFormWebhooksPathMatch ||
        jotformWebhookSyncPathMatch ||
        generateJotformPatientQuestionnairePathMatch
      ) {
        // This route is not order-scoped.
      } else {
        return jsonResponse(
          req,
          {
            error: "Missing orderId",
            message: "Provide orderId in the request path",
          },
          400,
        );
      }
    }

    if (!authHeader) {
      return jsonResponse(
        req,
        {
          error: "Unauthorized",
          message: "Missing authorization header",
        },
        401,
      );
    }

    const supabaseAuth = getSupabaseAuthClient(authHeader);
    const {
      data: { user },
      error: authError,
    } = await supabaseAuth.auth.getUser();

    if (authError || !user) {
      return jsonResponse(
        req,
        {
          error: "Unauthorized",
          message: "Invalid or expired user token",
        },
        401,
      );
    }

    const supabase = getSupabaseAdminClient(requestId);

    if (symptomsPathMatch) {
      return await handleTelegraSymptomsRequest({
        supabase,
        req,
        url,
        authUserId: user.id,
        requestId,
      });
    }

    if (req.method === "POST" && resolveProductVariationPathMatch) {
      return await handleTelegraResolveProductVariationRequest({
        supabase,
        req,
        authUserId: user.id,
        requestId,
      });
    }

    if (req.method === "POST" && validateJotformFormPathMatch) {
      return await handleJotformFormValidationRequest({
        supabase,
        req,
        authUserId: user.id,
        requestId,
      });
    }

    if (req.method === "POST" && jotformFormWebhooksPathMatch) {
      return await handleJotformFormWebhooksRequest({
        supabase,
        req,
        authUserId: user.id,
        requestId,
      });
    }

    if (req.method === "PUT" && jotformFormWebhooksPathMatch) {
      return await handleJotformFormWebhookUpdateRequest({
        supabase,
        req,
        authUserId: user.id,
        requestId,
      });
    }

    if (req.method === "POST" && jotformWebhookSyncPathMatch) {
      return await handleJotformWebhookSyncRequest({
        supabase,
        req,
        authUserId: user.id,
        requestId,
      });
    }

    if (
      req.method === "POST" && generateJotformPatientQuestionnairePathMatch
    ) {
      return await handleJotformPatientQuestionnaireGenerationRequest({
        supabase,
        req,
        authUserId: user.id,
        requestId,
      });
    }

    if (req.method === "GET" && patientQuestionnairePathMatch) {
      const order = await fetchOrderById(supabase, orderId!);
      if (!order) {
        return jsonResponse(
          req,
          {
            error: "Order not found",
            message: `No order found for id ${orderId}`,
          },
          404,
        );
      }

      const hasOrderAccess = await userHasOrderAccess({
        supabase,
        authUserId: user.id,
        order,
      });
      if (!hasOrderAccess) {
        return jsonResponse(
          req,
          {
            error: "Forbidden",
            message: "You do not have access to the requested order",
          },
          403,
        );
      }

      const {
        providerPlatformLink,
        providerIntegration,
        providerName,
        providerIntegrationKey,
      } = await resolveOrderProviderPlatformLink({
        supabase,
        order,
      });

      const normalizedProviderName = normalizeProviderPlatformIdentifier(
        providerIntegrationKey || providerName,
      );

      const jotformIntegration = await fetchTenantIntegrationForTenantByKey({
        supabase,
        tenantId: order.tenant_id,
        integrationKey: JOTFORM_INTEGRATION_KEY,
      });
      const patientQuestionnairePresentation =
        resolvePatientQuestionnairePresentation({
          order,
          providerKey: providerIntegrationKey ||
            order.provider_platform_integration_key,
          providerIntegration,
          jotformIntegration,
        });

      if (patientQuestionnairePresentation) {
        return jsonResponse(req, {
          orderId: order.id,
          provider: normalizedProviderName || providerName ||
            providerIntegrationKey || null,
          providerOrderId: providerPlatformLink?.provider_order_id || null,
          questionnairePresentation: patientQuestionnairePresentation,
          patientQuestionnaire: null,
          symptomsCount: 0,
          symptomsQuestionCount: 0,
        });
      }

      if (normalizedProviderName === "mdintegrations") {
        return await handleMdiPatientQuestionnaireRequest({
          supabase,
          req,
          order,
          providerPlatformLink,
        });
      }

      return await handleTelegraPatientQuestionnaireRequest({
        supabase,
        req,
        orderId: orderId!,
        requestId,
        authUserId: user.id,
      });
    }

    if (req.method === "POST" && updatePatientProfilePathMatch) {
      const order = await fetchOrderById(supabase, orderId!);
      if (!order) {
        return jsonResponse(
          req,
          {
            error: "Order not found",
            message: `No order found for id ${orderId}`,
          },
          404,
        );
      }

      const hasOrderAccess = await userHasOrderAccess({
        supabase,
        authUserId: user.id,
        order,
      });
      if (!hasOrderAccess) {
        return jsonResponse(
          req,
          {
            error: "Forbidden",
            message: "You do not have access to the requested order",
          },
          403,
        );
      }

      const { providerPlatformLink, providerName, providerIntegrationKey } =
        await resolveOrderProviderPlatformLink({
          supabase,
          order,
        });

      const normalizedProviderName = normalizeProviderPlatformIdentifier(
        providerIntegrationKey || providerName,
      );

      if (normalizedProviderName === "mdintegrations") {
        return await handleMdiUpdatePatientProfileRequest({
          supabase,
          req,
          order,
          providerPlatformLink,
          requestId,
        });
      }

      return await handleTelegraUpdatePatientProfileRequest({
        supabase,
        req,
        orderId: orderId!,
        requestId,
        authUserId: user.id,
      });
    }

    if (req.method === "POST" && answerLocationPathMatch) {
      return await handleTelegraAnswerLocationRequest({
        supabase,
        req,
        orderId: orderId!,
        requestId,
        authUserId: user.id,
      });
    }

    if (req.method === "POST" && mdiMedicalQuestionsPathMatch) {
      const order = await fetchOrderById(supabase, orderId!);
      if (!order) {
        return jsonResponse(
          req,
          {
            error: "Order not found",
            message: `No order found for id ${orderId}`,
          },
          404,
        );
      }

      const hasOrderAccess = await userHasOrderAccess({
        supabase,
        authUserId: user.id,
        order,
      });
      if (!hasOrderAccess) {
        return jsonResponse(
          req,
          {
            error: "Forbidden",
            message: "You do not have access to the requested order",
          },
          403,
        );
      }

      const { providerPlatformLink, providerName, providerIntegrationKey } =
        await resolveOrderProviderPlatformLink({
          supabase,
          order,
        });

      const normalizedProviderName = normalizeProviderPlatformIdentifier(
        providerIntegrationKey || providerName,
      );

      if (normalizedProviderName !== "mdintegrations") {
        return jsonResponse(
          req,
          {
            error: "Provider platform mismatch",
            message:
              "The order is not linked to MD Integrations for mdi-medical-questions",
          },
          409,
        );
      }

      return await handleMdiMedicalQuestionsRequest({
        supabase,
        req,
        order,
        providerPlatformLink,
        requestId,
      });
    }

    const order = await fetchOrderById(supabase, orderId!);
    if (!order) {
      return jsonResponse(
        req,
        {
          error: "Order not found",
          message: `No order found for id ${orderId}`,
        },
        404,
      );
    }

    const hasOrderAccess = await userHasOrderAccess({
      supabase,
      authUserId: user.id,
      order,
    });
    if (!hasOrderAccess) {
      return jsonResponse(
        req,
        {
          error: "Forbidden",
          message: "You do not have access to the requested order",
        },
        403,
      );
    }

    const { providerPlatformLink, providerName, providerIntegrationKey } =
      await resolveOrderProviderPlatformLink({
        supabase,
        order,
      });

    const normalizedProviderName = normalizeProviderPlatformIdentifier(
      providerIntegrationKey || providerName,
    );

    if (normalizedProviderName === "mdintegrations") {
      return await handleMdiQuestionnairesRequest({
        supabase,
        req,
        order,
        providerPlatformLink,
        requestId,
      });
    }

    return await handleTelegraQuestionnairesRequest({
      supabase,
      req,
      orderId: orderId!,
      requestId,
      authUserId: user.id,
    });
  } catch (error) {
    console.error("Provider platform bridge request failed", {
      requestId,
      error: error instanceof Error ? error.message : String(error),
    });

    return jsonResponse(
      req,
      {
        error: "Internal server error",
        message: error instanceof Error ? error.message : "Unknown error",
        requestId,
      },
      500,
    );
  }
});
