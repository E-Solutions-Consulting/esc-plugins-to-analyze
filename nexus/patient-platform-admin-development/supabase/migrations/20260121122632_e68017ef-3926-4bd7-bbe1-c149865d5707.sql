-- =====================================================
-- ALLIA CARE MULTI-TENANT SAAS PLATFORM SCHEMA
-- Foundation: Roles, Tenants, Audit, Core Entities
-- =====================================================

-- 1. ENUM TYPES
-- =====================================================

-- Application roles
CREATE TYPE public.app_role AS ENUM ('platform_superadmin', 'tenant_admin');

-- Tenant status
CREATE TYPE public.tenant_status AS ENUM ('active', 'inactive', 'suspended', 'pending');

-- Patient access status
CREATE TYPE public.patient_access_status AS ENUM ('active', 'suspended', 'deactivated');

-- Subscription status
CREATE TYPE public.subscription_status AS ENUM ('active', 'paused', 'cancelled');

-- Order status
CREATE TYPE public.order_status AS ENUM ('pending', 'processing', 'shipped', 'delivered', 'cancelled', 'paused');

-- Readiness status for medications/products
CREATE TYPE public.readiness_status AS ENUM ('not_started', 'in_progress', 'ready');

-- Feature flag types
CREATE TYPE public.flag_type AS ENUM ('boolean', 'exclusive_option');


-- 2. TENANTS & BRANDING
-- =====================================================

