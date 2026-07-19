// =====================================================
// ALLIA CARE MULTI-TENANT SAAS PLATFORM - TYPE DEFINITIONS
// =====================================================

type Session = import("@supabase/supabase-js").Session;

// =====================================================
// ENUMS (matching database enums)
// =====================================================

type AppRole = "platform_superadmin" | "tenant_admin" | "customer_support";

type TenantStatus = "active" | "inactive" | "suspended" | "pending";

type PatientAccessStatus = "active" | "suspended" | "deactivated";

// OrderStatus is now represented by the order_statuses table
// Legacy enum removed - use status_id foreign key instead

type FlagType = "boolean" | "exclusive_option";

// =====================================================
// ENTITY INTERFACES
// =====================================================

interface Tenant {
  id: string;
  name: string;
  slug: string;
  status: TenantStatus;
  contact_email: string | null;
  contact_phone: string | null;
  metadata: Record<string, unknown>;
  created_at: string;
  updated_at: string;
}

interface TenantBranding {
  id: string;
  tenant_id: string;
  logo_url: string | null;
  /** True when logo_url is a wordmark that already renders the brand name. */
  logo_has_wordmark: boolean;
  primary_color: string;
  secondary_color: string;
  accent_color: string;
  custom_css: string | null;
  created_at: string;
  updated_at: string;
}

interface TenantInfo {
  id: string;
  name: string;
  slug: string;
  is_primary: boolean;
}

interface TenantMembership {
  id: string;
  admin_user_id: string;
  tenant_id: string;
  is_primary: boolean;
  created_at: string;
  // Joined from tenants table for display
  tenant_name?: string;
  tenant_slug?: string;
}

interface AdminUser {
  id: string;
  auth_user_id?: string;
  admin_user_id?: string;
  email: string;
  full_name: string | null;
  avatar_url: string | null;
  is_active: boolean;
  created_at: string;
  roles?: string[] | null;
  tenants?: TenantInfo[];
}

interface Superadmin {
  id: string;
  admin_user_id: string;
  email: string;
  full_name: string | null;
  avatar_url: string | null;
  is_active: boolean;
  created_at: string;
  tenant_count: number;
}
interface UserRole {
  id: string;
  user_id: string;
  role: AppRole;
}

interface Patient {
  id: string;
  tenant_id: string;
  auth_user_id: string | null;
  external_id: string | null;
  email: string;
  first_name: string;
  last_name: string;
  date_of_birth: string | null;
  phone: string | null;
  address_line1: string | null;
  address_line2: string | null;
  city: string | null;
  state: string | null;
  postal_code: string | null;
  country: string;
  access_status: PatientAccessStatus;
  starting_weight: number | null;
  target_weight: number | null;
  subscribed_to_email_marketing: boolean;
  subscribed_to_sms_marketing: boolean;
  vitals: Record<string, unknown>;
  allergies: unknown[];
  medications: unknown[];
  conditions: unknown[];
  metadata: Record<string, unknown>;
  terms_and_conditions_accepted_at: string | null;
  terms_and_conditions_accepted_content: string | null;
  // Shipping address fields
  shipping_first_name: string | null;
  shipping_last_name: string | null;
  shipping_company: string | null;
  shipping_address_line1: string | null;
  shipping_address_line2: string | null;
  shipping_city: string | null;
  shipping_state: string | null;
  shipping_postal_code: string | null;
  shipping_country: string | null;
  shipping_instructions: string | null;
  // Billing address fields
  billing_first_name: string | null;
  billing_last_name: string | null;
  billing_company: string | null;
  billing_address_line1: string | null;
  billing_address_line2: string | null;
  billing_city: string | null;
  billing_state: string | null;
  billing_postal_code: string | null;
  billing_country: string | null;
  created_at: string;
  updated_at: string;
}

