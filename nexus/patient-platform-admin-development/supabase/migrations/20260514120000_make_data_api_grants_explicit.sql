-- Supabase Data API grants are no longer implicit for new public tables.
-- Keep RLS as the authorization boundary, but make API role access explicit.

GRANT USAGE ON SCHEMA public TO anon, authenticated, service_role;

GRANT SELECT
  ON ALL TABLES IN SCHEMA public
  TO anon;

GRANT SELECT, INSERT, UPDATE, DELETE
  ON ALL TABLES IN SCHEMA public
  TO authenticated;

GRANT SELECT, INSERT, UPDATE, DELETE
  ON ALL TABLES IN SCHEMA public
  TO service_role;

GRANT USAGE, SELECT
  ON ALL SEQUENCES IN SCHEMA public
  TO anon, authenticated, service_role;
