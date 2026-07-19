// =====================================================
// APPLICATION CONSTANTS
// =====================================================

export const APP_NAME = 'Allia Patient Platform';

export const ROUTES = {
  // Public routes
  LOGIN: '/login',
  SIGNUP: '/signup',
  FORGOT_PASSWORD: '/forgot-password',
  RESET_PASSWORD: '/reset-password',
  
  // Tenant Admin routes
  TENANT_ADMIN: {
    ROOT: '/tenant-admin',
    DASHBOARD: '/tenant-admin/dashboard',
    PROFILE: '/tenant-admin/profile',
    ANALYTICS: '/tenant-admin/analytics',
    PRODUCT_USAGE: '/tenant-admin/product-usage',
    PATIENTS: '/tenant-admin/patients',
    PATIENT_DETAIL: '/tenant-admin/patients/:id',
    SUBSCRIPTIONS: '/tenant-admin/subscriptions',
    SUBSCRIPTION_DETAIL: '/tenant-admin/subscriptions/:id',
    ORDERS: '/tenant-admin/orders',
    ORDER_DETAIL: '/tenant-admin/orders/:id',
    AUTOMATIONS: '/tenant-admin/automations',
    AUTOMATION_TEMPLATES: '/tenant-admin/automations/templates',
    AUTOMATION_DETAIL: '/tenant-admin/automations/:id',
    CATALOG: {
      ROOT: '/tenant-admin/catalog',
      MEDICATIONS: '/tenant-admin/catalog/medications',
      MEDICATION_DETAIL: '/tenant-admin/catalog/medications/:id',
      PRODUCTS: '/tenant-admin/catalog/products',
      PRODUCT_DETAIL: '/tenant-admin/catalog/products/:id',
    },
    SETTINGS: {
      ROOT: '/tenant-admin/settings',
      GENERAL: '/tenant-admin/settings/general',
      BRANDING: '/tenant-admin/settings/branding',
      NOTIFICATIONS: '/tenant-admin/settings/notifications',
      SECURITY: '/tenant-admin/settings/security',
      FEATURE_FLAGS: '/tenant-admin/settings/feature-flags',
      INTEGRATIONS: '/tenant-admin/settings/integrations',
      DEPLOYMENTS: '/tenant-admin/settings/deployments',
      PRODUCT_USAGE_TRACKING: '/tenant-admin/settings/product-usage-tracking',
      TERMS_AND_CONDITIONS: '/tenant-admin/settings/terms-and-conditions',
      PRIVACY_POLICY: '/tenant-admin/settings/privacy-policy',
      ADMINS: '/tenant-admin/settings/admins',
      MODULES: '/tenant-admin/settings/modules',
      AUDIT_LOGS: '/tenant-admin/settings/audit-logs',
      PAYMENT_PROVIDERS: '/tenant-admin/settings/payment-providers',
    },
  },
  
  // Platform Superadmin routes
  PLATFORM_ADMIN: {
    ROOT: '/platform-superadmin',
    DASHBOARD: '/platform-superadmin/dashboard',
    PROFILE: '/platform-superadmin/profile',
    TENANTS: '/platform-superadmin/tenants',
    TENANT_DETAIL: '/platform-superadmin/tenants/:id',
    FEATURE_FLAGS: '/platform-superadmin/feature-flags',
    MEDICATION_CAPABILITIES: '/platform-superadmin/medication-capabilities',
    PRODUCT_CATEGORIES: '/platform-superadmin/product-categories',
    PAYMENT_PROVIDERS: '/platform-superadmin/payment-providers',
    ORDER_STATUSES: '/platform-superadmin/order-statuses',
    ADMINS: '/platform-superadmin/admins',
    AUDIT_LOGS: '/platform-superadmin/audit-logs',
    SETTINGS: '/platform-superadmin/settings',
    INTEGRATIONS: '/platform-superadmin/integrations',
  },
} as const;

export const STATUS_COLORS = {
  // Patient access status
  active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
  suspended: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
  deactivated: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
  
  // Order status
  pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
  processing: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
  shipped: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
  delivered: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
  cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
  pending_cancellation: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
  paused: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
  
  // Tenant status
  inactive: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
  
  // Readiness status
  not_started: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
  in_progress: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
  ready: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
} as const;

export const MODULE_KEYS = {
  PROVIDER_PLATFORM: 'provider_platform',
  PHARMACY: 'pharmacy',
  FULFILLMENT: 'fulfillment',
  SHIPPING_TRACKING: 'shipping_tracking',
  MESSAGING: 'messaging',
  VIDEO: 'video',
  PAYMENTS: 'payments',
  AI_MODULES: 'ai_modules',
} as const;

export const FEATURE_FLAG_DEFAULTS = [
  { key: 'enable_telehealth', name: 'Enable Telehealth', description: 'Enable video consultations' },
  { key: 'enable_messaging', name: 'Enable Messaging', description: 'Enable in-app messaging' },
  { key: 'enable_ai_insights', name: 'Enable AI Insights', description: 'Enable AI-powered analytics' },
] as const;

export const PAGINATION = {
  DEFAULT_PAGE_SIZE: 10,
  PAGE_SIZE_OPTIONS: [10, 25, 50, 100],
} as const;

export const DATE_FORMATS = {
  DISPLAY: 'MMM D, YYYY',
  DISPLAY_WITH_TIME: 'MMM D, YYYY h:mm A',
  INPUT: 'YYYY-MM-DD',
  API: 'YYYY-MM-DDTHH:mm:ss.SSSZ',
} as const;