interface Product {
  id: string;
  name: string;
  tenant_id: string;
  sku: string | null;
  provider_sku: string | null;
  description: string | null;
  terms_and_conditions_html: string | null;
  price_cents: number;
  is_enabled: boolean;
  image_url?: string | null;
  payment_type: PaymentType;
  subscription_interval: SubscriptionInterval | null;
  subscription_interval_count: number | null;
  subscription_renewal_lead_days: number;
  /** Max weeks before end of cycle that an admin may move/trigger a renewal. */
  renewal_advance_max_weeks: number;
  faqs?: ProductFaq[];
  /** Tenant-authored "What's Included" bullets shown in checkout (per product, per tenant). */
  included_features: string[];
  metadata: Record<string, unknown>;
  created_at: string;
  updated_at: string;
}

interface ProductFaq {
  id: string;
  product_id: string;
  question: string;
  answer: string;
  display_order: number;
  created_at: string;
  updated_at: string;
}

interface Medication {
  id: string;
  tenant_id: string;
  title: string;
  description: string | null;
  provider_sku: string | null;
  offering_id: string | null;
  image_url: string | null;
  form: "tablets" | "injectable_solution" | "spray" | null;
  metadata: Record<string, unknown>;
  created_at: string;
  updated_at: string;
}

interface ProductMedication {
  id: string;
  product_id: string;
  medication_id: string;
  quantity: number;
  instructions: string | null;
  medication?: {
    id: string;
    title: string;
    image_url: string | null;
    provider_sku: string | null;
    offering_id?: string | null;
  };
}

