import { assertEquals } from "../_test/assert.ts";
import {
  buildTelegraAnswerLocationAgreementData,
  buildTelegraAnswerLocationRequestInit,
  buildTelegraAnswerRequestInit,
  buildTelegraConditionsAndSymptomsUrl,
  buildTelegraPatientUrl,
  buildTelegraQuestionnaireAnswerLocationUrl,
  buildTelegraQuestionnaireAnswerUrl,
  buildTelegraQuestionnaireInstanceUrl,
  buildTelegraQuestionnaireSchemaUrl,
  extractProviderNameFromMetadata,
  extractQuestionnaireInstanceIdsFromMetadata,
  extractTelegraSymptoms,
  getStringSetting,
  isTelegraProviderPlatform,
  normalizeProviderPlatformBridgePath,
  normalizeProviderPlatformIdentifier,
  parseAnswerLocationFormData,
  populateSymptomsQuestionnaireOptions,
} from "./helpers.ts";

Deno.test("normalizeProviderPlatformBridgePath trims function prefixes and trailing slashes", () => {
  assertEquals(
    normalizeProviderPlatformBridgePath(
      "/functions/v1/provider-platform-bridge/get-questionnaires/",
    ),
    "/get-questionnaires",
  );
  assertEquals(
    normalizeProviderPlatformBridgePath(
      "/provider-platform-bridge/get-questionnaires",
    ),
    "/get-questionnaires",
  );
});

Deno.test("getStringSetting returns trimmed string settings", () => {
  assertEquals(
    getStringSetting({ access_token: "  secret-token " }, "access_token"),
    "secret-token",
  );
  assertEquals(getStringSetting({ access_token: "" }, "access_token"), null);
});

Deno.test("normalizeProviderPlatformIdentifier and isTelegraProviderPlatform normalize provider values", () => {
  assertEquals(normalizeProviderPlatformIdentifier(" TelegraMD "), "telegramd");
  assertEquals(isTelegraProviderPlatform("TelegraMD"), true);
  assertEquals(isTelegraProviderPlatform("telegra"), true);
  assertEquals(isTelegraProviderPlatform("other-provider"), false);
});

Deno.test("extractQuestionnaireInstanceIdsFromMetadata returns sanitized ids", () => {
  assertEquals(
    extractQuestionnaireInstanceIdsFromMetadata({
      questionnaire_instance_ids: [" qi-1 ", "qi-2", "", null],
    }),
    ["qi-1", "qi-2"],
  );
  assertEquals(extractQuestionnaireInstanceIdsFromMetadata(null), []);
});

Deno.test("extractProviderNameFromMetadata returns trimmed provider name", () => {
  assertEquals(
    extractProviderNameFromMetadata({ provider: " TelegraMD " }),
    "TelegraMD",
  );
  assertEquals(extractProviderNameFromMetadata({ provider: 1 }), null);
});

Deno.test("buildTelegraQuestionnaireSchemaUrl appends questionnaire schema path", () => {
  assertEquals(
    buildTelegraQuestionnaireSchemaUrl(
      "https://api.telegramd.example.com/",
      "qi-123",
    ),
    "https://api.telegramd.example.com/questionnaireInstances/qi-123/schema",
  );
});

Deno.test("buildTelegraQuestionnaireInstanceUrl appends questionnaire instance path", () => {
  assertEquals(
    buildTelegraQuestionnaireInstanceUrl(
      "https://api.telegramd.example.com/",
      "qi-123",
    ),
    "https://api.telegramd.example.com/questionnaireInstances/qi-123",
  );
});

Deno.test("buildTelegraQuestionnaireAnswerLocationUrl appends answerLocation action path and shouldNavigateNext", () => {
  assertEquals(
    buildTelegraQuestionnaireAnswerLocationUrl(
      "https://api.telegramd.example.com/",
      "qi-123",
    ),
    "https://api.telegramd.example.com/questionnaireInstances/qi-123/actions/answerLocation?shouldNavigateNext=true",
  );
});

Deno.test("buildTelegraQuestionnaireAnswerUrl appends answer action path", () => {
  assertEquals(
    buildTelegraQuestionnaireAnswerUrl(
      "https://api.telegramd.example.com/",
      "qi-123",
    ),
    "https://api.telegramd.example.com/questionnaireInstances/qi-123/actions/answer",
  );
});

