UPDATE public.tenant_payment_providers
SET settings = COALESCE(settings, '{}'::jsonb) - 'webhook_secret'
WHERE COALESCE(settings, '{}'::jsonb) ? 'webhook_secret'
  AND payment_provider_id IN (
    SELECT id
    FROM public.payment_providers
    WHERE key = 'stripe'
  );
