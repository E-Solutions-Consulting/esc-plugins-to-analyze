import { assertEquals } from "../_test/assert.ts";
import {
  buildJotformCreateFormParams,
  buildJotformCreateFormUrl,
  buildJotformEmbedBaseUrl,
  buildJotformFormUrl,
  buildJotformFormWebhooksUrl,
  buildJotformFormWebhookUrl,
  buildJotformQuestionsUrl,
  buildTelegraPatientQuestionnaireJotformQuestions,
  extractJotformFileUrls,
  getJotformSubmissionOrderId,
  JOTFORM_ORDER_ID_FIELD_NAME,
  JOTFORM_PROVIDER_KEY_FIELD_NAME,
  JOTFORM_QUESTIONNAIRE_TYPE_FIELD_NAME,
  JOTFORM_SAVE_AND_CONTINUE_FORM_PROPERTY,
  jotformQuestionsHaveOrderIdField,
  resolveJotformSubmissionQuestionnaireMatch,
  resolvePatientQuestionnairePresentation,
  resolveQuestionnairePresentation,
} from "./jotform.ts";

Deno.test("buildJotformQuestionsUrl appends encoded form questions path", () => {
  assertEquals(
    buildJotformQuestionsUrl("https://www.jotform.com/", "123 456"),
    "https://www.jotform.com/API/form/123%20456/questions",
  );
});

Deno.test("buildJotformQuestionsUrl preserves configured API paths", () => {
  assertEquals(
    buildJotformQuestionsUrl("https://www.jotform.com/api/", "123456"),
    "https://www.jotform.com/API/form/123456/questions",
  );
});

Deno.test("buildJotformQuestionsUrl normalizes configured API path casing", () => {
  assertEquals(
    buildJotformQuestionsUrl("https://www.jotform.com/API/", "123456"),
    "https://www.jotform.com/API/form/123456/questions",
  );
});

Deno.test("buildJotformQuestionsUrl adds api path to HIPAA custom domains", () => {
  assertEquals(
    buildJotformQuestionsUrl(
      "https://ahghipaa.jotform.com/",
      "261413104126038",
    ),
    "https://ahghipaa.jotform.com/API/form/261413104126038/questions",
  );
});

Deno.test("buildJotformQuestionsUrl does not add api path to api hosts", () => {
  assertEquals(
    buildJotformQuestionsUrl("https://api.jotform.com/", "123456"),
    "https://api.jotform.com/form/123456/questions",
  );
});

Deno.test("buildJotformQuestionsUrl preserves configured API host paths", () => {
  assertEquals(
    buildJotformQuestionsUrl("https://eu-api.jotform.com/v1/", "123456"),
    "https://eu-api.jotform.com/v1/form/123456/questions",
  );
});

Deno.test("buildJotformCreateFormUrl normalizes create form endpoint", () => {
  assertEquals(
    buildJotformCreateFormUrl("https://www.jotform.com/"),
    "https://www.jotform.com/API/form",
  );
  assertEquals(
    buildJotformCreateFormUrl("https://api.jotform.com/"),
    "https://api.jotform.com/form",
  );
});

Deno.test("buildJotformFormWebhooksUrl appends form webhook endpoint", () => {
  assertEquals(
    buildJotformFormWebhooksUrl("https://www.jotform.com/api/", "123456"),
    "https://www.jotform.com/API/form/123456/webhooks",
  );
});

Deno.test("buildJotformFormWebhookUrl appends individual webhook endpoint", () => {
  assertEquals(
    buildJotformFormWebhookUrl(
      "https://www.jotform.com/api/",
      "123456",
      "987",
    ),
    "https://www.jotform.com/API/form/123456/webhooks/987",
  );
});

Deno.test("buildJotformEmbedBaseUrl normalizes API paths and hosts", () => {
  assertEquals(
    buildJotformEmbedBaseUrl("https://ahghipaa.jotform.com/api/"),
    "https://ahghipaa.jotform.com",
  );
  assertEquals(
    buildJotformEmbedBaseUrl("https://api.jotform.com/"),
    "https://www.jotform.com",
  );
  assertEquals(
    buildJotformEmbedBaseUrl("https://ahghipaa-api.jotform.com/"),
    "https://ahghipaa.jotform.com",
  );
});

Deno.test("buildJotformFormUrl appends selected form id and order id", () => {
  assertEquals(
    buildJotformFormUrl({
      baseUrl: "https://ahghipaa.jotform.com/",
      formId: "261405426738055",
      orderId: "order-123",
    }),
    "https://ahghipaa.jotform.com/261405426738055?patient_platform_order_id=order-123",
  );
});

