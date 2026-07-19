import { assertEquals } from "../_test/assert.ts";
import {
  buildMdiCaseQuestionTypeCandidates,
  buildMdiPatientProfilePayloadFromJotformSubmission,
  filterMdiQuestionnairesByOfferingId,
  isJotformQuestionFlagConfigField,
  normalizeMdiQuestionTypeAlias,
  parseHeightToCentimeters,
  parseMdiCaseQuestionBody,
  resolveMdiCaseQuestionType,
  resolveMdiJotformEffectiveQuestionnaireType,
  resolveMdiJotformQuestionFlags,
  resolveMdiQuestionnaireIdsForMedicationOfferings,
  updateMdiCaseHoldStatus,
} from "./mdi.ts";
import { type JotformSubmissionContent } from "./jotform.ts";

Deno.test("filterMdiQuestionnairesByOfferingId matches questionnaires with object offerings", () => {
  const questionnaires = [
    {
      partner_questionnaire_id: "q-1",
      offerings: {
        offering_id: "compound-1",
      },
    },
    {
      partner_questionnaire_id: "q-2",
      offerings: {
        offering_id: "compound-2",
      },
    },
  ];

  const filtered = filterMdiQuestionnairesByOfferingId(
    questionnaires,
    "compound-2",
  );

  assertEquals(filtered, [questionnaires[1]]);
});

Deno.test("filterMdiQuestionnairesByOfferingId matches questionnaires with array offerings", () => {
  const questionnaires = [
    {
      partner_questionnaire_id: "q-1",
      offerings: [
        { offering_id: "compound-1" },
        { offering_id: "compound-2" },
      ],
    },
    {
      partner_questionnaire_id: "q-2",
      offerings: [{ offering_id: "compound-3" }],
    },
  ];

  const filtered = filterMdiQuestionnairesByOfferingId(
    questionnaires,
    "compound-2",
  );

  assertEquals(filtered, [questionnaires[0]]);
});

Deno.test("resolveMdiQuestionnaireIdsForMedicationOfferings resolves every bundled medication", () => {
  const questionnaires = [
    {
      partner_questionnaire_id: "q-sermorelin",
      offerings: [{ offering_id: "offer-sermorelin" }],
    },
    {
      partner_questionnaire_id: "q-nad",
      offerings: [{ offering_id: "offer-nad" }],
    },
    {
      partner_questionnaire_id: "q-unrelated",
      offerings: [{ offering_id: "offer-unrelated" }],
    },
  ];

  const resolved = resolveMdiQuestionnaireIdsForMedicationOfferings(
    questionnaires,
    [
      {
        medication_id: "med-sermorelin",
        medication_title: "Sermorelin",
        offering_id: "offer-sermorelin",
      },
      {
        medication_id: "med-nad",
        medication_title: "NAD+",
        offering_id: "offer-nad",
      },
    ],
  );

  assertEquals(resolved.questionnaireIds, ["q-sermorelin", "q-nad"]);
  assertEquals(resolved.matches, [
    {
      questionnaireId: "q-sermorelin",
      offeringId: "offer-sermorelin",
      medicationId: "med-sermorelin",
      medicationTitle: "Sermorelin",
    },
    {
      questionnaireId: "q-nad",
      offeringId: "offer-nad",
      medicationId: "med-nad",
      medicationTitle: "NAD+",
    },
  ]);
  assertEquals(resolved.missingMedicationOfferings, []);
});

Deno.test("normalizeMdiQuestionTypeAlias maps common app aliases to MDI types", () => {
  assertEquals(normalizeMdiQuestionTypeAlias("multiselect"), "string");
  assertEquals(normalizeMdiQuestionTypeAlias("single-choice"), "string");
  assertEquals(normalizeMdiQuestionTypeAlias("textarea"), "string");
  assertEquals(normalizeMdiQuestionTypeAlias("yes_no"), "boolean");
});

Deno.test("parseHeightToCentimeters returns integers for MDI patient updates", () => {
  assertEquals(parseHeightToCentimeters("5 ft 9 in"), 175);
  assertEquals(parseHeightToCentimeters("69 in"), 175);
  assertEquals(parseHeightToCentimeters("175.26 cm"), 175);
});