CREATE TABLE public.tenants (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  status tenant_status NOT NULL DEFAULT 'pending',
  contact_email TEXT,
  contact_phone TEXT,
  metadata JSONB DEFAULT '{}',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE public.tenant_branding (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE UNIQUE,
  logo_url TEXT,
  primary_color TEXT DEFAULT '#3B82F6',
  secondary_color TEXT DEFAULT '#1E40AF',
  accent_color TEXT DEFAULT '#10B981',
  custom_css TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);


-- 3. ADMIN USERS & ROLE MEMBERSHIPS
-- =====================================================

CREATE TABLE public.admin_users (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  auth_user_id UUID NOT NULL UNIQUE,
  email TEXT NOT NULL UNIQUE,
  full_name TEXT,
  avatar_url TEXT,
  is_active BOOLEAN NOT NULL DEFAULT true,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Role assignments (separated table for security - NO roles on profile)
CREATE TABLE public.user_roles (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES public.admin_users(id) ON DELETE CASCADE,
  role app_role NOT NULL,
  UNIQUE(user_id, role)
);

-- Tenant membership (which admins belong to which tenants)
CREATE TABLE public.tenant_memberships (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  admin_user_id UUID NOT NULL REFERENCES public.admin_users(id) ON DELETE CASCADE,
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  is_primary BOOLEAN NOT NULL DEFAULT false,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE(admin_user_id, tenant_id)
);


-- 4. FEATURE FLAGS (GLOBAL & TENANT OVERRIDES)
-- =====================================================

CREATE TABLE public.feature_flags (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  key TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  description TEXT,
  flag_type flag_type NOT NULL DEFAULT 'boolean',
  default_value BOOLEAN NOT NULL DEFAULT false,
  is_active BOOLEAN NOT NULL DEFAULT true,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Exclusive flag sets (one-of flags)
CREATE TABLE public.exclusive_flag_sets (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name TEXT NOT NULL,
  description TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE public.exclusive_flag_options (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  set_id UUID NOT NULL REFERENCES public.exclusive_flag_sets(id) ON DELETE CASCADE,
  feature_flag_id UUID NOT NULL REFERENCES public.feature_flags(id) ON DELETE CASCADE,
  display_order INTEGER NOT NULL DEFAULT 0,
  UNIQUE(set_id, feature_flag_id)
);

-- Tenant-level flag overrides
CREATE TABLE public.tenant_feature_flag_overrides (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  feature_flag_id UUID NOT NULL REFERENCES public.feature_flags(id) ON DELETE CASCADE,
  enabled BOOLEAN NOT NULL,
  exclusive_option_id UUID REFERENCES public.exclusive_flag_options(id),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE(tenant_id, feature_flag_id)
);


-- 5. TENANT MODULE SUBSCRIPTIONS (SERVICE SUBSCRIPTIONS)
-- =====================================================

CREATE TABLE public.tenant_module_subscriptions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  module_key TEXT NOT NULL, -- e.g., 'provider_platform', 'pharmacy', 'fulfillment'
  is_enabled BOOLEAN NOT NULL DEFAULT false,
  config JSONB DEFAULT '{}',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE(tenant_id, module_key)
);


-- 6. PATIENTS (TENANT-SCOPED)
-- =====================================================

CREATE TABLE public.patients (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  external_id TEXT, -- ID from external systems
  email TEXT NOT NULL,
  first_name TEXT NOT NULL,
  last_name TEXT NOT NULL,
  date_of_birth DATE,
  phone TEXT,
  address_line1 TEXT,
  address_line2 TEXT,
  city TEXT,
  state TEXT,
  postal_code TEXT,
  country TEXT DEFAULT 'US',
  access_status patient_access_status NOT NULL DEFAULT 'active',
  -- Read-only placeholders for vitals/allergies/medications/conditions
  vitals JSONB DEFAULT '{}',
  allergies JSONB DEFAULT '[]',
  medications JSONB DEFAULT '[]',
  conditions JSONB DEFAULT '[]',
  metadata JSONB DEFAULT '{}',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE(tenant_id, email)
);

CREATE INDEX idx_patients_tenant ON public.patients(tenant_id);
CREATE INDEX idx_patients_email ON public.patients(email);
CREATE INDEX idx_patients_name ON public.patients(last_name, first_name);


-- 7. PRODUCTS & MEDICATIONS
-- =====================================================

-- Products represent "per product" questionnaire association
CREATE TABLE public.products (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  description TEXT,
  sku TEXT,
  price_cents INTEGER NOT NULL DEFAULT 0,
  is_enabled BOOLEAN NOT NULL DEFAULT false,
  -- Readiness states
  legal_ready readiness_status NOT NULL DEFAULT 'not_started',
  pharmacy_ready readiness_status NOT NULL DEFAULT 'not_started',
  provider_ready readiness_status NOT NULL DEFAULT 'not_started',
  metadata JSONB DEFAULT '{}',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE(tenant_id, sku)
);

CREATE TABLE public.medications (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  generic_name TEXT,
  dosage TEXT NOT NULL,
  dosage_unit TEXT NOT NULL DEFAULT 'mg',
  form TEXT DEFAULT 'tablet', -- tablet, capsule, injection, etc.
  is_enabled BOOLEAN NOT NULL DEFAULT false,
  -- External approval IDs
  provider_approval_id TEXT,
  pharmacy_approval_id TEXT,
  -- Readiness states
  legal_ready readiness_status NOT NULL DEFAULT 'not_started',
  pharmacy_ready readiness_status NOT NULL DEFAULT 'not_started',
  metadata JSONB DEFAULT '{}',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Product-Medication junction (many-to-many)
CREATE TABLE public.product_medications (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  product_id UUID NOT NULL REFERENCES public.products(id) ON DELETE CASCADE,
  medication_id UUID NOT NULL REFERENCES public.medications(id) ON DELETE CASCADE,
  quantity INTEGER NOT NULL DEFAULT 1,
  instructions TEXT,
  UNIQUE(product_id, medication_id)
);

CREATE INDEX idx_medications_tenant ON public.medications(tenant_id);
CREATE INDEX idx_products_tenant ON public.products(tenant_id);


-- 8. PROTOCOLS / LONGEVITY PACKS (BUNDLES)
-- =====================================================

CREATE TABLE public.protocols (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  description TEXT,
  price_cents INTEGER NOT NULL DEFAULT 0,
  is_enabled BOOLEAN NOT NULL DEFAULT false,
  duration_days INTEGER DEFAULT 30,
  metadata JSONB DEFAULT '{}',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Protocol-Product composition
CREATE TABLE public.protocol_products (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  protocol_id UUID NOT NULL REFERENCES public.protocols(id) ON DELETE CASCADE,
  product_id UUID NOT NULL REFERENCES public.products(id) ON DELETE CASCADE,
  display_order INTEGER NOT NULL DEFAULT 0,
  UNIQUE(protocol_id, product_id)
);

CREATE INDEX idx_protocols_tenant ON public.protocols(tenant_id);


-- 9. QUESTIONNAIRES
-- =====================================================

CREATE TABLE public.questionnaire_templates (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID REFERENCES public.tenants(id) ON DELETE CASCADE, -- NULL = shared template
  name TEXT NOT NULL,
  description TEXT,
  version INTEGER NOT NULL DEFAULT 1,
  schema JSONB NOT NULL DEFAULT '{}', -- JSON schema for questions
  is_shared BOOLEAN NOT NULL DEFAULT false,
  is_active BOOLEAN NOT NULL DEFAULT true,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Link questionnaires to products
CREATE TABLE public.product_questionnaire_links (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  product_id UUID NOT NULL REFERENCES public.products(id) ON DELETE CASCADE,
  questionnaire_template_id UUID NOT NULL REFERENCES public.questionnaire_templates(id) ON DELETE CASCADE,
  is_required BOOLEAN NOT NULL DEFAULT true,
  display_order INTEGER NOT NULL DEFAULT 0,
  UNIQUE(product_id, questionnaire_template_id)
);

-- Link questionnaires to protocols
CREATE TABLE public.protocol_questionnaire_links (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  protocol_id UUID NOT NULL REFERENCES public.protocols(id) ON DELETE CASCADE,
  questionnaire_template_id UUID NOT NULL REFERENCES public.questionnaire_templates(id) ON DELETE CASCADE,
  is_required BOOLEAN NOT NULL DEFAULT true,
  display_order INTEGER NOT NULL DEFAULT 0,
  UNIQUE(protocol_id, questionnaire_template_id)
);

CREATE INDEX idx_questionnaire_templates_tenant ON public.questionnaire_templates(tenant_id);


-- 10. SUBSCRIPTIONS (PATIENT → PRODUCT)
-- =====================================================

CREATE TABLE public.subscriptions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  patient_id UUID NOT NULL REFERENCES public.patients(id) ON DELETE CASCADE,
  product_id UUID REFERENCES public.products(id) ON DELETE SET NULL,
  protocol_id UUID REFERENCES public.protocols(id) ON DELETE SET NULL,
  status subscription_status NOT NULL DEFAULT 'active',
  billing_interval_days INTEGER NOT NULL DEFAULT 30,
  next_billing_date DATE,
  paused_at TIMESTAMPTZ,
  cancelled_at TIMESTAMPTZ,
  metadata JSONB DEFAULT '{}',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  -- Either product_id or protocol_id must be set
  CONSTRAINT subscription_target CHECK (product_id IS NOT NULL OR protocol_id IS NOT NULL)
);

CREATE INDEX idx_subscriptions_tenant ON public.subscriptions(tenant_id);
CREATE INDEX idx_subscriptions_patient ON public.subscriptions(patient_id);


-- 11. ORDERS
-- =====================================================

CREATE TABLE public.orders (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  patient_id UUID NOT NULL REFERENCES public.patients(id) ON DELETE CASCADE,
  subscription_id UUID REFERENCES public.subscriptions(id) ON DELETE SET NULL,
  order_number TEXT NOT NULL,
  status order_status NOT NULL DEFAULT 'pending',
  -- Shipping info
  shipping_address_line1 TEXT,
  shipping_address_line2 TEXT,
  shipping_city TEXT,
  shipping_state TEXT,
  shipping_postal_code TEXT,
  shipping_country TEXT DEFAULT 'US',
  -- Tracking
  tracking_number TEXT,
  tracking_url TEXT,
  -- Internal
  internal_notes TEXT,
  -- Amounts
  subtotal_cents INTEGER NOT NULL DEFAULT 0,
  shipping_cents INTEGER NOT NULL DEFAULT 0,
  tax_cents INTEGER NOT NULL DEFAULT 0,
  total_cents INTEGER NOT NULL DEFAULT 0,
  -- Timestamps
  shipped_at TIMESTAMPTZ,
  delivered_at TIMESTAMPTZ,
  cancelled_at TIMESTAMPTZ,
  paused_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_orders_tenant ON public.orders(tenant_id);
CREATE INDEX idx_orders_patient ON public.orders(patient_id);
CREATE INDEX idx_orders_status ON public.orders(status);
CREATE INDEX idx_orders_number ON public.orders(order_number);


-- 12. EXTERNAL APPROVAL LINKS
-- =====================================================

CREATE TABLE public.external_approval_links (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  entity_type TEXT NOT NULL, -- 'medication', 'product', 'protocol'
  entity_id UUID NOT NULL,
  provider_type TEXT NOT NULL, -- 'provider_platform', 'pharmacy', 'legal'
  external_id TEXT NOT NULL,
  status readiness_status NOT NULL DEFAULT 'not_started',
  config JSONB DEFAULT '{}',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE(tenant_id, entity_type, entity_id, provider_type)
);

CREATE INDEX idx_external_approval_tenant ON public.external_approval_links(tenant_id);


-- 13. AUDIT LOG
-- =====================================================

CREATE TABLE public.audit_logs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID REFERENCES public.tenants(id) ON DELETE SET NULL,
  actor_id UUID REFERENCES public.admin_users(id) ON DELETE SET NULL,
  actor_email TEXT,
  action TEXT NOT NULL,
  entity_type TEXT NOT NULL,
  entity_id UUID,
  before_data JSONB,
  after_data JSONB,
  diff JSONB,
  request_id UUID,
  ip_address INET,
  user_agent TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_audit_tenant ON public.audit_logs(tenant_id);
CREATE INDEX idx_audit_actor ON public.audit_logs(actor_id);
CREATE INDEX idx_audit_entity ON public.audit_logs(entity_type, entity_id);
CREATE INDEX idx_audit_created ON public.audit_logs(created_at DESC);


-- 14. TENANT SETTINGS
-- =====================================================

CREATE TABLE public.tenant_settings (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE UNIQUE,
  -- Notification settings
  notifications_email_enabled BOOLEAN NOT NULL DEFAULT true,
  notifications_sms_enabled BOOLEAN NOT NULL DEFAULT false,
  -- Security settings
  session_timeout_minutes INTEGER NOT NULL DEFAULT 60,
  require_mfa BOOLEAN NOT NULL DEFAULT false,
  -- General settings
  timezone TEXT NOT NULL DEFAULT 'America/New_York',
  date_format TEXT NOT NULL DEFAULT 'MM/DD/YYYY',
  currency TEXT NOT NULL DEFAULT 'USD',
  metadata JSONB DEFAULT '{}',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);


-- 15. SECURITY DEFINER FUNCTIONS FOR RBAC
-- =====================================================

-- Check if user has a specific role
CREATE OR REPLACE FUNCTION public.has_role(_user_id UUID, _role app_role)
RETURNS BOOLEAN
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT EXISTS (
    SELECT 1
    FROM public.user_roles ur
    JOIN public.admin_users au ON ur.user_id = au.id
    WHERE au.auth_user_id = _user_id
      AND ur.role = _role
  )
$$;

-- Check if user is platform superadmin
CREATE OR REPLACE FUNCTION public.is_platform_superadmin(_auth_user_id UUID)
RETURNS BOOLEAN
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT public.has_role(_auth_user_id, 'platform_superadmin')
$$;

-- Check if user is tenant admin for a specific tenant
CREATE OR REPLACE FUNCTION public.is_tenant_admin(_auth_user_id UUID, _tenant_id UUID)
RETURNS BOOLEAN
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT EXISTS (
    SELECT 1
    FROM public.admin_users au
    JOIN public.user_roles ur ON ur.user_id = au.id
    JOIN public.tenant_memberships tm ON tm.admin_user_id = au.id
    WHERE au.auth_user_id = _auth_user_id
      AND ur.role = 'tenant_admin'
      AND tm.tenant_id = _tenant_id
  )
$$;

-- Get user's tenant IDs
CREATE OR REPLACE FUNCTION public.get_user_tenant_ids(_auth_user_id UUID)
RETURNS SETOF UUID
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT tm.tenant_id
  FROM public.tenant_memberships tm
  JOIN public.admin_users au ON tm.admin_user_id = au.id
  WHERE au.auth_user_id = _auth_user_id
$$;

-- Get admin user ID from auth user ID
CREATE OR REPLACE FUNCTION public.get_admin_user_id(_auth_user_id UUID)
RETURNS UUID
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT id FROM public.admin_users WHERE auth_user_id = _auth_user_id LIMIT 1
$$;


-- 16. ENABLE RLS ON ALL TABLES
-- =====================================================

ALTER TABLE public.tenants ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tenant_branding ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.admin_users ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.user_roles ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tenant_memberships ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.feature_flags ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.exclusive_flag_sets ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.exclusive_flag_options ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tenant_feature_flag_overrides ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tenant_module_subscriptions ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.patients ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.products ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.medications ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.product_medications ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.protocols ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.protocol_products ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.questionnaire_templates ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.product_questionnaire_links ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.protocol_questionnaire_links ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.subscriptions ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.orders ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.external_approval_links ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.audit_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tenant_settings ENABLE ROW LEVEL SECURITY;


-- 17. RLS POLICIES
-- =====================================================

-- TENANTS: Superadmin can do all, tenant admin can view their own
CREATE POLICY "Superadmin can manage all tenants"
  ON public.tenants FOR ALL
  USING (public.is_platform_superadmin(auth.uid()));

CREATE POLICY "Tenant admins can view their tenant"
  ON public.tenants FOR SELECT
  USING (id IN (SELECT public.get_user_tenant_ids(auth.uid())));

-- TENANT BRANDING: Same as tenants
CREATE POLICY "Superadmin can manage all branding"
  ON public.tenant_branding FOR ALL
  USING (public.is_platform_superadmin(auth.uid()));

CREATE POLICY "Tenant admins can manage their branding"
  ON public.tenant_branding FOR ALL
  USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));

-- ADMIN USERS: Own profile + superadmin
CREATE POLICY "Users can view own profile"
  ON public.admin_users FOR SELECT
  USING (auth_user_id = auth.uid() OR public.is_platform_superadmin(auth.uid()));

CREATE POLICY "Users can update own profile"
  ON public.admin_users FOR UPDATE
  USING (auth_user_id = auth.uid());

CREATE POLICY "Superadmin can manage all admin users"
  ON public.admin_users FOR ALL
  USING (public.is_platform_superadmin(auth.uid()));

-- USER ROLES: Only superadmin and service role can manage
CREATE POLICY "Superadmin can manage all roles"
  ON public.user_roles FOR ALL
  USING (public.is_platform_superadmin(auth.uid()));

CREATE POLICY "Users can view own roles"
  ON public.user_roles FOR SELECT
  USING (user_id = public.get_admin_user_id(auth.uid()));

-- TENANT MEMBERSHIPS: Superadmin + own memberships
CREATE POLICY "Superadmin can manage all memberships"
  ON public.tenant_memberships FOR ALL
  USING (public.is_platform_superadmin(auth.uid()));

CREATE POLICY "Users can view own memberships"
  ON public.tenant_memberships FOR SELECT
  USING (admin_user_id = public.get_admin_user_id(auth.uid()));

-- FEATURE FLAGS: Superadmin full access, others read
CREATE POLICY "Superadmin can manage feature flags"
  ON public.feature_flags FOR ALL
  USING (public.is_platform_superadmin(auth.uid()));

CREATE POLICY "Authenticated users can view feature flags"
  ON public.feature_flags FOR SELECT
  USING (auth.uid() IS NOT NULL);

-- EXCLUSIVE FLAG SETS/OPTIONS: Superadmin only
CREATE POLICY "Superadmin can manage exclusive flag sets"
  ON public.exclusive_flag_sets FOR ALL
  USING (public.is_platform_superadmin(auth.uid()));

CREATE POLICY "Authenticated users can view exclusive flag sets"
  ON public.exclusive_flag_sets FOR SELECT
  USING (auth.uid() IS NOT NULL);

CREATE POLICY "Superadmin can manage exclusive flag options"
  ON public.exclusive_flag_options FOR ALL
  USING (public.is_platform_superadmin(auth.uid()));

CREATE POLICY "Authenticated users can view exclusive flag options"
  ON public.exclusive_flag_options FOR SELECT
  USING (auth.uid() IS NOT NULL);

-- TENANT FEATURE FLAG OVERRIDES: Superadmin + tenant admin
CREATE POLICY "Superadmin can manage all flag overrides"
  ON public.tenant_feature_flag_overrides FOR ALL
  USING (public.is_platform_superadmin(auth.uid()));

CREATE POLICY "Tenant admins can manage their flag overrides"
  ON public.tenant_feature_flag_overrides FOR ALL
  USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));

-- TENANT MODULE SUBSCRIPTIONS: Superadmin + tenant admin (read-only for tenant)
CREATE POLICY "Superadmin can manage all module subscriptions"
  ON public.tenant_module_subscriptions FOR ALL
  USING (public.is_platform_superadmin(auth.uid()));

CREATE POLICY "Tenant admins can view their module subscriptions"
  ON public.tenant_module_subscriptions FOR SELECT
  USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));