Deno.test("resolveQuestionnairePresentation selects new-order Jotform when configured", () => {
  const presentation = resolveQuestionnairePresentation({
    order: {
      id: "order-123",
      subscription_order_type: "initial",
    },
    providerKey: "md_integrations",
    productProviderPlatform: {
      jotform_new_order_questionnaire_id: "261405426738055",
      jotform_renewall_questionnaire_id: "261405426738066",
    },
    jotformIntegration: {
      is_enabled: true,
      settings: {
        api_url: "https://ahghipaa.jotform.com/",
        api_key: "secret",
      },
    },
  });

  assertEquals(presentation.type, "jotform");
  assertEquals(presentation.selectedQuestionnaire, "new_order");
  assertEquals(
    presentation.type === "jotform" ? presentation.jotform.formId : null,
    "261405426738055",
  );
  assertEquals(
    presentation.type === "jotform" ? presentation.jotform.formUrl : null,
    "https://ahghipaa.jotform.com/261405426738055?patient_platform_order_id=order-123&provider_key=md_integrations",
  );
});

Deno.test("resolveQuestionnairePresentation selects renewal Jotform when configured", () => {
  const presentation = resolveQuestionnairePresentation({
    order: {
      id: "order-123",
      subscription_order_type: "renewal",
    },
    providerKey: "md_integrations",
    productProviderPlatform: {
      jotform_new_order_questionnaire_id: "261405426738055",
      jotform_renewall_questionnaire_id: "261405426738066",
    },
    jotformIntegration: {
      is_enabled: true,
      settings: {
        api_url: "https://ahghipaa.jotform.com/",
        api_key: "secret",
      },
    },
  });

  assertEquals(presentation.type, "jotform");
  assertEquals(presentation.selectedQuestionnaire, "renewal");
  assertEquals(
    presentation.type === "jotform" ? presentation.jotform.formId : null,
    "261405426738066",
  );
  assertEquals(
    presentation.type === "jotform" ? presentation.jotform.formUrl : null,
    "https://ahghipaa.jotform.com/261405426738066?patient_platform_order_id=order-123&provider_key=md_integrations",
  );
});

Deno.test("resolveQuestionnairePresentation falls back to native when Jotform is incomplete", () => {
  const presentation = resolveQuestionnairePresentation({
    order: {
      id: "order-123",
      subscription_order_type: "renewal",
    },
    productProviderPlatform: {
      jotform_new_order_questionnaire_id: "261405426738055",
      jotform_renewall_questionnaire_id: null,
    },
    jotformIntegration: {
      is_enabled: true,
      settings: {
        api_url: "https://ahghipaa.jotform.com/",
        api_key: "secret",
      },
    },
  });

  assertEquals(presentation.type, "native");
  assertEquals(presentation.selectedQuestionnaire, "renewal");
  assertEquals(
    presentation.type === "native" ? presentation.reason : null,
    "jotform_form_id_not_configured",
  );
});

Deno.test("resolveQuestionnairePresentation: integration_mode='direct' forces native even when a Jotform form id is set", () => {
  const presentation = resolveQuestionnairePresentation({
    order: {
      id: "order-123",
      subscription_order_type: "initial",
    },
    providerKey: "md_integrations",
    productProviderPlatform: {
      jotform_new_order_questionnaire_id: "261405426738055",
      jotform_renewall_questionnaire_id: "261405426738066",
      integration_mode: "direct",
    },
    jotformIntegration: {
      is_enabled: true,
      settings: {
        api_url: "https://ahghipaa.jotform.com/",
        api_key: "secret",
      },
    },
  });

  assertEquals(presentation.type, "native");
  assertEquals(
    presentation.type === "native" ? presentation.reason : null,
    "integration_mode_direct",
  );
});

Deno.test("resolveQuestionnairePresentation: integration_mode='jotform' uses the configured Jotform form", () => {
  const presentation = resolveQuestionnairePresentation({
    order: {
      id: "order-123",
      subscription_order_type: "initial",
    },
    providerKey: "md_integrations",
    productProviderPlatform: {
      jotform_new_order_questionnaire_id: "261405426738055",
      jotform_renewall_questionnaire_id: null,
      integration_mode: "jotform",
    },
    jotformIntegration: {
      is_enabled: true,
      settings: {
        api_url: "https://ahghipaa.jotform.com/",
        api_key: "secret",
      },
    },
  });

  assertEquals(presentation.type, "jotform");
  assertEquals(
    presentation.type === "jotform" ? presentation.jotform.formId : null,
    "261405426738055",
  );
});

