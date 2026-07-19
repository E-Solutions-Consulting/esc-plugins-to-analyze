-- Migration: Drop rtdh_event_payloads table
-- Reason: Event logging moved to Edge Function console output; table no longer used

DROP TABLE IF EXISTS public.rtdh_event_payloads CASCADE;
