-- Store platform-level logo options for provider platform integrations.
-- Tenant-level provider logo overrides are intentionally deferred.

CREATE TABLE IF NOT EXISTS public.provider_logo_assets (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  platform_integration_id UUID NOT NULL REFERENCES public.platform_integrations(id) ON DELETE CASCADE,
  logo_url TEXT NOT NULL,
  is_default BOOLEAN NOT NULL DEFAULT false,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT provider_logo_assets_logo_url_not_empty CHECK (length(trim(logo_url)) > 0)
);

CREATE UNIQUE INDEX IF NOT EXISTS provider_logo_assets_one_default_per_provider
  ON public.provider_logo_assets (platform_integration_id)
  WHERE is_default;

CREATE INDEX IF NOT EXISTS provider_logo_assets_platform_integration_id_idx
  ON public.provider_logo_assets (platform_integration_id);

ALTER TABLE public.provider_logo_assets ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'provider_logo_assets'
      AND policyname = 'Authenticated users can view provider logo assets'
  ) THEN
    CREATE POLICY "Authenticated users can view provider logo assets"
      ON public.provider_logo_assets
      FOR SELECT
      TO authenticated
      USING (true);
  END IF;
END $$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'provider_logo_assets'
      AND policyname = 'Platform superadmins can manage provider logo assets'
  ) THEN
    CREATE POLICY "Platform superadmins can manage provider logo assets"
      ON public.provider_logo_assets
      FOR ALL
      USING (public.is_platform_superadmin(auth.uid()))
      WITH CHECK (public.is_platform_superadmin(auth.uid()));
  END IF;
END $$;

CREATE OR REPLACE FUNCTION public.validate_provider_logo_asset()
RETURNS TRIGGER
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM public.platform_integrations
    WHERE id = NEW.platform_integration_id
      AND category = 'provider_platform'
  ) THEN
    RAISE EXCEPTION 'Provider logo assets can only be attached to provider platform integrations';
  END IF;

  NEW.logo_url = trim(NEW.logo_url);
  RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS validate_provider_logo_asset_before_write ON public.provider_logo_assets;
CREATE TRIGGER validate_provider_logo_asset_before_write
  BEFORE INSERT OR UPDATE ON public.provider_logo_assets
  FOR EACH ROW
  EXECUTE FUNCTION public.validate_provider_logo_asset();

DROP TRIGGER IF EXISTS update_provider_logo_assets_updated_at ON public.provider_logo_assets;
CREATE TRIGGER update_provider_logo_assets_updated_at
  BEFORE UPDATE ON public.provider_logo_assets
  FOR EACH ROW
  EXECUTE FUNCTION public.update_updated_at_column();

CREATE OR REPLACE FUNCTION public.sync_provider_default_logo_url()
RETURNS TRIGGER
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  affected_platform_integration_id UUID;
  default_logo_url TEXT;
BEGIN
  affected_platform_integration_id = COALESCE(NEW.platform_integration_id, OLD.platform_integration_id);

  SELECT logo_url
  INTO default_logo_url
  FROM public.provider_logo_assets
  WHERE platform_integration_id = affected_platform_integration_id
    AND is_default
  LIMIT 1;

  UPDATE public.platform_integrations
  SET logo_url = default_logo_url
  WHERE id = affected_platform_integration_id;

  IF TG_OP = 'DELETE' THEN
    RETURN OLD;
  END IF;

  RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS sync_provider_default_logo_url_after_write ON public.provider_logo_assets;
CREATE TRIGGER sync_provider_default_logo_url_after_write
  AFTER INSERT OR UPDATE OR DELETE ON public.provider_logo_assets
  FOR EACH ROW
  EXECUTE FUNCTION public.sync_provider_default_logo_url();

INSERT INTO public.provider_logo_assets (
  platform_integration_id,
  logo_url,
  is_default
)
SELECT
  pi.id,
  pi.logo_url,
  true
FROM public.platform_integrations pi
WHERE pi.category = 'provider_platform'
  AND pi.logo_url IS NOT NULL
  AND length(trim(pi.logo_url)) > 0
  AND NOT EXISTS (
    SELECT 1
    FROM public.provider_logo_assets pla
    WHERE pla.platform_integration_id = pi.id
      AND pla.logo_url = trim(pi.logo_url)
  );
