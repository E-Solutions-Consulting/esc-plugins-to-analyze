-- The automations trigger dropdown is fed by analytics_event_types (is_active).
-- THE RULE (see _shared/platform-events.ts): an event is offered only if the
-- platform actually emits it. Three seeded names have no emitter:
--   * page_view      — captured as event_type='page_view' with event_name NULL,
--                      so an event-NAME trigger on it can never match
--                      (comms only matches named `track` events).
--   * session_start / session_end — the SDK never enqueues these types;
--                      sessions are server-side rows, not events.
-- Deactivate them so nobody builds an automation that can never fire.
-- Re-activate if/when a producer really emits them.
UPDATE public.analytics_event_types
SET is_active = false
WHERE key IN ('page_view', 'session_start', 'session_end');