Deno.test("buildMdiPatientProfilePayloadFromJotformSubmission maps patient questionnaire answers", () => {
  const payload = buildMdiPatientProfilePayloadFromJotformSubmission({
    id: "submission-1",
    form_id: "261483071783057",
    answers: {
      "5": {
        name: "symptoms",
        text: "Please check all the symptoms you have.",
        type: "control_checkbox",
        answer: ["Headache", "Nausea"],
        order: "5",
      },
      "14": {
        name: "medications_list",
        text: "Medications",
        type: "control_widget",
        answer: [
          { Medication: "Metformin", Dosage: "500mg", Frequency: "Daily" },
        ],
        order: "14",
      },
      "17": {
        name: "medications_confirm",
        text: "Medication confirmation",
        type: "control_radio",
        answer: "Yes - listed above",
        order: "17",
      },
      "21": {
        name: "allergies_list",
        text: "Allergies",
        type: "control_widget",
        answer: [{ Allergy: "Pollen", Reaction: "Hives" }],
        order: "21",
      },
      "23": {
        name: "allergies_confirm",
        text: "Allergy confirmation",
        type: "control_radio",
        answer: "Yes - listed above",
        order: "23",
      },
      "26": {
        name: "biological_gender",
        text: "What is your biological gender?",
        type: "control_radio",
        answer: "Male",
        order: "26",
      },
      "28": {
        name: "weight_unit",
        text: "Weight units",
        type: "control_dropdown",
        answer: "lbs",
        order: "28",
      },
      "32": {
        name: "weight_value",
        text: "What is your current weight?",
        type: "control_number",
        answer: "200",
        order: "32",
      },
      "34": {
        name: "height_value",
        text: "What is your current height?",
        type: "control_number",
        answer: "180",
        order: "34",
      },
      "35": {
        name: "height_unit",
        text: "Height units",
        type: "control_dropdown",
        answer: "cm",
        order: "35",
      },
      "38": {
        name: "date_of_birth",
        text: "What's your birth date?",
        type: "control_datetime",
        answer: { day: "31", month: "12", year: "2000" },
        order: "38",
      },
      "39": {
        name: "other_symptoms_options",
        text: "Other symptoms not listed in previous screen",
        type: "control_radio",
        answer: "Yes - Please list conditions",
        order: "39",
      },
      "50": {
        name: "other_symptoms_text",
        text: "Please list conditions",
        type: "control_textarea",
        answer: "Hypertension",
        order: "50",
      },
      "72": {
        name: "areYou",
        text:
          "Are you sure that you want to proceed without upload an ID Document?",
        type: "control_radio",
        answer: "Yes",
        order: "72",
      },
      "74": {
        name: "patient_platform_order_id",
        text: "patient_platform_order_id",
        type: "control_textbox",
        answer: "order-1",
        order: "74",
      },
    },
  });

  assertEquals(payload, {
    gender: 1,
    date_of_birth: "2000-12-31",
    weight: 90.72,
    height: 180,
    current_medications: "Metformin - 500mg - Daily",
    medical_conditions: "Hypertension",
    allergies: "Pollen - Hives",
    metafields: [
      {
        key: "symptoms",
        title: "Reported symptoms",
        value: "Headache, Nausea",
        type: "string",
      },
      {
        key: "medications_confirm",
        title: "Medication confirmation",
        value: "Yes - listed above",
        type: "string",
      },
      {
        key: "allergies_confirm",
        title: "Allergy confirmation",
        value: "Yes - listed above",
        type: "string",
      },
      {
        key: "other_symptoms_options",
        title: "Other symptoms option",
        value: "Yes - Please list conditions",
        type: "string",
      },
      {
        key: "id_verification_skipped_confirmation",
        title: "ID verification skipped confirmation",
        value: "Yes",
        type: "string",
      },
    ],
  });
});

Deno.test("resolveMdiJotformEffectiveQuestionnaireType keeps the RTDH questionnaire type authoritative", () => {
  const result = resolveMdiJotformEffectiveQuestionnaireType({
    incomingQuestionnaireType: "patient_questionnaire",
    submission: {
      id: "submission-1",
      form_id: "261483071783057",
      answers: {
        "77": {
          name: "questionnaire_type",
          text: "questionnaire_type",
          type: "control_hidden",
          answer: "medical_questionnaire",
          order: "77",
        },
      },
    },
  });

  assertEquals(result, {
    effectiveQuestionnaireType: "patient_questionnaire",
    submittedQuestionnaireType: "medical_questionnaire",
  });
});

