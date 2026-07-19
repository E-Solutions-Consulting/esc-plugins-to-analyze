CREATE TABLE IF NOT EXISTS public.shipping_availability_notifications (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  product_id UUID NOT NULL REFERENCES public.products(id) ON DELETE CASCADE,
  email TEXT NOT NULL,
  shipping_state TEXT NOT NULL,
  country TEXT NOT NULL DEFAULT 'US',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.shipping_availability_notifications
  ADD COLUMN IF NOT EXISTS id UUID DEFAULT gen_random_uuid(),
  ADD COLUMN IF NOT EXISTS tenant_id UUID,
  ADD COLUMN IF NOT EXISTS product_id UUID,
  ADD COLUMN IF NOT EXISTS email TEXT,
  ADD COLUMN IF NOT EXISTS shipping_state TEXT,
  ADD COLUMN IF NOT EXISTS country TEXT DEFAULT 'US',
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT now(),
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT now();

ALTER TABLE public.shipping_availability_notifications
  ALTER COLUMN id SET DEFAULT gen_random_uuid(),
  ALTER COLUMN country SET DEFAULT 'US',
  ALTER COLUMN created_at SET DEFAULT now(),
  ALTER COLUMN updated_at SET DEFAULT now();

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'shipping_availability_notifications_pkey'
      AND conrelid = 'public.shipping_availability_notifications'::regclass
  ) THEN
    ALTER TABLE public.shipping_availability_notifications
      ADD CONSTRAINT shipping_availability_notifications_pkey PRIMARY KEY (id);
  END IF;
END $$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'shipping_availability_notifications_tenant_id_fkey'
      AND conrelid = 'public.shipping_availability_notifications'::regclass
  ) THEN
    ALTER TABLE public.shipping_availability_notifications
      ADD CONSTRAINT shipping_availability_notifications_tenant_id_fkey
      FOREIGN KEY (tenant_id)
      REFERENCES public.tenants(id)
      ON DELETE CASCADE;
  END IF;
END $$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'shipping_availability_notifications_product_id_fkey'
      AND conrelid = 'public.shipping_availability_notifications'::regclass
  ) THEN
    ALTER TABLE public.shipping_availability_notifications
      ADD CONSTRAINT shipping_availability_notifications_product_id_fkey
      FOREIGN KEY (product_id)
      REFERENCES public.products(id)
      ON DELETE CASCADE;
  END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_shipping_availability_notifications_tenant_id
  ON public.shipping_availability_notifications(tenant_id);

CREATE INDEX IF NOT EXISTS idx_shipping_availability_notifications_product_id
  ON public.shipping_availability_notifications(product_id);

CREATE INDEX IF NOT EXISTS idx_shipping_availability_notifications_email
  ON public.shipping_availability_notifications(email);

CREATE UNIQUE INDEX IF NOT EXISTS uniq_shipping_availability_notifications_lookup
  ON public.shipping_availability_notifications(
    tenant_id,
    product_id,
    email,
    shipping_state,
    country
  );

ALTER TABLE public.shipping_availability_notifications ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'shipping_availability_notifications'
      AND policyname = 'Tenant admins can manage shipping availability notifications'
  ) THEN
    CREATE POLICY "Tenant admins can manage shipping availability notifications"
      ON public.shipping_availability_notifications
      FOR ALL
      USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));
  END IF;
END $$;

DO $$
BEGIN
  IF EXISTS (
    SELECT 1
    FROM pg_proc
    WHERE proname = 'update_updated_at_column'
      AND pg_function_is_visible(oid)
  ) AND NOT EXISTS (
    SELECT 1
    FROM pg_trigger
    WHERE tgname = 'update_shipping_availability_notifications_updated_at'
      AND tgrelid = 'public.shipping_availability_notifications'::regclass
  ) THEN
    CREATE TRIGGER update_shipping_availability_notifications_updated_at
      BEFORE UPDATE ON public.shipping_availability_notifications
      FOR EACH ROW
      EXECUTE FUNCTION public.update_updated_at_column();
  END IF;
END $$;

COMMENT ON TABLE public.shipping_availability_notifications IS
  'Emails captured from the patient signup flow for product shipping availability updates.';