-- PATIENTS: Tenant-scoped only - NO superadmin access (PHI protection)
CREATE POLICY "Tenant admins can manage their patients"
  ON public.patients FOR ALL
  USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));

-- PRODUCTS: Tenant-scoped + superadmin view-only
CREATE POLICY "Tenant admins can manage their products"
  ON public.products FOR ALL
  USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));

CREATE POLICY "Superadmin can view products"
  ON public.products FOR SELECT
  USING (public.is_platform_superadmin(auth.uid()));

-- MEDICATIONS: Tenant-scoped
CREATE POLICY "Tenant admins can manage their medications"
  ON public.medications FOR ALL
  USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));

-- PRODUCT MEDICATIONS: Follow product access
CREATE POLICY "Access via product ownership"
  ON public.product_medications FOR ALL
  USING (
    product_id IN (
      SELECT id FROM public.products 
      WHERE tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid()))
    )
  );

-- PROTOCOLS: Tenant-scoped
CREATE POLICY "Tenant admins can manage their protocols"
  ON public.protocols FOR ALL
  USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));

-- PROTOCOL PRODUCTS: Follow protocol access
CREATE POLICY "Access via protocol ownership"
  ON public.protocol_products FOR ALL
  USING (
    protocol_id IN (
      SELECT id FROM public.protocols 
      WHERE tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid()))
    )
  );