Deno.test("resolveMdiJotformQuestionFlags reads global hidden question lists", () => {
  const submission: JotformSubmissionContent = {
    id: "submission-flags",
    form_id: "form-1",
    answers: {
      "1": {
        name: "allergies",
        text: "Do you have allergies?",
        type: "control_textarea",
        answer: "Peanuts",
        order: "1",
      },
      "2": {
        name: "currentMedications",
        text: "Current medications",
        type: "control_textarea",
        answer: "Aspirin",
        order: "2",
      },
      "3": {
        name: "important_questions",
        text: "Important questions",
        type: "control_textbox",
        answer: "currentMedications",
        order: "3",
      },
      "4": {
        name: "critical_questions",
        text: "Critical questions",
        type: "control_textbox",
        answer: "allergies",
        order: "4",
      },
    },
  };

  assertEquals(
    resolveMdiJotformQuestionFlags({
      submission,
      entry: submission.answers["1"],
    }),
    { critical: true },
  );
  assertEquals(
    resolveMdiJotformQuestionFlags({
      submission,
      entry: submission.answers["2"],
    }),
    { important: true },
  );
  assertEquals(isJotformQuestionFlagConfigField(submission.answers["3"]), true);
  assertEquals(isJotformQuestionFlagConfigField(submission.answers["4"]), true);
});

Deno.test("resolveMdiJotformQuestionFlags reads per-question boolean fields", () => {
  const submission: JotformSubmissionContent = {
    id: "submission-per-question-flags",
    form_id: "form-1",
    answers: {
      "1": {
        name: "allergies",
        text: "Do you have allergies?",
        type: "control_textarea",
        answer: "Peanuts",
        order: "1",
      },
      "2": {
        name: "allergies_is_critical",
        text: "Allergies is critical",
        type: "control_textbox",
        answer: "Yes",
        order: "2",
      },
      "3": {
        name: "important_allergies",
        text: "Allergies is important",
        type: "control_textbox",
        answer: "true",
        order: "3",
      },
    },
  };

  assertEquals(
    resolveMdiJotformQuestionFlags({
      submission,
      entry: submission.answers["1"],
    }),
    { important: true, critical: true },
  );
  assertEquals(isJotformQuestionFlagConfigField(submission.answers["2"]), true);
  assertEquals(isJotformQuestionFlagConfigField(submission.answers["3"]), true);
});

Deno.test("buildMdiPatientProfilePayloadFromJotformSubmission converts height to integer centimeters and weight to kilograms", () => {
  const imperialPayload = buildMdiPatientProfilePayloadFromJotformSubmission({
    id: "submission-2",
    form_id: "261483071783057",
    answers: {
      "28": {
        name: "weight_unit",
        text: "Weight units",
        type: "control_dropdown",
        answer: "lbs",
        order: "28",
      },
      "32": {
        name: "weight_value",
        text: "What is your current weight?",
        type: "control_number",
        answer: "154",
        order: "32",
      },
      "34": {
        name: "height_value",
        text: "What is your current height?",
        type: "control_number",
        answer: "5.8",
        order: "34",
      },
      "35": {
        name: "height_unit",
        text: "Height units",
        type: "control_dropdown",
        answer: "ft",
        order: "35",
      },
    },
  });

  assertEquals(imperialPayload.weight, 69.85);
  assertEquals(imperialPayload.height, 177);

  const metricPayload = buildMdiPatientProfilePayloadFromJotformSubmission({
    id: "submission-3",
    form_id: "261483071783057",
    answers: {
      "28": {
        name: "weight_unit",
        text: "Weight units",
        type: "control_dropdown",
        answer: "Kgs",
        order: "28",
      },
      "32": {
        name: "weight_value",
        text: "What is your current weight?",
        type: "control_number",
        answer: "72.5",
        order: "32",
      },
      "34": {
        name: "height_value",
        text: "What is your current height?",
        type: "control_number",
        answer: "175.6",
        order: "34",
      },
      "35": {
        name: "height_unit",
        text: "Height units",
        type: "control_dropdown",
        answer: "cm",
        order: "35",
      },
    },
  });

  assertEquals(metricPayload.weight, 72.5);
  assertEquals(metricPayload.height, 176);
});