Deno.test("buildTelegraPatientUrl appends patient resource path", () => {
  assertEquals(
    buildTelegraPatientUrl(
      "https://api.telegramd.example.com/",
      "pat::123",
    ),
    "https://api.telegramd.example.com/patients/pat%3A%3A123",
  );
});

Deno.test("buildTelegraConditionsAndSymptomsUrl appends products action path", () => {
  assertEquals(
    buildTelegraConditionsAndSymptomsUrl(
      "https://api.telegramd.example.com/",
      [" pro::1 ", "pro::2"],
    ),
    "https://api.telegramd.example.com/products/actions/getConditionsAndSymptoms?products=pro%3A%3A1%2Cpro%3A%3A2",
  );
});

Deno.test("parseAnswerLocationFormData reads string answers from multipart payloads", () => {
  const formData = new FormData();
  formData.set("questionnaire-id", " qi-123 ");
  formData.set("location", " patient.address.state ");
  formData.set("value", " TX ");

  assertEquals(parseAnswerLocationFormData(formData), {
    questionnaireId: "qi-123",
    location: "patient.address.state",
    value: "TX",
    file: null,
  });
});

Deno.test("parseAnswerLocationFormData reads file answers from multipart payloads", async () => {
  const file = new File(["image-bytes"], "document.jpg", {
    type: "image/jpeg",
  });
  const formData = new FormData();
  formData.set("questionnaireId", "qi-456");
  formData.set("location", "patient.identity.document");
  formData.set("value", file, file.name);

  const parsed = parseAnswerLocationFormData(formData);

  assertEquals(parsed.questionnaireId, "qi-456");
  assertEquals(parsed.location, "patient.identity.document");
  assertEquals(parsed.value, null);
  assertEquals(parsed.file?.name, "document.jpg");
  assertEquals(parsed.file?.type, "image/jpeg");
  assertEquals(await parsed.file?.text(), "image-bytes");
});

Deno.test("parseAnswerLocationFormData parses JSON string arrays for multiple option answers", () => {
  const formData = new FormData();
  formData.set("questionnaire-id", "qi-789");
  formData.set("location", "patient.conditions");
  formData.set("value", '["weight-loss", "metabolic"]');

  assertEquals(parseAnswerLocationFormData(formData), {
    questionnaireId: "qi-789",
    location: "patient.conditions",
    value: ["weight-loss", "metabolic"],
    file: null,
  });
});

Deno.test("buildTelegraAnswerLocationRequestInit serializes string answers as JSON", async () => {
  const init = buildTelegraAnswerLocationRequestInit({
    location: "patient.address.state",
    value: "TX",
    accessToken: "secret-token",
    requestId: "request-1",
  });
  const request = new Request("https://api.telegramd.example.com", init);

  assertEquals(request.method, "PUT");
  assertEquals(request.headers.get("authorization"), "Bearer secret-token");
  assertEquals(request.headers.get("content-type"), "application/json");
  assertEquals(request.headers.get("x-request-id"), "request-1");
  assertEquals(request.headers.get("x-source"), "provider-platform-bridge");
  assertEquals(
    await request.text(),
    JSON.stringify({
      location: "patient.address.state",
      value: "TX",
    }),
  );
});

Deno.test("buildTelegraAnswerLocationRequestInit serializes array answers as JSON", async () => {
  const init = buildTelegraAnswerLocationRequestInit({
    location: "patient.conditions",
    value: ["weight-loss", "metabolic"],
    accessToken: "secret-token",
    requestId: "request-array",
  });
  const request = new Request("https://api.telegramd.example.com", init);

  assertEquals(request.method, "PUT");
  assertEquals(request.headers.get("authorization"), "Bearer secret-token");
  assertEquals(request.headers.get("content-type"), "application/json");
  assertEquals(
    await request.text(),
    JSON.stringify({
      location: "patient.conditions",
      value: ["weight-loss", "metabolic"],
    }),
  );
});

