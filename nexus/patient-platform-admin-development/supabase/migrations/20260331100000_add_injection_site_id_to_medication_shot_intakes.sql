ALTER TABLE public.medication_shot_intakes
ADD COLUMN IF NOT EXISTS injection_site_id UUID;

CREATE UNIQUE INDEX IF NOT EXISTS
  idx_tenant_injection_site_definitions_tenant_id_id_unique
  ON public.tenant_injection_site_definitions(tenant_id, id);

DO $$
BEGIN
  IF EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'medication_shot_intakes_injection_site_id_fkey'
      AND conrelid = 'public.medication_shot_intakes'::regclass
  ) THEN
    ALTER TABLE public.medication_shot_intakes
    DROP CONSTRAINT medication_shot_intakes_injection_site_id_fkey;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'medication_shot_intakes_injection_site_id_fkey'
      AND conrelid = 'public.medication_shot_intakes'::regclass
  ) THEN
    ALTER TABLE public.medication_shot_intakes
    ADD CONSTRAINT medication_shot_intakes_injection_site_id_fkey
    FOREIGN KEY (tenant_id, injection_site_id)
    REFERENCES public.tenant_injection_site_definitions(tenant_id, id)
    ON DELETE RESTRICT
    NOT VALID;
  END IF;
END
$$;

CREATE INDEX IF NOT EXISTS idx_medication_shot_intakes_injection_site_id
  ON public.medication_shot_intakes(injection_site_id);

DO $$
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = 'public'
      AND table_name = 'medication_shot_intakes'
      AND column_name = 'shot_location'
  ) THEN
    UPDATE public.medication_shot_intakes AS intake
    SET injection_site_id = site.id
    FROM public.tenant_injection_site_definitions AS site
    WHERE intake.injection_site_id IS NULL
      AND intake.tenant_id = site.tenant_id
      AND lower(intake.shot_location) = lower(site.label);
  END IF;
END
$$;

DO $$
BEGIN
  IF EXISTS (
    SELECT 1 FROM pg_constraint
    WHERE conname = 'medication_shot_intakes_injection_site_id_fkey'
      AND conrelid = 'public.medication_shot_intakes'::regclass
  ) THEN
    ALTER TABLE public.medication_shot_intakes
    VALIDATE CONSTRAINT medication_shot_intakes_injection_site_id_fkey;
  END IF;
END
$$;

ALTER TABLE public.medication_shot_intakes
DROP COLUMN IF EXISTS shot_location;

COMMENT ON COLUMN public.medication_shot_intakes.injection_site_id IS
  'Tenant injection site selected for the intake; historical rows may be null if they could not be mapped during migration';
