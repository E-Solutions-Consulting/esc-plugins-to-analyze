-- Rename 'name' to 'title' for medications
ALTER TABLE public.medications RENAME COLUMN name TO title;

-- Add new columns
ALTER TABLE public.medications ADD COLUMN description text;
ALTER TABLE public.medications ADD COLUMN provider_sku text;
ALTER TABLE public.medications ADD COLUMN image_url text;

-- Remove unused columns
ALTER TABLE public.medications DROP COLUMN IF EXISTS generic_name;
ALTER TABLE public.medications DROP COLUMN IF EXISTS dosage;
ALTER TABLE public.medications DROP COLUMN IF EXISTS dosage_unit;