Deno.test("buildTelegraAnswerLocationRequestInit serializes informed consent answers as agreementData", async () => {
  const agreementData = buildTelegraAnswerLocationAgreementData({
    location: "loc::informed-consent:1",
    signature: "data:image/png;base64,abc",
    consentDate: "2024-06-11T10:40:54.425Z",
  });

  const init = buildTelegraAnswerLocationRequestInit({
    location: "loc::informed-consent:1",
    value: "data:image/png;base64,abc",
    agreementData: agreementData!,
    accessToken: "secret-token",
    requestId: "request-consent",
  });
  const request = new Request("https://api.telegramd.example.com", init);

  assertEquals(request.method, "PUT");
  assertEquals(request.headers.get("authorization"), "Bearer secret-token");
  assertEquals(request.headers.get("content-type"), "application/json");
  assertEquals(
    await request.text(),
    JSON.stringify({
      location: "loc::informed-consent:1",
      data: {
        agreementData: {
          consent: true,
          consentDate: "2024-06-11T10:40:54.425Z",
          signature: "data:image/png;base64,abc",
        },
      },
    }),
  );
});

Deno.test("buildTelegraAnswerLocationAgreementData ignores non-consent locations", () => {
  assertEquals(
    buildTelegraAnswerLocationAgreementData({
      location: "patient.conditions",
      signature: "data:image/png;base64,abc",
      consentDate: "2024-06-11T10:40:54.425Z",
    }),
    null,
  );
});

Deno.test("buildTelegraAnswerLocationRequestInit serializes file answers as multipart", async () => {
  const init = buildTelegraAnswerLocationRequestInit({
    location: "patient.identity.document",
    value: new File(["file-bytes"], "document.jpg", { type: "image/jpeg" }),
    accessToken: "secret-token",
    requestId: "request-2",
  });
  const request = new Request("https://api.telegramd.example.com", init);
  const formData = await request.formData();
  const value = formData.get("value");

  assertEquals(request.method, "PUT");
  assertEquals(request.headers.get("authorization"), "Bearer secret-token");
  assertEquals(
    request.headers.get("content-type")?.startsWith(
      "multipart/form-data; boundary=",
    ),
    true,
  );
  assertEquals(formData.get("location"), "patient.identity.document");
  assertEquals(value instanceof File, true);
  assertEquals((value as File).name, "document.jpg");
  assertEquals((value as File).type, "image/jpeg");
  assertEquals(await (value as File).text(), "file-bytes");
});

Deno.test("buildTelegraAnswerRequestInit serializes scalar answers as group payload", async () => {
  const init = buildTelegraAnswerRequestInit({
    location: "loc::weight-loss:2",
    value: "yes",
    accessToken: "secret-token",
    requestId: "request-3",
  });
  const request = new Request("https://api.telegramd.example.com", init);

  assertEquals(request.method, "PUT");
  assertEquals(request.headers.get("authorization"), "Bearer secret-token");
  assertEquals(request.headers.get("content-type"), "application/json");
  assertEquals(
    await request.text(),
    JSON.stringify({
      lastLocation: "loc::weight-loss:2",
      responses: [{ location: "loc::weight-loss:2", value: "yes" }],
    }),
  );
});

Deno.test("buildTelegraAnswerRequestInit serializes multiple option answers as arrays", async () => {
  const init = buildTelegraAnswerRequestInit({
    location: "loc::weight-loss:2",
    value: ["option-a", "option-b"],
    accessToken: "secret-token",
    requestId: "request-4",
  });
  const request = new Request("https://api.telegramd.example.com", init);

  assertEquals(
    await request.text(),
    JSON.stringify({
      lastLocation: "loc::weight-loss:2",
      responses: [{
        location: "loc::weight-loss:2",
        value: ["option-a", "option-b"],
      }],
    }),
  );
});