-- QUESTIONNAIRE TEMPLATES: Tenant-scoped + shared templates
CREATE POLICY "Tenant admins can manage their templates"
  ON public.questionnaire_templates FOR ALL
  USING (
    (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())))
    OR (is_shared = true AND tenant_id IS NULL)
  );

CREATE POLICY "Superadmin can manage shared templates"
  ON public.questionnaire_templates FOR ALL
  USING (public.is_platform_superadmin(auth.uid()));

-- PRODUCT/PROTOCOL QUESTIONNAIRE LINKS: Follow product/protocol access
CREATE POLICY "Access via product ownership for questionnaire links"
  ON public.product_questionnaire_links FOR ALL
  USING (
    product_id IN (
      SELECT id FROM public.products 
      WHERE tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid()))
    )
  );

CREATE POLICY "Access via protocol ownership for questionnaire links"
  ON public.protocol_questionnaire_links FOR ALL
  USING (
    protocol_id IN (
      SELECT id FROM public.protocols 
      WHERE tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid()))
    )
  );

-- SUBSCRIPTIONS: Tenant-scoped only (PHI)
CREATE POLICY "Tenant admins can manage their subscriptions"
  ON public.subscriptions FOR ALL
  USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));

-- ORDERS: Tenant-scoped only (PHI)
CREATE POLICY "Tenant admins can manage their orders"
  ON public.orders FOR ALL
  USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));

