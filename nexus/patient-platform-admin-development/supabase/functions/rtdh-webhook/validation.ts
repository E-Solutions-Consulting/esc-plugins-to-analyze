export type JsonObject = Record<string, unknown>;

export interface RtdhEventPayload {
  master_order_id: string;
  internal_tenant_id: string;
  source_systems: string[];
  event_type?: unknown;
  global_status?: unknown;
  order_status_key?: unknown;
  status_provider?: unknown;
  updated_at: string;
  ids?: JsonObject;
  customer?: JsonObject;
  provider?: JsonObject;
  subscription?: JsonObject;
  payment?: JsonObject;
  prescription?: JsonObject;
  fulfillment?: JsonObject;
  shipping?: JsonObject;
  products?: unknown[];
  status_rollup?: JsonObject;
  rtdh_intent?: unknown;
  is_migrated?: unknown;
  migration?: JsonObject;
  timeline: unknown[];
}

export interface ValidatePayloadOptions {
  qaBypass?: boolean;
}

export function asObject(value: unknown): JsonObject | null {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    return null;
  }
  return value as JsonObject;
}

export function asNonEmptyString(value: unknown): string | null {
  if (typeof value !== "string") return null;
  const trimmed = value.trim();
  return trimmed.length > 0 ? trimmed : null;
}

export function asStringArray(value: unknown): string[] | null {
  if (!Array.isArray(value)) return null;
  const items = value
    .filter((entry) => typeof entry === "string")
    .map((entry) => (entry as string).trim())
    .filter((entry) => entry.length > 0);
  return items.length === value.length ? items : null;
}

function hasOwn(value: JsonObject, key: string): boolean {
  return Object.prototype.hasOwnProperty.call(value, key);
}

function isFiniteNumber(value: unknown): value is number {
  return typeof value === "number" && Number.isFinite(value);
}

function validateOptionalObject(
  payload: JsonObject,
  key: string,
  errors: string[],
  validate?: (value: JsonObject, path: string, errors: string[]) => void,
): void {
  if (!hasOwn(payload, key)) return;

  const value = asObject(payload[key]);
  if (!value) {
    errors.push(`${key} must be an object`);
    return;
  }

  validate?.(value, key, errors);
}

function validateNullableString(
  value: JsonObject,
  key: string,
  path: string,
  errors: string[],
): void {
  if (!hasOwn(value, key)) {
    errors.push(`${path}.${key} is required`);
    return;
  }
  if (value[key] === null) return;
  if (!asNonEmptyString(value[key])) {
    errors.push(`${path}.${key} must be a non-empty string or null`);
  }
}

function validateOptionalNullableString(
  value: JsonObject,
  key: string,
  path: string,
  errors: string[],
): void {
  if (!hasOwn(value, key)) return;
  if (value[key] === null) return;
  if (!asNonEmptyString(value[key])) {
    errors.push(`${path}.${key} must be a non-empty string or null`);
  }
}

function validateNullableNumber(
  value: JsonObject,
  key: string,
  path: string,
  errors: string[],
): void {
  if (!hasOwn(value, key)) {
    errors.push(`${path}.${key} is required`);
    return;
  }
  if (value[key] === null) return;
  if (!isFiniteNumber(value[key])) {
    errors.push(`${path}.${key} must be a number or null`);
  }
}

function validateOptionalNullableNumber(
  value: JsonObject,
  key: string,
  path: string,
  errors: string[],
): void {
  if (!hasOwn(value, key)) return;
  if (value[key] === null) return;
  if (!isFiniteNumber(value[key])) {
    errors.push(`${path}.${key} must be a number or null`);
  }
}

function validateNullableBoolean(
  value: JsonObject,
  key: string,
  path: string,
  errors: string[],
): void {
  if (!hasOwn(value, key)) {
    errors.push(`${path}.${key} is required`);
    return;
  }
  if (value[key] === null) return;
  if (typeof value[key] !== "boolean") {
    errors.push(`${path}.${key} must be a boolean or null`);
  }
}

