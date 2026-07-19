-- Tracks admin-initiated "trigger renewal" actions so a reconciliation sweep can
-- detect a "charged but no order" gap (Stripe billed but RTDH never sent
-- renewal_order_create). Scoped to manual triggers only; natural renewals flow
-- through the proven Stripe -> RTDH path.
CREATE TABLE IF NOT EXISTS public.renewal_trigger_attempts (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
  subscription_id UUID NOT NULL REFERENCES public.subscriptions(id) ON DELETE CASCADE,
  provider_subscription_id TEXT,
  triggered_by_email TEXT,
  triggered_at TIMESTAMPTZ NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending'
    CHECK (status IN ('pending', 'fulfilled', 'unresolved')),
  resolved_order_id UUID REFERENCES public.orders(id) ON DELETE SET NULL,
  resolved_at TIMESTAMPTZ,
  notes TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- The sweep scans pending attempts oldest-first.
CREATE INDEX IF NOT EXISTS idx_renewal_trigger_attempts_status_triggered_at
  ON public.renewal_trigger_attempts (status, triggered_at);

CREATE INDEX IF NOT EXISTS idx_renewal_trigger_attempts_subscription
  ON public.renewal_trigger_attempts (subscription_id, triggered_at DESC);

ALTER TABLE public.renewal_trigger_attempts ENABLE ROW LEVEL SECURITY;

-- Written and read only by edge functions using the service role
-- (trigger-renewal endpoint + renewal-trigger-reconcile sweep).
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'renewal_trigger_attempts'
      AND policyname = 'service_role_full_access_renewal_trigger_attempts'
  ) THEN
    CREATE POLICY "service_role_full_access_renewal_trigger_attempts"
      ON public.renewal_trigger_attempts
      FOR ALL
      TO service_role
      USING (true)
      WITH CHECK (true);
  END IF;
END $$;

COMMENT ON TABLE public.renewal_trigger_attempts IS
  'Audit + reconciliation record for admin-triggered renewals; a sweep marks each fulfilled or unresolved.';
