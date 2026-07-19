export interface QaAddressSource {
  first_name?: unknown;
  last_name?: unknown;
  company?: unknown;
  line1?: unknown;
  line2?: unknown;
  city?: unknown;
  state?: unknown;
  postal_code?: unknown;
  country?: unknown;
  instructions?: unknown;
}

export interface QaPatientAddressSource {
  first_name?: string | null;
  last_name?: string | null;
  country?: string | null;
  shipping_first_name?: string | null;
  shipping_last_name?: string | null;
  shipping_company?: string | null;
  shipping_address_line1?: string | null;
  shipping_address_line2?: string | null;
  shipping_city?: string | null;
  shipping_state?: string | null;
  shipping_postal_code?: string | null;
  shipping_country?: string | null;
  shipping_instructions?: string | null;
}

export interface QaResolvedAddress {
  first_name: string;
  last_name: string;
  company: string | null;
  line1: string;
  line2: string | null;
  city: string;
  state: string;
  postal_code: string;
  country: string;
  instructions: string | null;
}

export function buildQaOrderAddressFields(address: QaResolvedAddress) {
  return {
    shipping_first_name: address.first_name,
    shipping_last_name: address.last_name,
    shipping_company: address.company,
    shipping_address_line1: address.line1,
    shipping_address_line2: address.line2,
    shipping_city: address.city,
    shipping_state: address.state,
    shipping_postal_code: address.postal_code,
    shipping_country: address.country,
    shipping_instructions: address.instructions,
    billing_first_name: address.first_name,
    billing_last_name: address.last_name,
    billing_company: address.company,
    billing_address_line1: address.line1,
    billing_address_line2: address.line2,
    billing_city: address.city,
    billing_state: address.state,
    billing_postal_code: address.postal_code,
    billing_country: address.country,
  };
}

const text = (value: unknown): string | null =>
  typeof value === "string" && value.trim() ? value.trim() : null;

export function resolveQaShippingAddress(params: {
  requested?: QaAddressSource | null;
  patient: QaPatientAddressSource;
}): { address: QaResolvedAddress | null; missing: string[] } {
  const requested = params.requested ?? {};
  const patient = params.patient;
  const address = {
    first_name: text(requested.first_name) ||
      text(patient.shipping_first_name) || text(patient.first_name),
    last_name: text(requested.last_name) ||
      text(patient.shipping_last_name) || text(patient.last_name),
    company: text(requested.company) || text(patient.shipping_company),
    line1: text(requested.line1) || text(patient.shipping_address_line1),
    line2: text(requested.line2) || text(patient.shipping_address_line2),
    city: text(requested.city) || text(patient.shipping_city),
    state: text(requested.state) || text(patient.shipping_state),
    postal_code: text(requested.postal_code) ||
      text(patient.shipping_postal_code),
    country: text(requested.country) || text(patient.shipping_country) ||
      text(patient.country) || "US",
    instructions: text(requested.instructions) ||
      text(patient.shipping_instructions),
  };

  const required = [
    "first_name",
    "last_name",
    "line1",
    "city",
    "state",
    "postal_code",
    "country",
  ] as const;
  const missing = required.filter((field) => !address[field]);

  return missing.length > 0
    ? { address: null, missing: [...missing] }
    : { address: address as QaResolvedAddress, missing: [] };
}