Deno.test("resolveQuestionnairePresentation: null integration_mode falls back to inference (backward-compatible)", () => {
  const presentation = resolveQuestionnairePresentation({
    order: {
      id: "order-123",
      subscription_order_type: "initial",
    },
    providerKey: "md_integrations",
    productProviderPlatform: {
      jotform_new_order_questionnaire_id: "261405426738055",
      jotform_renewall_questionnaire_id: null,
      integration_mode: null,
    },
    jotformIntegration: {
      is_enabled: true,
      settings: {
        api_url: "https://ahghipaa.jotform.com/",
        api_key: "secret",
      },
    },
  });

  // No explicit mode + a valid form id → Jotform (exactly as before this flag).
  assertEquals(presentation.type, "jotform");
});

Deno.test("resolvePatientQuestionnairePresentation: patient_questionnaire_mode='direct' forces native even when a form id is set", () => {
  const presentation = resolvePatientQuestionnairePresentation({
    order: {
      id: "order-123",
    },
    providerKey: "md_integrations",
    providerIntegration: {
      is_enabled: true,
      settings: {
        patient_questionnaire_mode: "direct",
        patient_questionnaire_form_id: "261405426738055",
      },
    },
    jotformIntegration: {
      is_enabled: true,
      settings: {
        api_url: "https://ahghipaa.jotform.com/",
        api_key: "secret",
      },
    },
  });

  // Direct mode → native provider questionnaire (resolver returns null).
  assertEquals(presentation, null);
});

Deno.test("resolvePatientQuestionnairePresentation: patient_questionnaire_mode='jotform' uses the configured form", () => {
  const presentation = resolvePatientQuestionnairePresentation({
    order: {
      id: "order-123",
    },
    providerKey: "md_integrations",
    providerIntegration: {
      is_enabled: true,
      settings: {
        patient_questionnaire_mode: "jotform",
        patient_questionnaire_form_id: "261405426738055",
      },
    },
    jotformIntegration: {
      is_enabled: true,
      settings: {
        api_url: "https://ahghipaa.jotform.com/",
        api_key: "secret",
      },
    },
  });

  assertEquals(presentation?.type, "jotform");
  assertEquals(presentation?.jotform.formId, "261405426738055");
});

Deno.test("resolvePatientQuestionnairePresentation: no mode set falls back to inference (backward-compatible)", () => {
  const presentation = resolvePatientQuestionnairePresentation({
    order: {
      id: "order-123",
    },
    providerKey: "md_integrations",
    providerIntegration: {
      is_enabled: true,
      settings: {
        patient_questionnaire_form_id: "261405426738055",
      },
    },
    jotformIntegration: {
      is_enabled: true,
      settings: {
        api_url: "https://ahghipaa.jotform.com/",
        api_key: "secret",
      },
    },
  });

  // No explicit mode + valid form id → Jotform (exactly as before this flag).
  assertEquals(presentation?.type, "jotform");
});

Deno.test("resolvePatientQuestionnairePresentation returns provider-scoped Jotform form when configured", () => {
  const presentation = resolvePatientQuestionnairePresentation({
    order: {
      id: "order-123",
    },
    providerKey: "md_integrations",
    providerIntegration: {
      is_enabled: true,
      settings: {
        patient_questionnaire_form_id: "261405426738055",
      },
    },
    jotformIntegration: {
      is_enabled: true,
      settings: {
        api_url: "https://ahghipaa.jotform.com/",
        api_key: "secret",
        patient_questionnaire_form_id: "261405426738099",
      },
    },
  });

  assertEquals(presentation?.type, "jotform");
  assertEquals(presentation?.purpose, "patient_questionnaire");
  assertEquals(presentation?.jotform.formId, "261405426738055");
  assertEquals(
    presentation?.jotform.formUrl,
    "https://ahghipaa.jotform.com/261405426738055?patient_platform_order_id=order-123&provider_key=md_integrations&questionnaire_type=patient_questionnaire",
  );
});