interface QuestionnaireTemplate {
  id: string;
  tenant_id: string | null;
  name: string;
  description: string | null;
  version: number;
  schema: Record<string, unknown>;
  is_shared: boolean;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

interface Order {
  id: string;
  tenant_id: string;
  patient_id: string;
  subscription_id: string | null;
  subscription_order_type: "initial" | "renewal" | null;
  product_id: string | null;
  product_name: string | null;
  provider_platform_order_id: string | null;
  order_number: string;
  status_id: string | null;
  cancellation_reason: string | null;
  status_changed_at: string | null;
  metadata: Record<string, unknown> | null;
  // Shipping address fields
  shipping_first_name: string | null;
  shipping_last_name: string | null;
  shipping_company: string | null;
  shipping_address_line1: string | null;
  shipping_address_line2: string | null;
  shipping_city: string | null;
  shipping_state: string | null;
  shipping_postal_code: string | null;
  shipping_country: string;
  shipping_instructions: string | null;
  // Billing address fields
  billing_first_name: string | null;
  billing_last_name: string | null;
  billing_company: string | null;
  billing_address_line1: string | null;
  billing_address_line2: string | null;
  billing_city: string | null;
  billing_state: string | null;
  billing_postal_code: string | null;
  billing_country: string | null;
  // Tracking and notes
  tracking_number: string | null;
  tracking_url: string | null;
  internal_notes: string | null;
  paid_at: string | null;
  // Financials
  subtotal_cents: number;
  shipping_cents: number;
  tax_cents: number;
  discount_cents: number;
  total_cents: number;
  coupon_code: string | null;
  coupon_name: string | null;
  // Timestamps
  shipped_at: string | null;
  delivered_at: string | null;
  cancelled_at: string | null;
  paused_at: string | null;
  renewal_at: string | null;
  created_at: string;
  updated_at: string;
}

interface OrderStatusHistoryEntry {
  id: string;
  order_id: string;
  status_id: string;
  changed_by: string | null;
  changed_by_email: string | null;
  notes: string | null;
  created_at: string;
  order_statuses: {
    status_key: string;
    admin_status_label: string;
    patient_status_label: string | null;
  };
}

interface FeatureFlag {
  id: string;
  key: string;
  name: string;
  description: string | null;
  flag_type: FlagType;
  default_value: boolean;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

interface FlagOverride {
  id: string;
  feature_flag_id: string;
  tenant_id: string;
  enabled: boolean;
}

interface TenantFeatureFlagOverride {
  id: string;
  tenant_id: string;
  feature_flag_id: string;
  enabled: boolean;
  exclusive_option_id: string | null;
  created_at: string;
  updated_at: string;
}

interface ActivityDefinition {
  id: string;
  tenant_id: string;
  label: string;
  is_active: boolean;
  display_order: number;
  created_at: string;
  updated_at: string;
}

interface InjectionSiteDefinition {
  id: string;
  tenant_id: string;
  label: string;
  image_url: string;
  is_active: boolean;
  display_order: number;
  created_at: string;
  updated_at: string;
}

interface BodyMeasurementEntry {
  id: string;
  tenant_id: string;
  patient_id: string;
  chest_inches: number;
  waist_inches: number;
  hips_inches: number;
  arms_inches: number;
  measured_at: string;
  created_at: string;
  updated_at: string;
}

interface AuditLog {
  id: string;
  tenant_id: string | null;
  actor_id: string | null;
  action: string;
  entity_type: string;
  actor_email: string | null;
  entity_id: string | null;
  before_data: import("@/integrations/supabase/types").Json | null;
  after_data: import("@/integrations/supabase/types").Json | null;
  diff: import("@/integrations/supabase/types").Json | null;
  request_id: string | null;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string;
}

interface TenantSettings {
  id: string;
  tenant_id: string;
  notifications_email_enabled: boolean;
  notifications_sms_enabled: boolean;
  session_timeout_minutes: number;
  require_mfa: boolean;
  timezone: string;
  date_format: string;
  currency: string;
  metadata?: Record<string, unknown> & {
    mobile_apps?: {
      stores?: Array<{
        id: "ios" | "android";
        app_url: string;
        qr_code_url: string;
      }>;
      ios_app_link?: string;
      android_app_link?: string;
    };
    email_template_html?: string | null;
  };
  created_at?: string;
  updated_at?: string;
  allowed_countries: string[];
  allowed_states: string[];
}

interface TenantSupportConfig {
  id: string;
  tenant_id: string;
  support_html: string | null;
  faqs?: Array<{ question: string; answer: string }> | null;
  support_hours?: string | null;
  created_at?: string;
  updated_at?: string;
}

interface TenantModuleSubscription {
  id: string;
  tenant_id: string;
  module_key: string;
  is_enabled: boolean;
  config: Record<string, unknown>;
  created_at: string;
  updated_at: string;
}

// =====================================================
// AUTH STORE TYPES
// =====================================================

interface AuthState {
  user: AdminUser | null;
  session: Session | null;
  roles: AppRole[];
  tenants: TenantMembership[];
  currentTenantId: string | null;
  isLoading: boolean;
  isSessionRefreshing: boolean;
  isAuthenticated: boolean;
  profileStatus: "idle" | "loading" | "ready" | "error";
  profileError: string | null;
}

interface AuthStoreType extends AuthState {
  signIn: (email: string, password: string) => Promise<void>;
  signUp: (email: string, password: string, fullName: string) => Promise<void>;
  signOut: () => Promise<void>;
  refreshProfile: () => Promise<void>;
  switchTenant: (tenantId: string) => void;
  isPlatformSuperadmin: boolean;
  isTenantAdmin: boolean;
  isCustomerSupport: boolean;
}

// =====================================================
// API RESPONSE TYPES
// =====================================================

interface ApiResponse<T> {
  data: T | null;
  error: ApiError | null;
}

interface ApiError {
  code: string;
  message: string;
  details?: Record<string, unknown>;
}

interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  pageSize: number;
  hasMore: boolean;
}

// =====================================================
// PATIENT API TYPES (for patient-facing API responses)
// =====================================================

/**
 * Shipping address object returned by patient-api endpoints
 */
interface PatientShippingAddress {
  first_name: string | null;
  last_name: string | null;
  company: string | null;
  line1: string | null;
  line2: string | null;
  city: string | null;
  state: string | null;
  postal_code: string | null;
  country: string | null;
  instructions: string | null;
}

/**
 * Billing address object returned by patient-api endpoints
 */
interface PatientBillingAddress {
  first_name: string | null;
  last_name: string | null;
  company: string | null;
  line1: string | null;
  line2: string | null;
  city: string | null;
  state: string | null;
  postal_code: string | null;
  country: string | null;
}

/**
 * Patient profile response from GET /auth/me and PATCH /auth/me
 */
interface PatientProfileResponse {
  user_id: string;
  id: string;
  tenant_id: string;
  first_name: string;
  last_name: string;
  email: string;
  phone: string | null;
  date_of_birth: string | null;
  subscribed_to_email_marketing: boolean;
  subscribed_to_sms_marketing: boolean;
  shipping_address: PatientShippingAddress;
  billing_address: PatientBillingAddress;
  access_status: PatientAccessStatus;
  created_at?: string;
}

/**
 * Request payload for PATCH /auth/me
 */
interface PatientProfileUpdateRequest {
  first_name?: string;
  last_name?: string;
  phone?: string;
  date_of_birth?: string;
  subscribed_to_email_marketing?: boolean;
  subscribed_to_sms_marketing?: boolean;
  shipping_address?: Partial<Omit<PatientShippingAddress, "instructions">> & {
    instructions?: string;
  };
  billing_address?: Partial<PatientBillingAddress>;
}

/**
 * Response from PATCH /auth/me
 */
interface PatientProfileUpdateResponse {
  message: string;
  data: PatientProfileResponse;
}

interface PatientOrderNotificationResource {
  type: "order";
  id: string;
  order_number: string;
  product_title: string | null;
  status_changed_at: string | null;
}

interface PatientChatNotificationResource {
  type: "chat";
  provider_name: string | null;
  provider_patient_id: string | null;
  order_id: string | null;
}

type PatientNotificationResource =
  | PatientOrderNotificationResource
  | PatientChatNotificationResource;

interface PatientNotification {
  id: string;
  type: "order_action_required" | "chat_message";
  title: string;
  message: string;
  created_at: string;
  updated_at: string;
  resource: PatientNotificationResource;
}

interface PatientNotificationsResponse {
  data: PatientNotification[];
  summary: {
    total_pending_actions: number;
  };
}

// =====================================================
// DASHBOARD METRICS
// =====================================================

interface TenantDashboardMetrics {
  totalPatients: number;
  activePatients: number;
  totalOrders: number;
  pendingOrders: number;
  totalProducts: number;
  enabledProducts: number;
  recentOrders: Order[];
}

interface PlatformDashboardMetrics {
  totalTenants: number;
  activeTenants: number;
  totalAdminUsers: number;
  recentTenants: Tenant[];
}

// =====================================================
// FORM TYPES
// =====================================================

interface PatientFormData {
  email: string;
  first_name: string;
  last_name: string;
  date_of_birth?: string;
  phone?: string;
  address_line1?: string;
  address_line2?: string;
  city?: string;
  state?: string;
  postal_code?: string;
  country?: string;
  starting_weight?: number | null;
  target_weight?: number | null;
}

interface ProductFormData {
  name: string;
  sku: string;
  provider_sku?: string;
  description: string;
  terms_and_conditions_html: string;
  price: string;
}

interface OrderFormData {
  shipping_address_line1: string;
  shipping_address_line2?: string;
  shipping_city: string;
  shipping_state: string;
  shipping_postal_code: string;
  shipping_country: string;
  internal_notes?: string;
}

interface MedicationFormData {
  title: string;
  description?: string;
  provider_sku?: string;
  image_url?: string;
  form: "tablets" | "injectable_solution" | "spray";
}

interface TenantFormData {
  name: string;
  slug: string;
  contact_email?: string;
  contact_phone?: string;
}

// =====================================================
// SHARED DOMAIN LITERALS
// =====================================================

type PaymentType = "one_time" | "subscription";

type SubscriptionInterval = "day" | "week" | "month" | "year";

interface ProductUpdateData {
  name?: string;
  sku?: string | null;
  provider_sku?: string | null;
  description?: string | null;
  terms_and_conditions_html?: string | null;
  price_cents?: number;
  is_enabled?: boolean;
  image_url?: string | null;
  payment_type?: PaymentType;
  subscription_interval?: SubscriptionInterval | null;
  subscription_interval_count?: number | null;
  subscription_renewal_lead_days?: number;
  renewal_advance_max_weeks?: number;
  included_features?: string[];
  /**
   * The full metadata blob. It is a shared jsonb column — several producers keep
   * their own keys in it (`pdp`, `allow_promo_codes`, …). Always PATCH the whole
   * merged object, never a bare `{ pdp }`, or the other keys are silently dropped.
   */
  metadata?: Record<string, unknown>;
}

/**
 * Editorial content for the patient-facing product detail page, stored under
 * `products.metadata.pdp`. Imported once from the marketing site
 * (start-wellness.json) by scripts/import-product-pdp.mjs, and from then on
 * edited in Nexus. Every field is optional: a product may carry only a gallery,
 * only an About block, or nothing at all.
 *
 * Deliberately excludes `price`, `href` and `variants` — the platform owns those
 * (see the import script header). Do not add them here.
 */
interface ProductPdpContent {
  /** Small pill above the title, e.g. "Best Seller". */
  badge?: string;
  /** Secondary pill next to the badge. */
  subBadge?: string;
  /** One-line teaser shown under the title. */
  shortDesc?: string;
  /** Longer "learn more" lede paragraph. */
  description?: string;
  /** Gallery image URLs, in display order. First is the hero. */
  images?: string[];
  about?: ProductPdpAbout;
  /** "What's included" bullets specific to the page (distinct from included_features). */
  includes?: string[];
  /** Path-to-care steps. Free-form objects as authored on the marketing site. */
  steps?: ProductPdpStep[];
}

interface ProductPdpAbout {
  /** e.g. "What is Tirzepatide?" */
  heading?: string;
  /** Illustration shown beside the body. */
  image?: string;
  /** Body paragraphs, each optionally tied to a citation (1-indexed). */
  paragraphs?: ProductPdpParagraph[];
  /** Callout under the paragraphs, e.g. which medication the plan includes. */
  note?: string;
  /** Heading for the benefits list, e.g. "Studies examine GLP-1's potential role in:". */
  benefitsHeading?: string;
  benefits?: ProductPdpBenefit[];
  /** Ordered reference list. A paragraph/benefit `citation: n` points at citations[n-1]. */
  citations?: string[];
}

interface ProductPdpParagraph {
  text: string;
  /** 1-indexed pointer into `about.citations`. */
  citation?: number;
}

interface ProductPdpBenefit {
  /** Bold lead-in, e.g. "Feeling lighter". */
  lead: string;
  /** Rest of the sentence, e.g. "and more comfortable in daily movements". */
  rest?: string;
  /** 1-indexed pointer into `about.citations`. */
  citation?: number;
}

interface ProductPdpStep {
  title?: string;
  description?: string;
  image?: string;
}

type SubscriptionStatus =
  | "active"
  | "paused"
  | "cancelled"
  | "pending_validation"
  | "pending_cancellation";

type SortOption =
  | "name_asc"
  | "name_desc"
  | "renewal_asc"
  | "renewal_desc"
  | "created_desc"
  | "created_asc";

interface SubscriptionWithPatient {
  id: string;
  status: SubscriptionStatus;
  current_period_end_at: string | null;
  metadata: Record<string, unknown> | null;
  created_at: string;
  patients: { first_name: string; last_name: string } | null;
}

interface SubscriptionPatient {
  id: string;
  first_name: string;
  last_name: string;
  email: string;
  phone: string | null;
  access_status: "active" | "suspended" | "deactivated";
}

interface SubscriptionProduct {
  id: string;
  name: string;
  price_cents: number;
  payment_type: string | null;
  subscription_interval: string | null;
  subscription_interval_count: number | null;
  subscription_renewal_lead_days: number;
  renewal_advance_max_weeks: number;
}

interface SubscriptionDetails {
  id: string;
  status: SubscriptionStatus;
  started_at: string | null;
  current_period_end_at: string | null;
  expires_at: string | null;
  paused_at: string | null;
  cancelled_at: string | null;
  cancellation_reason: string | null;
  metadata: Record<string, unknown> | null;
  created_at: string;
  updated_at: string;
  patient: SubscriptionPatient | null;
  product: SubscriptionProduct | null;
}

interface SubscriptionOrder {
  id: string;
  order_number: string;
  subscription_order_type: "initial" | "renewal" | null;
  total_cents: number;
  created_at: string;
  paid_at: string | null;
  order_statuses: {
    id: string;
    status_key: string;
    admin_status_label: string;
    is_terminal: boolean;
    next_step_owner: string;
  } | null;
}

interface ProviderLink {
  id: string;
  provider_subscription_id: string | null;
  provider_checkout_session_id: string | null;
  created_at: string;
  updated_at: string;
  provider: {
    name: string;
    key: string;
  } | null;
}

interface PaymentTransaction {
  id: string;
  payment_status: string | null;
  paid_at: string | null;
  provider_payment_intent_id: string | null;
  provider_invoice_id: string | null;
  provider_charge_id: string | null;
  created_at: string;
  order: {
    id: string;
    order_number: string;
    total_cents: number;
    created_at: string;
  } | null;
  provider: {
    name: string;
    key: string;
  } | null;
}

interface SubscriptionEvent {
  id: string;
  event_type: string;
  old_status: SubscriptionStatus | null;
  new_status: SubscriptionStatus | null;
  old_renewal_at: string | null;
  new_renewal_at: string | null;
  old_expires_at: string | null;
  new_expires_at: string | null;
  old_paused_at: string | null;
  new_paused_at: string | null;
  old_cancelled_at: string | null;
  new_cancelled_at: string | null;
  changed_by_email: string | null;
  notes: string | null;
  created_at: string;
}

interface SubscriptionDetailsPayload {
  subscription: SubscriptionDetails;
  orders: SubscriptionOrder[];
  providerLinks: ProviderLink[];
  paymentTransactions: PaymentTransaction[];
  events: SubscriptionEvent[];
}
// =====================================================
// PRODUCT SYNC TYPES
// =====================================================

interface ProductSyncData {
  id: string;
  name: string;
  description: string | null;
  price_cents: number;
  payment_type: PaymentType;
  subscription_interval: SubscriptionInterval | null;
  subscription_interval_count: number | null;
  subscription_renewal_lead_days: number;
  sku: string | null;
  image_url: string | null;
  metadata?: Record<string, unknown>;
}

interface ProviderSyncResult {
  provider_key: string;
  provider_name: string;
  success: boolean;
  external_product_id?: string;
  external_price_id?: string;
  error?: string;
}

interface SyncResponse {
  success: boolean;
  results: ProviderSyncResult[];
  error?: string;
  message?: string;
}

// =====================================================
// PAYMENT PROVIDER TYPES
// =====================================================

interface RequiredSetting {
  key: string;
  label: string;
  type: "text" | "secret" | "select";
  required: boolean;
  placeholder?: string;
  options?: string[];
}

interface PaymentProvider {
  id: string;
  key: string;
  name: string;
  description: string | null;
  logo_url: string | null;
  is_active: boolean;
  required_settings: RequiredSetting[];
  created_at: string;
  updated_at: string;
}

interface PaymentProviderFormData {
  key: string;
  name: string;
  description?: string;
  logo_url?: string;
  is_active: boolean;
  required_settings: RequiredSetting[];
}

interface TenantPaymentProvider {
  id: string;
  tenant_id: string;
  payment_provider_id: string;
  is_enabled: boolean;
  settings: Record<string, string>;
  created_at: string;
  updated_at: string;
  payment_provider?: PaymentProvider;
}

interface TenantPaymentProviderWithDetails {
  id: string;
  tenant_id: string;
  payment_provider_id: string;
  is_enabled: boolean;
  settings: Record<string, string>;
  payment_provider: PaymentProvider;
}

interface ProductPaymentProvider {
  id: string;
  product_id: string;
  tenant_payment_provider_id: string;
  is_enabled: boolean;
  created_at: string;
}

// =====================================================
// PLATFORM SETTINGS
// =====================================================

interface PlatformSetting {
  id: string;
  key: string;
  value: Record<string, unknown>;
  description: string | null;
  category: string;
  created_at: string;
  updated_at: string;
}

// =====================================================
// ORDER STATUS API TYPES
// =====================================================

interface OrderStatus {
  id: string;
  key: string;
  label: string;
  description: string | null;
  action_required: boolean;
  is_final: boolean;
  display_order: number;
}

interface StatusHistoryEntry {
  id: string;
  timestamp: string;
  status: OrderStatus | null;
}

interface OrderStatusHistoryResponse {
  order_id: string;
  order_number: string;
  current_status: string;
  status_changed_at: string;
  history: StatusHistoryEntry[];
  total_transitions: number;
}

interface AllOrderStatusesResponse {
  id: string;
  key: string;
  label: string;
  description: string | null;
  action_required: boolean;
  is_final: boolean;
  display_order: number;
}

interface OrderStatusData {
  id: string;
  status_key: string;
  admin_status_label: string;
  is_terminal: boolean;
  next_step_owner: string;
}

// =====================================================
// VALIDATION TYPES
// =====================================================

interface PatientValidationFormData {
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  starting_weight?: number | null;
  target_weight?: number | null;
}

interface ShippingAddressFormData {
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

interface BillingAddressFormData {
  billing_first_name?: string | null;
  billing_last_name?: string | null;
  billing_company?: string | null;
  billing_address_line1?: string | null;
  billing_address_line2?: string | null;
  billing_city?: string | null;
  billing_state?: string | null;
  billing_postal_code?: string | null;
  billing_country?: string | null;
}

interface TenantValidationFormData {
  name: string;
  slug: string;
  contact_email: string;
}

interface TenantUpdateFormData {
  name: string;
  contact_email: string;
  contact_phone: string;
}

interface MedicationValidationFormData {
  title: string;
  description?: string;
  provider_sku?: string;
  image_url?: string;
  form: "tablets" | "injectable_solution" | "spray";
}

type ValidationSuccess<T> = { success: true; data: T };
type ValidationFailure = { success: false; errors: Record<string, string> };
type ValidationResult<T> = ValidationSuccess<T> | ValidationFailure;

// =====================================================
// PATIENT & ORDER UPDATE TYPES
// =====================================================

interface PatientUpdateData {
  first_name?: string;
  last_name?: string;
  email?: string;
  phone?: string | null;
  date_of_birth?: string | null;
  starting_weight?: number | null;
  target_weight?: number | null;
  subscribed_to_email_marketing?: boolean;
  subscribed_to_sms_marketing?: boolean;
  address_line1?: string | null;
  address_line2?: string | null;
  city?: string | null;
  state?: string | null;
  postal_code?: string | null;
  access_status?: PatientAccessStatus;
  // Shipping address
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
  // Billing address
  billing_first_name?: string | null;
  billing_last_name?: string | null;
  billing_company?: string | null;
  billing_address_line1?: string | null;
  billing_address_line2?: string | null;
  billing_city?: string | null;
  billing_state?: string | null;
  billing_postal_code?: string | null;
  billing_country?: string | null;
}

type PatientOrder = Order & {
  subscription?: {
    id: string;
    status: string;
    current_period_end_at: string | null;
  } | null;
  product?: Pick<Product, "id" | "name"> | null;
  order_statuses?: OrderStatusData | null;
};

// =====================================================
// ANALYTICS TYPES
// =====================================================

interface SalesMetrics {
  totalOrders: number;
  totalQuantity: number;
  totalRevenue: number;
  uniqueCustomers: number;
}

interface RecentOrder {
  orderId: string;
  orderNumber: string;
  patientName: string;
  patientId: string;
  quantity: number;
  unitPriceCents: number;
  totalCents: number;
  orderStatus: OrderStatusData | null;
  createdAt: string;
}

// =====================================================
// INTEGRATION CONFIG TYPES
// =====================================================

interface ProviderWithConfig {
  id: string;
  key: string;
  name: string;
  description: string | null;
  logo_url: string | null;
  is_active: boolean;
  required_settings: RequiredSetting[];
  tenantConfig: {
    id: string;
    is_enabled: boolean;
    settings: Record<string, string>;
  } | null;
  isEnabled: boolean;
  configuredSettings: Record<string, string>;
}

interface PlatformIntegration {
  id: string;
  key: string;
  name: string;
  description: string | null;
  logo_url: string | null;
  is_active: boolean;
  required_settings: string[];
  category: string;
  created_at: string;
}

interface ProviderLogoAsset {
  id: string;
  platform_integration_id: string;
  logo_url: string;
  is_default: boolean;
  created_at: string;
  updated_at: string;
}

interface TenantIntegration {
  id: string;
  tenant_id: string;
  integration_key: string;
  is_enabled: boolean;
  settings: Record<string, unknown>;
}

// =====================================================
// ORDER VIEW & TRANSACTION TYPES
// =====================================================

type OrderWithPatient = Order & {
  subscription?: {
    id: string;
    status:
      | "active"
      | "paused"
      | "cancelled"
      | "pending_validation"
      | "pending_cancellation";
    current_period_end_at: string | null;
  } | null;
  order_statuses?: {
    id: string;
    status_key: string;
    admin_status_label: string;
    patient_status_label: string | null;
    is_terminal: boolean;
    next_step_owner: string;
  } | null;
  product?: {
    id: string;
    name: string;
  } | null;
  patients: {
    id: string;
    first_name: string;
    last_name: string;
    email: string;
    phone: string | null;
    // Address fields for "Copy from Patient" feature
    shipping_first_name: string | null;
    shipping_last_name: string | null;
    shipping_company: string | null;
    shipping_address_line1: string | null;
    shipping_address_line2: string | null;
    shipping_city: string | null;
    shipping_state: string | null;
    shipping_postal_code: string | null;
    shipping_country: string | null;
    shipping_instructions: string | null;
    billing_first_name: string | null;
    billing_last_name: string | null;
    billing_company: string | null;
    billing_address_line1: string | null;
    billing_address_line2: string | null;
    billing_city: string | null;
    billing_state: string | null;
    billing_postal_code: string | null;
    billing_country: string | null;
    // Fallback to primary address
    address_line1: string | null;
    address_line2: string | null;
    city: string | null;
    state: string | null;
    postal_code: string | null;
    country: string | null;
  };
};

interface OrderUpdateData {
  status_id?: string;
  tracking_number?: string | null;
  tracking_url?: string | null;
  internal_notes?: string | null;
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
  billing_first_name?: string | null;
  billing_last_name?: string | null;
  billing_company?: string | null;
  billing_address_line1?: string | null;
  billing_address_line2?: string | null;
  billing_city?: string | null;
  billing_state?: string | null;
  billing_postal_code?: string | null;
  billing_country?: string | null;
}

interface OrderPaymentProviderTransaction {
  id: string;
  payment_status: string | null;
  paid_at: string | null;
  provider_payment_intent_id: string | null;
  provider_invoice_id: string | null;
  provider_charge_id: string | null;
  provider_subscription_id: string | null;
  provider_checkout_session_id: string | null;
  created_at: string;
  provider: {
    name: string;
    key: string;
  } | null;
}