-- EXTERNAL APPROVAL LINKS: Tenant-scoped
CREATE POLICY "Tenant admins can manage their approval links"
  ON public.external_approval_links FOR ALL
  USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));

-- AUDIT LOGS: Tenant-scoped read-only + superadmin full read
CREATE POLICY "Tenant admins can view their audit logs"
  ON public.audit_logs FOR SELECT
  USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));

CREATE POLICY "Superadmin can view all audit logs except PHI"
  ON public.audit_logs FOR SELECT
  USING (
    public.is_platform_superadmin(auth.uid())
    AND entity_type NOT IN ('patient', 'subscription', 'order')
  );

-- Allow insert for audit logs from authenticated users
CREATE POLICY "Authenticated users can insert audit logs"
  ON public.audit_logs FOR INSERT
  WITH CHECK (auth.uid() IS NOT NULL);

-- TENANT SETTINGS: Tenant-scoped
CREATE POLICY "Tenant admins can manage their settings"
  ON public.tenant_settings FOR ALL
  USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));

CREATE POLICY "Superadmin can view tenant settings"
  ON public.tenant_settings FOR SELECT
  USING (public.is_platform_superadmin(auth.uid()));


-- 18. UPDATED_AT TRIGGER FUNCTION
-- =====================================================

CREATE OR REPLACE FUNCTION public.update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql SET search_path = public;