Deno.test("buildMdiPatientProfilePayloadFromJotformSubmission stringifies list/object answers into human-readable metafields", () => {
  const payload = buildMdiPatientProfilePayloadFromJotformSubmission({
    id: "submission-4",
    form_id: "261483071783057",
    answers: {
      "5": {
        name: "symptoms",
        text: "Symptoms",
        type: "control_widget",
        answer: [
          { symptom: "Headache", severity: "Moderate" },
          { symptom: "Nausea", severity: "Mild" },
        ],
        order: "5",
      },
    },
  });

  assertEquals(payload.metafields, [
    {
      key: "symptoms",
      title: "Reported symptoms",
      value: "Headache - Moderate; Nausea - Mild",
      type: "string",
    },
  ]);
});

Deno.test("buildMdiPatientProfilePayloadFromJotformSubmission parses stringified JSON list answers", () => {
  const payload = buildMdiPatientProfilePayloadFromJotformSubmission({
    id: "submission-5",
    form_id: "261483071783057",
    answers: {
      "14": {
        name: "medications_list",
        text: "Medications",
        type: "control_textarea",
        answer:
          '[{"Medication":"Ben-u-ron","Reaction":"Pain relief"},{"Medication":"Brufen","Reaction":"Pain 2 Relief"}]',
        order: "14",
      },
    },
  });

  assertEquals(
    payload.current_medications,
    "Ben-u-ron - Pain relief; Brufen - Pain 2 Relief",
  );
});

Deno.test("updateMdiCaseHoldStatus patches the MDI case status endpoint", async () => {
  const originalFetch = globalThis.fetch;
  const originalDebug = console.debug;
  const calls: Array<{
    input: Parameters<typeof fetch>[0];
    init?: Parameters<typeof fetch>[1];
  }> = [];

  console.debug = () => {};
  globalThis.fetch = ((input, init) => {
    calls.push({ input, init });
    return Promise.resolve(
      new Response(JSON.stringify({ case_id: "case-1", hold_status: false }), {
        status: 200,
        headers: { "content-type": "application/json" },
      }),
    );
  }) as typeof fetch;

  try {
    const response = await updateMdiCaseHoldStatus({
      backendUrl: "https://api.mdintegrations.com/",
      accessToken: "token-1",
      providerOrderId: "case 1/2",
      holdStatus: false,
      requestId: "request-1",
    });

    assertEquals(response, { case_id: "case-1", hold_status: false });
    assertEquals(calls.length, 1);

    const call = calls[0];
    const url = call.input instanceof Request
      ? call.input.url
      : String(call.input);
    const headers = new Headers(call.init?.headers);

    assertEquals(
      url,
      "https://api.mdintegrations.com/v1/partner/cases/case%201%2F2/status",
    );
    assertEquals(call.init?.method, "PATCH");
    assertEquals(headers.get("Authorization"), "Bearer token-1");
    assertEquals(headers.get("Content-Type"), "application/json");
    assertEquals(JSON.parse(String(call.init?.body)), { hold_status: false });
  } finally {
    globalThis.fetch = originalFetch;
    console.debug = originalDebug;
  }
});

