-- Add migration metadata and database-level idempotency anchors for patient
-- health tracking backfills.
--
-- Step 2 imports historical Brello rows. These columns let the importer use
-- the same upsert conflict key across all target tables:
--
--   tenant_id, patient_id, migration_source, migration_source_id, migration_source_item_key
--
-- For one-row source tables, migration_source_item_key should be 'row'.
-- For symptoms_log arrays, migration_source_item_key should be the normalized
-- migrated label, allowing multiple rows from the same Brello symptoms_log id.

ALTER TABLE public.patient_weight_entries
  ADD COLUMN IF NOT EXISTS metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  ADD COLUMN IF NOT EXISTS migration_source TEXT,
  ADD COLUMN IF NOT EXISTS migration_source_id TEXT,
  ADD COLUMN IF NOT EXISTS migration_source_item_key TEXT;

ALTER TABLE public.medication_shot_intakes
  ADD COLUMN IF NOT EXISTS metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  ADD COLUMN IF NOT EXISTS migration_source TEXT,
  ADD COLUMN IF NOT EXISTS migration_source_id TEXT,
  ADD COLUMN IF NOT EXISTS migration_source_item_key TEXT;

ALTER TABLE public.patient_symptom_entries
  ADD COLUMN IF NOT EXISTS metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  ADD COLUMN IF NOT EXISTS migration_source TEXT,
  ADD COLUMN IF NOT EXISTS migration_source_id TEXT,
  ADD COLUMN IF NOT EXISTS migration_source_item_key TEXT;

ALTER TABLE public.patient_mood_change_entries
  ADD COLUMN IF NOT EXISTS metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  ADD COLUMN IF NOT EXISTS migration_source TEXT,
  ADD COLUMN IF NOT EXISTS migration_source_id TEXT,
  ADD COLUMN IF NOT EXISTS migration_source_item_key TEXT;

ALTER TABLE public.patient_activity_entries
  ADD COLUMN IF NOT EXISTS metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  ADD COLUMN IF NOT EXISTS migration_source TEXT,
  ADD COLUMN IF NOT EXISTS migration_source_id TEXT,
  ADD COLUMN IF NOT EXISTS migration_source_item_key TEXT;

ALTER TABLE public.patient_body_measurement_entries
  ADD COLUMN IF NOT EXISTS metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  ADD COLUMN IF NOT EXISTS migration_source TEXT,
  ADD COLUMN IF NOT EXISTS migration_source_id TEXT,
  ADD COLUMN IF NOT EXISTS migration_source_item_key TEXT;

CREATE UNIQUE INDEX IF NOT EXISTS uniq_patient_weight_entries_migration_source
  ON public.patient_weight_entries (
    tenant_id,
    patient_id,
    migration_source,
    migration_source_id,
    migration_source_item_key
  );

CREATE UNIQUE INDEX IF NOT EXISTS uniq_medication_shot_intakes_migration_source
  ON public.medication_shot_intakes (
    tenant_id,
    patient_id,
    migration_source,
    migration_source_id,
    migration_source_item_key
  );

CREATE UNIQUE INDEX IF NOT EXISTS uniq_patient_body_measurement_entries_migration_source
  ON public.patient_body_measurement_entries (
    tenant_id,
    patient_id,
    migration_source,
    migration_source_id,
    migration_source_item_key
  );

CREATE UNIQUE INDEX IF NOT EXISTS uniq_patient_symptom_entries_migration_source
  ON public.patient_symptom_entries (
    tenant_id,
    patient_id,
    migration_source,
    migration_source_id,
    migration_source_item_key
  );

CREATE UNIQUE INDEX IF NOT EXISTS uniq_patient_mood_change_entries_migration_source
  ON public.patient_mood_change_entries (
    tenant_id,
    patient_id,
    migration_source,
    migration_source_id,
    migration_source_item_key
  );

