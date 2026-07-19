import { z } from 'zod';
import { normalizePhoneDigits } from '@/lib/phone';

const normalizePhoneInput = (value: unknown) =>
  typeof value === 'string' ? normalizePhoneDigits(value.trim()) : value;

// ========================================
// Patient validation
// ========================================

const weightSchema = z.preprocess(
  (value) => {
    if (value === '' || value === undefined) return undefined;
    if (value === null) return null;
    if (typeof value === 'string') {
      const trimmed = value.trim();
      if (!trimmed) return undefined;
      const parsed = Number(trimmed);
      return Number.isFinite(parsed) ? parsed : value;
    }
    return value;
  },
  z.number().positive('Must be greater than 0').nullable().optional()
);

export const patientSchema = z.object({
  first_name: z.string().trim().min(1, 'Required').max(100, 'Must be 100 characters or less'),
  last_name: z.string().trim().min(1, 'Required').max(100, 'Must be 100 characters or less'),
  email: z.string().trim().email('Invalid email address').max(255, 'Must be 255 characters or less').toLowerCase(),
  phone: z
    .string()
    .trim()
    .regex(/^\d*$/, 'Phone number must contain numbers only')
    .max(10, 'Phone number must be 10 digits or less'),
  starting_weight: weightSchema,
  target_weight: weightSchema,
});

// ========================================
// Shipping Address validation
// ========================================

export const shippingAddressSchema = z.object({
  shipping_first_name: z.string().trim().max(100, 'Must be 100 characters or less').optional().or(z.literal('')),
  shipping_last_name: z.string().trim().max(100, 'Must be 100 characters or less').optional().or(z.literal('')),
  shipping_company: z.string().trim().max(200, 'Must be 200 characters or less').optional().or(z.literal('')),
  shipping_address_line1: z.string().trim().max(255, 'Must be 255 characters or less').optional().or(z.literal('')),
  shipping_address_line2: z.string().trim().max(255, 'Must be 255 characters or less').optional().or(z.literal('')),
  shipping_city: z.string().trim().max(100, 'Must be 100 characters or less').optional().or(z.literal('')),
  shipping_state: z.string().trim().max(100, 'Must be 100 characters or less').optional().or(z.literal('')),
  shipping_postal_code: z.string().trim().max(20, 'Must be 20 characters or less').optional().or(z.literal('')),
  shipping_country: z.string().trim().max(100, 'Must be 100 characters or less').optional().or(z.literal('')),
  shipping_instructions: z.string().trim().max(500, 'Must be 500 characters or less').optional().or(z.literal('')),
});


// ========================================
// Billing Address validation
// ========================================

export const billingAddressSchema = z.object({
  billing_first_name: z.string().trim().max(100, 'Must be 100 characters or less').optional().or(z.literal('')),
  billing_last_name: z.string().trim().max(100, 'Must be 100 characters or less').optional().or(z.literal('')),
  billing_company: z.string().trim().max(200, 'Must be 200 characters or less').optional().or(z.literal('')),
  billing_address_line1: z.string().trim().max(255, 'Must be 255 characters or less').optional().or(z.literal('')),
  billing_address_line2: z.string().trim().max(255, 'Must be 255 characters or less').optional().or(z.literal('')),
  billing_city: z.string().trim().max(100, 'Must be 100 characters or less').optional().or(z.literal('')),
  billing_state: z.string().trim().max(100, 'Must be 100 characters or less').optional().or(z.literal('')),
  billing_postal_code: z.string().trim().max(20, 'Must be 20 characters or less').optional().or(z.literal('')),
  billing_country: z.string().trim().max(100, 'Must be 100 characters or less').optional().or(z.literal('')),
});


// ========================================
// Tenant validation
// ========================================

export const tenantSchema = z.object({
  name: z.string().trim().min(1, 'Required').max(100, 'Must be 100 characters or less'),
  slug: z.string().trim().min(1, 'Required').max(50, 'Must be 50 characters or less').regex(/^[a-z0-9-]+$/, 'Must be lowercase letters, numbers, and hyphens only'),
  contact_email: z.string().trim(),
});

export const tenantUpdateSchema = z.object({
  name: z.string().trim().min(1, 'Required').max(100, 'Must be 100 characters or less'),
  contact_email: z.string().trim().max(255, 'Must be 255 characters or less'),
  contact_phone: z.preprocess(
    normalizePhoneInput,
    z.string().max(50, 'Must be 50 characters or less')
  ),
});


// ========================================
// Medication validation
// ========================================

export const medicationFormValues = ['tablets', 'injectable_solution', 'spray'] as const;

export const medicationSchema = z.object({
  title: z.string().trim().min(1, 'Required').max(100, 'Must be 100 characters or less'),
  description: z.string().trim().max(500, 'Must be 500 characters or less').optional().or(z.literal('')),
  provider_sku: z.string().trim().max(100, 'Must be 100 characters or less').optional().or(z.literal('')),
  image_url: z.string().trim().optional().or(z.literal('')),
  form: z.enum(medicationFormValues, { required_error: 'Required' }),
});

// ========================================
// Validation helper function
// ========================================

export function validatePatient(data: unknown): ValidationResult<PatientFormData> {
  const result = patientSchema.safeParse(data);
  if (result.success) {
    return { success: true, data: result.data as PatientFormData };
  }
  return { success: false, errors: extractErrors(result.error) };
}

export function validateTenant(data: unknown): ValidationResult<TenantFormData> {
  const result = tenantSchema.safeParse(data);
  if (result.success) {
    return { success: true, data: result.data as TenantFormData };
  }
  return { success: false, errors: extractErrors(result.error) };
}

export function validateTenantUpdate(data: unknown): ValidationResult<TenantUpdateFormData> {
  const result = tenantUpdateSchema.safeParse(data);
  if (result.success) {
    return { success: true, data: result.data as TenantUpdateFormData };
  }
  return { success: false, errors: extractErrors(result.error) };
}

export function validateMedication(data: unknown): ValidationResult<MedicationFormData> {
  const result = medicationSchema.safeParse(data);
  if (result.success) {
    return { success: true, data: result.data as MedicationFormData };
  }
  return { success: false, errors: extractErrors(result.error) };
}

export function validateShippingAddress(data: unknown): ValidationResult<ShippingAddressFormData> {
  const result = shippingAddressSchema.safeParse(data);
  if (result.success) {
    return { success: true, data: result.data as ShippingAddressFormData };
  }
  return { success: false, errors: extractErrors(result.error) };
}

export function validateBillingAddress(data: unknown): ValidationResult<BillingAddressFormData> {
  const result = billingAddressSchema.safeParse(data);
  if (result.success) {
    return { success: true, data: result.data as BillingAddressFormData };
  }
  return { success: false, errors: extractErrors(result.error) };
}

function extractErrors(error: z.ZodError): Record<string, string> {
  const errors: Record<string, string> = {};
  for (const issue of error.issues) {
    const path = issue.path.join('.');
    if (!errors[path]) {
      errors[path] = issue.message;
    }
  }
  return errors;
}