function validateNullableIsoTimestamp(
  value: JsonObject,
  key: string,
  path: string,
  errors: string[],
): void {
  if (!hasOwn(value, key)) {
    errors.push(`${path}.${key} is required`);
    return;
  }
  if (value[key] === null) return;

  const timestamp = asNonEmptyString(value[key]);
  if (!timestamp || Number.isNaN(Date.parse(timestamp))) {
    errors.push(`${path}.${key} must be a valid ISO timestamp string or null`);
  }
}

function validateOptionalNullableIsoTimestamp(
  value: JsonObject,
  key: string,
  path: string,
  errors: string[],
): void {
  if (!hasOwn(value, key)) return;
  if (value[key] === null) return;

  const timestamp = asNonEmptyString(value[key]);
  if (!timestamp || Number.isNaN(Date.parse(timestamp))) {
    errors.push(`${path}.${key} must be a valid ISO timestamp string or null`);
  }
}

function validateRequiredString(
  value: JsonObject,
  key: string,
  path: string,
  errors: string[],
): void {
  if (!hasOwn(value, key)) {
    errors.push(`${path}.${key} is required`);
    return;
  }

  if (!asNonEmptyString(value[key])) {
    errors.push(`${path}.${key} must be a non-empty string`);
  }
}

function validateRequiredNumber(
  value: JsonObject,
  key: string,
  path: string,
  errors: string[],
): void {
  if (!hasOwn(value, key)) {
    errors.push(`${path}.${key} is required`);
    return;
  }

  if (!isFiniteNumber(value[key])) {
    errors.push(`${path}.${key} must be a number`);
  }
}

function validateRequiredIsoTimestamp(
  value: JsonObject,
  key: string,
  path: string,
  errors: string[],
): void {
  if (!hasOwn(value, key)) {
    errors.push(`${path}.${key} is required`);
    return;
  }

  const timestamp = asNonEmptyString(value[key]);
  if (!timestamp || Number.isNaN(Date.parse(timestamp))) {
    errors.push(`${path}.${key} must be a valid ISO timestamp string`);
  }
}

function validateCustomer(
  value: JsonObject,
  path: string,
  errors: string[],
): void {
  ["email"].forEach((key) => validateNullableString(value, key, path, errors));
  [
    "provider_name",
    "provider_patient_id",
    "phone",
    "first_name",
    "last_name",
  ].forEach((key) => validateOptionalNullableString(value, key, path, errors));
}

function validateSubscription(
  value: JsonObject,
  path: string,
  errors: string[],
): void {
  ["subscription_id", "status"].forEach((key) =>
    validateNullableString(value, key, path, errors)
  );
  // billing_period / billing_interval / cancelled_at are optional: RTDH omits
  // these keys entirely for subscriptions it has only partially materialized
  // (e.g. the draft subscription written at payment capture), so absence must
  // be tolerated. Type/format is still checked when a non-null value is present.
  validateOptionalNullableString(value, "billing_period", path, errors);
  validateOptionalNullableNumber(value, "billing_interval", path, errors);
  [
    "created_at",
    "updated_at",
    "next_payment_at",
    "end_date_at",
  ].forEach((key) => validateNullableIsoTimestamp(value, key, path, errors));
  validateOptionalNullableIsoTimestamp(value, "cancelled_at", path, errors);
}

function validatePayment(
  value: JsonObject,
  path: string,
  errors: string[],
  options: ValidatePayloadOptions = {},
): void {
  [
    "provider",
    "status",
    "currency",
    "customer_id",
    "event_id",
    "event_type",
    "object_id",
    "object_type",
  ].forEach((key) => validateNullableString(value, key, path, errors));
  if (!options.qaBypass) {
    validateNullableString(value, "api_version", path, errors);
  }
  [
    "subscription_id",
    "charge_id",
    "invoice_id",
    "payment_intent_id",
    "checkout_session_id",
  ].forEach((key) => validateOptionalNullableString(value, key, path, errors));
  validateNullableNumber(value, "amount", path, errors);
  if (!options.qaBypass) {
    validateNullableBoolean(value, "livemode", path, errors);
    validateNullableIsoTimestamp(value, "provider_created_at", path, errors);
  }
}