CREATE UNIQUE INDEX IF NOT EXISTS uniq_patient_activity_entries_migration_source
  ON public.patient_activity_entries (
    tenant_id,
    patient_id,
    migration_source,
    migration_source_id,
    migration_source_item_key
  );

COMMENT ON COLUMN public.patient_weight_entries.metadata IS
  'Migration/source metadata. Step 2 stores Brello source values for audit and troubleshooting.';
COMMENT ON COLUMN public.patient_weight_entries.migration_source IS
  'Historical migration source system. Brello Step 2 uses brello.';
COMMENT ON COLUMN public.patient_weight_entries.migration_source_id IS
  'Legacy Brello source row id used for idempotent backfills.';
COMMENT ON COLUMN public.patient_weight_entries.migration_source_item_key IS
  'Stable per-source item key. One-row Brello tables use row.';

COMMENT ON COLUMN public.medication_shot_intakes.metadata IS
  'Migration/source metadata. Step 2 stores Brello source values for audit and troubleshooting.';
COMMENT ON COLUMN public.medication_shot_intakes.migration_source IS
  'Historical migration source system. Brello Step 2 uses brello.';
COMMENT ON COLUMN public.medication_shot_intakes.migration_source_id IS
  'Legacy Brello source row id used for idempotent backfills.';
COMMENT ON COLUMN public.medication_shot_intakes.migration_source_item_key IS
  'Stable per-source item key. One-row Brello tables use row.';

COMMENT ON COLUMN public.patient_body_measurement_entries.metadata IS
  'Migration/source metadata. Step 2 stores Brello source values for audit and troubleshooting.';
COMMENT ON COLUMN public.patient_body_measurement_entries.migration_source IS
  'Historical migration source system. Brello Step 2 uses brello.';
COMMENT ON COLUMN public.patient_body_measurement_entries.migration_source_id IS
  'Legacy Brello source row id used for idempotent backfills.';
COMMENT ON COLUMN public.patient_body_measurement_entries.migration_source_item_key IS
  'Stable per-source item key. One-row Brello tables use row.';

COMMENT ON COLUMN public.patient_symptom_entries.metadata IS
  'Migration/source metadata. Step 2 stores Brello source values for audit and troubleshooting.';
COMMENT ON COLUMN public.patient_symptom_entries.migration_source IS
  'Historical migration source system. Brello Step 2 uses brello.';
COMMENT ON COLUMN public.patient_symptom_entries.migration_source_id IS
  'Legacy Brello source row id used for idempotent backfills.';
COMMENT ON COLUMN public.patient_symptom_entries.migration_source_item_key IS
  'Stable per-source item key. Brello symptoms_log array rows use the normalized symptom label.';

COMMENT ON COLUMN public.patient_mood_change_entries.metadata IS
  'Migration/source metadata. Step 2 stores Brello source values for audit and troubleshooting.';
COMMENT ON COLUMN public.patient_mood_change_entries.migration_source IS
  'Historical migration source system. Brello Step 2 uses brello.';
COMMENT ON COLUMN public.patient_mood_change_entries.migration_source_id IS
  'Legacy Brello source row id used for idempotent backfills.';
COMMENT ON COLUMN public.patient_mood_change_entries.migration_source_item_key IS
  'Stable per-source item key. Brello symptoms_log array rows use the normalized mood label.';

COMMENT ON COLUMN public.patient_activity_entries.metadata IS
  'Migration/source metadata. Step 2 stores Brello source values for audit and troubleshooting.';
COMMENT ON COLUMN public.patient_activity_entries.migration_source IS
  'Historical migration source system. Brello Step 2 uses brello.';
COMMENT ON COLUMN public.patient_activity_entries.migration_source_id IS
  'Legacy Brello source row id used for idempotent backfills.';
COMMENT ON COLUMN public.patient_activity_entries.migration_source_item_key IS
  'Stable per-source item key. Brello symptoms_log array rows use the normalized activity label.';