Deno.test("buildTelegraAnswerRequestInit serializes file answers as multipart group payload", async () => {
  const init = buildTelegraAnswerRequestInit({
    location: "patient.identity.document",
    value: new File(["file-bytes"], "document.jpg", { type: "image/jpeg" }),
    accessToken: "secret-token",
    requestId: "request-file-answer",
  });
  const request = new Request("https://api.telegramd.example.com", init);
  const formData = await request.formData();
  const value = formData.get("responses[0][value]");

  assertEquals(request.method, "PUT");
  assertEquals(request.headers.get("authorization"), "Bearer secret-token");
  assertEquals(
    request.headers.get("content-type")?.startsWith(
      "multipart/form-data; boundary=",
    ),
    true,
  );
  assertEquals(formData.get("lastLocation"), "patient.identity.document");
  assertEquals(
    formData.get("responses[0][location]"),
    "patient.identity.document",
  );
  assertEquals(value instanceof File, true);
  assertEquals((value as File).name, "document.jpg");
  assertEquals((value as File).type, "image/jpeg");
  assertEquals(await (value as File).text(), "file-bytes");
});

Deno.test("buildTelegraAnswerRequestInit serializes agreement answers as data payload", async () => {
  const init = buildTelegraAnswerRequestInit({
    location: "loc::informed-consent:1",
    data: { agreementData: "data:image/png;base64,abc" },
    accessToken: "secret-token",
    requestId: "request-5",
  });
  const request = new Request("https://api.telegramd.example.com", init);

  assertEquals(
    await request.text(),
    JSON.stringify({
      lastLocation: "loc::informed-consent:1",
      responses: [{
        location: "loc::informed-consent:1",
        data: { agreementData: "data:image/png;base64,abc" },
      }],
    }),
  );
});

Deno.test("buildTelegraAnswerRequestInit supports full agreementData object payload", async () => {
  const init = buildTelegraAnswerRequestInit({
    location: "loc::informed-consent:1",
    data: {
      agreementData: {
        consent: true,
        consentDate: "2026-03-09T10:00:00.000Z",
        signature: "data:image/png;base64,abc",
      },
    },
    accessToken: "secret-token",
    requestId: "request-6",
  });
  const request = new Request("https://api.telegramd.example.com", init);

  assertEquals(
    await request.text(),
    JSON.stringify({
      lastLocation: "loc::informed-consent:1",
      responses: [{
        location: "loc::informed-consent:1",
        data: {
          agreementData: {
            consent: true,
            consentDate: "2026-03-09T10:00:00.000Z",
            signature: "data:image/png;base64,abc",
          },
        },
      }],
    }),
  );
});

Deno.test("extractTelegraSymptoms returns sanitized and deduplicated symptom summaries plus a trailing none-of-the-above option", () => {
  assertEquals(
    extractTelegraSymptoms({
      symptoms: [
        {
          id: " symp::1 ",
          description: " Difficulty Sleeping ",
          name: " Difficulty Sleeping ",
        },
        {
          _id: "symp::2",
          description: "Joint Pain",
          name: "Joint Pain",
        },
        {
          id: "symp::1",
          description: "Duplicate",
          name: "Duplicate",
        },
        {
          id: "symp::3",
          description: "Hidden",
          name: "Hidden",
          deleted: true,
        },
        {
          id: "symp::4",
          description: "Missing name",
        },
      ],
    }),
    [
      {
        id: "symp::1",
        description: "Difficulty Sleeping",
        name: "Difficulty Sleeping",
      },
      {
        id: "symp::2",
        description: "Joint Pain",
        name: "Joint Pain",
      },
      {
        id: null,
        description: "None of the above",
        name: "None of the above",
      },
    ],
  );
});

Deno.test("populateSymptomsQuestionnaireOptions replaces options on symptoms questions", () => {
  assertEquals(
    populateSymptomsQuestionnaireOptions(
      {
        steps: [
          {
            id: "step-1",
            fields: [
              {
                id: "symptoms",
                type: "symptoms",
                options: [],
              },
            ],
          },
        ],
      },
      [
        {
          id: "symp::1",
          description: "Difficulty Sleeping",
          name: "Difficulty Sleeping",
        },
      ],
    ),
    {
      questionnaire: {
        steps: [
          {
            id: "step-1",
            fields: [
              {
                id: "symptoms",
                type: "symptoms",
                options: [
                  {
                    id: "symp::1",
                    description: "Difficulty Sleeping",
                    name: "Difficulty Sleeping",
                  },
                ],
              },
            ],
          },
        ],
      },
      replacedCount: 1,
    },
  );
});