Deno.test("updateMdiCaseHoldStatus proceeds when PATCH is forbidden but case is already released", async () => {
  const originalFetch = globalThis.fetch;
  const originalDebug = console.debug;
  const originalWarn = console.warn;
  const originalInfo = console.info;
  const calls: Array<{
    input: Parameters<typeof fetch>[0];
    init?: Parameters<typeof fetch>[1];
  }> = [];

  console.debug = () => {};
  console.warn = () => {};
  console.info = () => {};
  globalThis.fetch = ((input, init) => {
    calls.push({ input, init });

    if (calls.length === 1) {
      return Promise.resolve(
        new Response(JSON.stringify({ message: "Access forbidden." }), {
          status: 403,
          headers: { "content-type": "application/json" },
        }),
      );
    }

    return Promise.resolve(
      new Response(JSON.stringify({ case_id: "case-1", hold_status: false }), {
        status: 200,
        headers: { "content-type": "application/json" },
      }),
    );
  }) as typeof fetch;

  try {
    const response = await updateMdiCaseHoldStatus({
      backendUrl: "https://api.mdintegrations.com/",
      accessToken: "token-1",
      providerOrderId: "case 1/2",
      holdStatus: false,
      requestId: "request-1",
    });

    assertEquals(response, { case_id: "case-1", hold_status: false });
    assertEquals(calls.length, 2);

    const patchCall = calls[0];
    const patchUrl = patchCall.input instanceof Request
      ? patchCall.input.url
      : String(patchCall.input);
    assertEquals(
      patchUrl,
      "https://api.mdintegrations.com/v1/partner/cases/case%201%2F2/status",
    );
    assertEquals(patchCall.init?.method, "PATCH");

    const getCaseCall = calls[1];
    const getCaseUrl = getCaseCall.input instanceof Request
      ? getCaseCall.input.url
      : String(getCaseCall.input);
    const getCaseHeaders = new Headers(getCaseCall.init?.headers);
    assertEquals(
      getCaseUrl,
      "https://api.mdintegrations.com/v1/partner/cases/case%201%2F2",
    );
    assertEquals(getCaseCall.init?.method, "GET");
    assertEquals(getCaseHeaders.get("Authorization"), "Bearer token-1");
  } finally {
    globalThis.fetch = originalFetch;
    console.debug = originalDebug;
    console.warn = originalWarn;
    console.info = originalInfo;
  }
});

Deno.test("resolveMdiCaseQuestionType prefers questionnaire-defined MDI type", () => {
  const questionnaire = {
    questions: [
      {
        partner_questionnaire_question_id: "question-1",
        title: "Select your symptoms",
        type: "multiple_option",
      },
    ],
  };

  const resolvedType = resolveMdiCaseQuestionType({
    questionnaire,
    payload: {
      question: "Select your symptoms",
      answer: "Headache",
      type: "multiselect",
    },
  });

  assertEquals(resolvedType, "string");
});

Deno.test("resolveMdiCaseQuestionType falls back to normalized payload type when questionnaire does not match", () => {
  const resolvedType = resolveMdiCaseQuestionType({
    questionnaire: {
      questions: [
        {
          partner_questionnaire_question_id: "question-1",
          title: "Unrelated question",
          type: "boolean",
        },
      ],
    },
    payload: {
      question: "Pick one option",
      answer: "A",
      type: "single-choice",
    },
  });

  assertEquals(resolvedType, "string");
});

Deno.test("buildMdiCaseQuestionTypeCandidates prefers incoming normalized type before questionnaire-derived fallback", () => {
  const candidates = buildMdiCaseQuestionTypeCandidates({
    questionnaire: {
      questions: [
        {
          title: "Are you here to be evaluated for weight loss?",
          type: "single_option",
        },
      ],
    },
    payload: {
      question: "Are you here to be evaluated for weight loss?",
      answer: "true",
      type: "boolean",
    },
  });

  assertEquals(candidates, [
    "boolean",
    "string",
  ]);
});

Deno.test("buildMdiCaseQuestionTypeCandidates expands option-based fallback types", () => {
  const candidates = buildMdiCaseQuestionTypeCandidates({
    questionnaire: {
      questions: [
        {
          title:
            "Have you ever attempted to lose weight in a weight management program?",
          type: "single_option",
          options: [
            { option: "Yes" },
            { option: "No, this would be my first time" },
          ],
        },
      ],
    },
    payload: {
      question:
        "Have you ever attempted to lose weight in a weight management program?",
      answer: "No, this would be my first time",
      type: "text",
    },
  });

  assertEquals(candidates, [
    "string",
  ]);
});

Deno.test("parseMdiCaseQuestionBody preserves the MDI critical field", async () => {
  const request = new Request("http://localhost/test", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify([
      {
        question: "Do you have any severe allergies?",
        answer: "Yes",
        type: "boolean",
        important: true,
        critical: true,
      },
    ]),
  });

  const parsed = await parseMdiCaseQuestionBody(request);

  assertEquals(parsed, [
    {
      question: "Do you have any severe allergies?",
      answer: "Yes",
      type: "boolean",
      important: true,
      critical: true,
    },
  ]);
});

Deno.test("parseMdiCaseQuestionBody normalizes legacy is_critical to critical", async () => {
  const request = new Request("http://localhost/test", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify([
      {
        question: "Do you have any severe allergies?",
        answer: "Yes",
        type: "boolean",
        is_critical: true,
      },
    ]),
  });

  const parsed = await parseMdiCaseQuestionBody(request);

  assertEquals(parsed, [
    {
      question: "Do you have any severe allergies?",
      answer: "Yes",
      type: "boolean",
      critical: true,
    },
  ]);
});

