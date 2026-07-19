-- Drop legacy non-prefixed address columns from patients table.
-- These columns are superseded by shipping_* and billing_* prefixed columns.
ALTER TABLE patients
  DROP COLUMN IF EXISTS address_line1,
  DROP COLUMN IF EXISTS address_line2,
  DROP COLUMN IF EXISTS city,
  DROP COLUMN IF EXISTS state,
  DROP COLUMN IF EXISTS postal_code;