function validateStatusRollup(
  value: JsonObject,
  path: string,
  errors: string[],
): void {
  [
    "order_stage",
    "payment_stage",
    "prescription_stage",
    "fulfillment_stage",
    "shipping_stage",
  ].forEach((key) => validateNullableString(value, key, path, errors));
  ["is_complete", "is_cancelled"].forEach((key) =>
    validateNullableBoolean(value, key, path, errors)
  );
}

function validateProducts(value: unknown, errors: string[]): void {
  if (!Array.isArray(value)) {
    errors.push("products must be an array");
    return;
  }

  value.forEach((entry, index) => {
    const itemPath = `products[${index}]`;
    const item = asObject(entry);
    if (!item) {
      errors.push(`${itemPath} must be an object`);
      return;
    }

    [
      "product_id",
      "name",
      "subscription_duration",
    ].forEach((key) => validateRequiredString(item, key, itemPath, errors));
    validateRequiredNumber(item, "quantity", itemPath, errors);
    validateRequiredNumber(item, "price", itemPath, errors);
  });
}

function validateTimeline(value: unknown, errors: string[]): void {
  if (!Array.isArray(value)) {
    errors.push("timeline must be an array");
    return;
  }
  if (value.length === 0) {
    errors.push("timeline must contain at least one event");
    return;
  }

  value.forEach((entry, index) => {
    const itemPath = `timeline[${index}]`;
    const item = asObject(entry);
    if (!item) {
      errors.push(`${itemPath} must be an object`);
      return;
    }

    ["event_id", "source", "event_type", "status"].forEach((key) =>
      validateRequiredString(item, key, itemPath, errors)
    );
    validateRequiredIsoTimestamp(item, "at", itemPath, errors);
  });
}

export function validatePayload(
  payload: JsonObject,
  options: ValidatePayloadOptions = {},
): string[] {
  const errors: string[] = [];

  if (!asNonEmptyString(payload.master_order_id)) {
    errors.push("master_order_id must be a non-empty string");
  }
  if (!asNonEmptyString(payload.internal_tenant_id)) {
    errors.push("internal_tenant_id must be a non-empty string");
  }

  const sourceSystems = asStringArray(payload.source_systems);
  if (!sourceSystems || sourceSystems.length === 0) {
    errors.push("source_systems must be a non-empty string array");
  }

  const updatedAt = asNonEmptyString(payload.updated_at);
  if (!updatedAt) {
    errors.push("updated_at must be a non-empty ISO timestamp string");
  } else if (Number.isNaN(Date.parse(updatedAt))) {
    errors.push("updated_at must be a valid ISO timestamp");
  }

  validateOptionalObject(payload, "ids", errors);
  validateOptionalObject(payload, "customer", errors, validateCustomer);
  validateOptionalObject(payload, "provider", errors);
  validateOptionalObject(payload, "subscription", errors, validateSubscription);
  validateOptionalObject(
    payload,
    "payment",
    errors,
    (value, path, errors) => validatePayment(value, path, errors, options),
  );
  validateOptionalObject(payload, "prescription", errors);
  validateOptionalObject(payload, "fulfillment", errors);
  validateOptionalObject(payload, "shipping", errors);
  validateOptionalObject(
    payload,
    "status_rollup",
    errors,
    validateStatusRollup,
  );

  if (hasOwn(payload, "products")) {
    validateProducts(payload.products, errors);
  }
  if (!hasOwn(payload, "timeline")) {
    errors.push("timeline is required");
  } else {
    validateTimeline(payload.timeline, errors);
  }

  return errors;
}