Deno.test("resolvePatientQuestionnairePresentation does NOT fall back to a global Jotform form id (per-provider only)", () => {
  // The form id is configured per provider. A provider with no form id of its
  // own ("Not Configured" in Nexus, e.g. TelegraMD) must use its NATIVE
  // questionnaire — it must not be forced onto Jotform via the global jotform
  // integration's legacy flat form id.
  const presentation = resolvePatientQuestionnairePresentation({
    order: {
      id: "order-123",
    },
    providerKey: "telegramd",
    providerIntegration: {
      is_enabled: true,
      settings: {},
    },
    jotformIntegration: {
      is_enabled: true,
      settings: {
        api_url: "https://ahghipaa.jotform.com/",
        api_key: "secret",
        patient_questionnaire_form_id: "261405426738055",
      },
    },
  });

  assertEquals(presentation, null);
});

Deno.test("buildTelegraPatientQuestionnaireJotformQuestions includes required hidden fields", () => {
  const questions = buildTelegraPatientQuestionnaireJotformQuestions();
  const providerKey = questions.find((question) =>
    question.name === JOTFORM_PROVIDER_KEY_FIELD_NAME
  );
  const orderId = questions.find((question) =>
    question.name === JOTFORM_ORDER_ID_FIELD_NAME
  );
  const questionnaireType = questions.find((question) =>
    question.name === JOTFORM_QUESTIONNAIRE_TYPE_FIELD_NAME
  );

  assertEquals(providerKey?.type, "control_radio");
  assertEquals(providerKey?.options, "md_integrations|telegramd|zito_care");
  assertEquals(providerKey?.defaultValue, "telegramd");
  assertEquals(providerKey?.hidden, "Yes");
  assertEquals(orderId?.type, "control_textbox");
  assertEquals(orderId?.hidden, "Yes");
  assertEquals(questionnaireType?.type, "control_radio");
  assertEquals(
    questionnaireType?.options,
    "medical_questionnaire|patient_questionnaire",
  );
  assertEquals(questionnaireType?.defaultValue, "patient_questionnaire");
});

Deno.test("buildTelegraPatientQuestionnaireJotformQuestions puts one visible question per page", () => {
  const questions = buildTelegraPatientQuestionnaireJotformQuestions();
  const pageBreaks = questions.filter((question) =>
    question.type === "control_pagebreak"
  );

  assertEquals(pageBreaks.length, 9);
  assertEquals(questions[0]?.name, "symptoms");
  assertEquals(questions[1]?.type, "control_pagebreak");
  assertEquals(questions[2]?.name, "other_symptoms");
  assertEquals(questions[18]?.name, "birth_date");
  assertEquals(questions[19]?.name, JOTFORM_PROVIDER_KEY_FIELD_NAME);
  assertEquals(questions[20]?.name, JOTFORM_ORDER_ID_FIELD_NAME);
  assertEquals(questions[21]?.name, JOTFORM_QUESTIONNAIRE_TYPE_FIELD_NAME);
});

Deno.test("buildJotformCreateFormParams serializes generated questions", () => {
  const questions = buildTelegraPatientQuestionnaireJotformQuestions();
  const params = buildJotformCreateFormParams({
    title: "Telegra Patient Questionnaire",
    questions,
    properties: {
      [JOTFORM_SAVE_AND_CONTINUE_FORM_PROPERTY]: "Yes",
    },
  });

  assertEquals(
    params.get("properties[title]"),
    "Telegra Patient Questionnaire",
  );
  assertEquals(
    params.get(`properties[${JOTFORM_SAVE_AND_CONTINUE_FORM_PROPERTY}]`),
    "Yes",
  );
  assertEquals(params.get("questions[1][name]"), "symptoms");
  assertEquals(params.get("questions[1][type]"), "control_textarea");
  assertEquals(params.get("questions[2][type]"), "control_pagebreak");
  assertEquals(
    params.get("questions[20][name]"),
    JOTFORM_PROVIDER_KEY_FIELD_NAME,
  );
  assertEquals(params.get("questions[21][name]"), JOTFORM_ORDER_ID_FIELD_NAME);
  assertEquals(
    params.get("questions[22][name]"),
    JOTFORM_QUESTIONNAIRE_TYPE_FIELD_NAME,
  );
});

Deno.test("resolvePatientQuestionnairePresentation ignores invalid tenant form ids", () => {
  const presentation = resolvePatientQuestionnairePresentation({
    order: {
      id: "order-123",
    },
    providerKey: "md_integrations",
    providerIntegration: {
      is_enabled: true,
      settings: {
        patient_questionnaire_form_id: "abc123",
      },
    },
    jotformIntegration: {
      is_enabled: true,
      settings: {
        api_url: "https://ahghipaa.jotform.com/",
        api_key: "secret",
      },
    },
  });

  assertEquals(presentation, null);
});