Deno.test("parseMdiCaseQuestionBody resolves file question by slugged question field name", async () => {
  const formData = new FormData();
  formData.set(
    "questions",
    JSON.stringify([
      {
        question:
          "Please provide a current, FULL body clothed photo of yourself.",
        type: "file",
        file_type: "lab-result",
      },
    ]),
  );
  formData.set(
    "please_provide_a_current_full_body_clothed_photo_of_yourself",
    new File(["image-bytes"], "photo.jpg", { type: "image/jpeg" }),
  );

  const request = new Request("http://localhost/test", {
    method: "POST",
    body: formData,
  });

  const parsed = await parseMdiCaseQuestionBody(request);

  assertEquals(parsed?.length, 1);
  assertEquals(parsed?.[0]?.type, "file");
  assertEquals(
    parsed?.[0]?.answer,
    "please_provide_a_current_full_body_clothed_photo_of_yourself",
  );
  assertEquals(parsed?.[0]?.file instanceof File, true);
});

Deno.test("parseMdiCaseQuestionBody resolves single uploaded file without explicit mapping", async () => {
  const formData = new FormData();
  formData.set(
    "questions",
    JSON.stringify([
      {
        question: "Upload your lab result",
        type: "file",
      },
    ]),
  );
  formData.set(
    "unexpected_field_name",
    new File(["pdf-bytes"], "lab.pdf", { type: "application/pdf" }),
  );

  const request = new Request("http://localhost/test", {
    method: "POST",
    body: formData,
  });

  const parsed = await parseMdiCaseQuestionBody(request);

  assertEquals(parsed?.length, 1);
  assertEquals(parsed?.[0]?.answer, "unexpected_field_name");
  assertEquals(parsed?.[0]?.file instanceof File, true);
});

Deno.test("parseMdiCaseQuestionBody assigns multiple unmatched files in form order", async () => {
  const formData = new FormData();
  formData.set(
    "questions",
    JSON.stringify([
      {
        question:
          "Your doctor only needs to see cholesterol levels, TSH (thyroid test), Ha1c, and Creatinine (kidney function).",
        type: "file",
      },
      {
        question:
          "Please provide a current, FULL body clothed photo of yourself.",
        type: "file",
      },
    ]),
  );
  formData.set(
    "random_upload_one",
    new File(["pdf-bytes"], "labs.pdf", { type: "application/pdf" }),
  );
  formData.set(
    "random_upload_two",
    new File(["image-bytes"], "photo.jpg", { type: "image/jpeg" }),
  );

  const request = new Request("http://localhost/test", {
    method: "POST",
    body: formData,
  });

  const parsed = await parseMdiCaseQuestionBody(request);

  assertEquals(parsed?.length, 2);
  assertEquals(parsed?.[0]?.answer, "random_upload_one");
  assertEquals(parsed?.[1]?.answer, "random_upload_two");
  assertEquals(parsed?.[0]?.file instanceof File, true);
  assertEquals(parsed?.[1]?.file instanceof File, true);
});

Deno.test("parseMdiCaseQuestionBody decodes file question data URL answers into File objects", async () => {
  const request = new Request("http://localhost/test", {
    method: "POST",
    headers: {
      "content-type": "application/json",
    },
    body: JSON.stringify([
      {
        question:
          "Your doctor only needs to see cholesterol levels, TSH (thyroid test), Ha1c, and Creatinine (kidney function).",
        type: "file",
        answer: "data:image/png;base64,cG5nLWJ5dGVz",
      },
    ]),
  });

  const parsed = await parseMdiCaseQuestionBody(request);

  assertEquals(parsed?.length, 1);
  assertEquals(parsed?.[0]?.type, "file");
  assertEquals(parsed?.[0]?.file instanceof File, true);
  assertEquals(parsed?.[0]?.file?.type, "image/png");
  assertEquals(
    parsed?.[0]?.file?.name,
    "your-doctor-only-needs-to-see-cholesterol-levels-tsh-thyroid-test-ha1c-and-creat.png",
  );
  assertEquals(await parsed?.[0]?.file?.text(), "png-bytes");
});