-- Apply trigger to all tables with updated_at
CREATE TRIGGER update_tenants_updated_at BEFORE UPDATE ON public.tenants
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

CREATE TRIGGER update_tenant_branding_updated_at BEFORE UPDATE ON public.tenant_branding
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

CREATE TRIGGER update_admin_users_updated_at BEFORE UPDATE ON public.admin_users
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

CREATE TRIGGER update_feature_flags_updated_at BEFORE UPDATE ON public.feature_flags
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

CREATE TRIGGER update_tenant_feature_flag_overrides_updated_at BEFORE UPDATE ON public.tenant_feature_flag_overrides
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

CREATE TRIGGER update_tenant_module_subscriptions_updated_at BEFORE UPDATE ON public.tenant_module_subscriptions
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

CREATE TRIGGER update_patients_updated_at BEFORE UPDATE ON public.patients
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

CREATE TRIGGER update_products_updated_at BEFORE UPDATE ON public.products
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

CREATE TRIGGER update_medications_updated_at BEFORE UPDATE ON public.medications
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

CREATE TRIGGER update_protocols_updated_at BEFORE UPDATE ON public.protocols
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

CREATE TRIGGER update_questionnaire_templates_updated_at BEFORE UPDATE ON public.questionnaire_templates
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

CREATE TRIGGER update_subscriptions_updated_at BEFORE UPDATE ON public.subscriptions
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

CREATE TRIGGER update_orders_updated_at BEFORE UPDATE ON public.orders
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

CREATE TRIGGER update_external_approval_links_updated_at BEFORE UPDATE ON public.external_approval_links
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

CREATE TRIGGER update_tenant_settings_updated_at BEFORE UPDATE ON public.tenant_settings
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


-- 19. AUTO-CREATE ADMIN USER ON AUTH SIGNUP
-- =====================================================

CREATE OR REPLACE FUNCTION public.handle_new_user()
RETURNS TRIGGER AS $$
BEGIN
  INSERT INTO public.admin_users (auth_user_id, email, full_name)
  VALUES (
    NEW.id,
    NEW.email,
    COALESCE(NEW.raw_user_meta_data->>'full_name', NEW.email)
  );
  RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = public;

CREATE TRIGGER on_auth_user_created
  AFTER INSERT ON auth.users
  FOR EACH ROW
  EXECUTE FUNCTION public.handle_new_user();