Deno.test("resolvePatientQuestionnairePresentation requires provider key", () => {
  const presentation = resolvePatientQuestionnairePresentation({
    order: {
      id: "order-123",
    },
    providerKey: null,
    providerIntegration: {
      is_enabled: true,
      settings: {
        patient_questionnaire_form_id: "261405426738055",
      },
    },
    jotformIntegration: {
      is_enabled: true,
      settings: {
        api_url: "https://ahghipaa.jotform.com/",
        api_key: "secret",
      },
    },
  });

  assertEquals(presentation, null);
});

Deno.test("jotformQuestionsHaveOrderIdField accepts object question maps", () => {
  assertEquals(
    jotformQuestionsHaveOrderIdField({
      "1": { type: "control_textbox", name: "firstName" },
      "2": { type: "control_hidden", name: "patient_platform_order_id" },
    }),
    true,
  );
});

Deno.test("jotformQuestionsHaveOrderIdField accepts question arrays", () => {
  assertEquals(
    jotformQuestionsHaveOrderIdField([
      { type: "control_textbox", name: "firstName" },
      { type: "control_hidden", name: " patient_platform_order_id " },
    ]),
    true,
  );
});

Deno.test("jotformQuestionsHaveOrderIdField rejects missing required field", () => {
  assertEquals(
    jotformQuestionsHaveOrderIdField({
      "1": { type: "control_textbox", name: "patientOrderId" },
    }),
    false,
  );
});

Deno.test("getJotformSubmissionOrderId extracts the hidden order id answer", () => {
  assertEquals(
    getJotformSubmissionOrderId({
      answers: {
        "1": {
          name: "patient_platform_order_id",
          text: "Order ID",
          type: "control_hidden",
          answer: "order-123",
          order: "1",
        },
      },
    }),
    "order-123",
  );
});

Deno.test("extractJotformFileUrls reads URLs from widget answer objects", () => {
  assertEquals(
    extractJotformFileUrls({
      file: {
        name: "id.jpg",
        url: "https://uploads.jotform.com/id.jpg",
      },
      duplicate: "https://uploads.jotform.com/id.jpg",
      label: "front of ID",
    }),
    ["https://uploads.jotform.com/id.jpg"],
  );
});

Deno.test("extractJotformFileUrls reads URLs from widget metadata JSON strings", () => {
  assertEquals(
    extractJotformFileUrls(
      '{"widget_metadata":{"type":"imagelinks","value":[{"name":"Clipart Document.png","url":"https://ahghipaa.jotform.com/widget-uploads/imagepreview/261483071783057/example.png?ufs=jotformWidgets"}]}}',
    ),
    [
      "https://ahghipaa.jotform.com/widget-uploads/imagepreview/261483071783057/example.png?ufs=jotformWidgets",
    ],
  );
});

Deno.test("extractJotformFileUrls ignores plain text values that are not URLs", () => {
  assertEquals(
    extractJotformFileUrls("front of ID"),
    [],
  );
});

Deno.test("resolveJotformSubmissionQuestionnaireMatch matches patient questionnaire form id", () => {
  const match = resolveJotformSubmissionQuestionnaireMatch({
    submission: { form_id: "261405426738055" },
    order: { subscription_order_type: "initial" },
    productProviderPlatform: {
      jotform_new_order_questionnaire_id: "261405426738066",
      jotform_renewall_questionnaire_id: null,
    },
    providerIntegration: {
      settings: {
        patient_questionnaire_form_id: "261405426738055",
      },
    },
    jotformIntegration: {
      settings: {},
    },
  });

  assertEquals(match, {
    type: "patient_questionnaire",
    formId: "261405426738055",
  });
});

Deno.test("resolveJotformSubmissionQuestionnaireMatch matches medical renewal form id", () => {
  const match = resolveJotformSubmissionQuestionnaireMatch({
    submission: { form_id: "261405426738066" },
    order: { subscription_order_type: "renewal" },
    productProviderPlatform: {
      jotform_new_order_questionnaire_id: "261405426738055",
      jotform_renewall_questionnaire_id: "261405426738066",
    },
    providerIntegration: {
      settings: {
        patient_questionnaire_form_id: "261405426738077",
      },
    },
    jotformIntegration: {
      settings: {},
    },
  });

  assertEquals(match, {
    type: "medical_questionnaire",
    formId: "261405426738066",
    selectedQuestionnaire: "renewal",
  });
});
