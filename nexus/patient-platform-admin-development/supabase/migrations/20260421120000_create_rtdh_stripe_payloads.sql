CREATE TABLE IF NOT EXISTS public.rtdh_stripe_payloads (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID REFERENCES public.tenants(id) ON DELETE SET NULL,
  payment_provider_id UUID REFERENCES public.payment_providers(id) ON DELETE RESTRICT,
  stripe_event_id TEXT,
  stripe_event_type TEXT NOT NULL,
  stripe_object_id TEXT,
  stripe_object_type TEXT,
  stripe_created_at TIMESTAMPTZ,
  api_version TEXT,
  livemode BOOLEAN,
  stripe_customer_id TEXT,
  stripe_subscription_id TEXT,
  stripe_checkout_session_id TEXT,
  stripe_payment_intent_id TEXT,
  stripe_invoice_id TEXT,
  stripe_charge_id TEXT,
  customer_email TEXT,
  patient_id TEXT,
  product_id TEXT,
  payment_status TEXT,
  raw_payload JSONB NOT NULL DEFAULT '{}'::jsonb,
  request_id TEXT,
  is_processed BOOLEAN NOT NULL DEFAULT false,
  processed_at TIMESTAMPTZ,
  processing_error TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS uniq_rtdh_stripe_payloads_event_id
  ON public.rtdh_stripe_payloads(stripe_event_id)
  WHERE stripe_event_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_rtdh_stripe_payloads_tenant_id
  ON public.rtdh_stripe_payloads(tenant_id);

CREATE INDEX IF NOT EXISTS idx_rtdh_stripe_payloads_event_type
  ON public.rtdh_stripe_payloads(stripe_event_type);

CREATE INDEX IF NOT EXISTS idx_rtdh_stripe_payloads_processed_created
  ON public.rtdh_stripe_payloads(is_processed, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_rtdh_stripe_payloads_object_id
  ON public.rtdh_stripe_payloads(stripe_object_id)
  WHERE stripe_object_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_rtdh_stripe_payloads_customer_id
  ON public.rtdh_stripe_payloads(stripe_customer_id)
  WHERE stripe_customer_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_rtdh_stripe_payloads_subscription_id
  ON public.rtdh_stripe_payloads(stripe_subscription_id)
  WHERE stripe_subscription_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_rtdh_stripe_payloads_checkout_session_id
  ON public.rtdh_stripe_payloads(stripe_checkout_session_id)
  WHERE stripe_checkout_session_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_rtdh_stripe_payloads_payment_intent_id
  ON public.rtdh_stripe_payloads(stripe_payment_intent_id)
  WHERE stripe_payment_intent_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_rtdh_stripe_payloads_invoice_id
  ON public.rtdh_stripe_payloads(stripe_invoice_id)
  WHERE stripe_invoice_id IS NOT NULL;

ALTER TABLE public.rtdh_stripe_payloads ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_policies
    WHERE schemaname = 'public'
      AND tablename = 'rtdh_stripe_payloads'
      AND policyname = 'Tenant admins can manage Stripe payloads'
  ) THEN
    CREATE POLICY "Tenant admins can manage Stripe payloads"
      ON public.rtdh_stripe_payloads
      FOR ALL
      USING (tenant_id IN (SELECT public.get_user_tenant_ids(auth.uid())));
  END IF;
END $$;

COMMENT ON TABLE public.rtdh_stripe_payloads IS
  'Stores normalized + raw Stripe webhook payloads for all handled Stripe webhook event types.';

COMMENT ON COLUMN public.rtdh_stripe_payloads.created_at IS
  'Webhook payload receive timestamp. Defaults to now().';

COMMENT ON COLUMN public.rtdh_stripe_payloads.is_processed IS
  'False by default. Set to true after webhook processing completes.';
