# Friendbuy Implementation Status

## Summary

The Friendbuy referral flow has been implemented as a multi-tenant integration across PP-admin/Nexus and Patient UI. Patient UI owns the referral banner and credits display, while Friendbuy owns the hosted invite form, referral emails, Stripe coupon generation, and referrer reward behavior.

No tenant is enabled by default. Tenants must opt in with the `friendbuy_referrals` feature flag and an enabled `friendbuy` tenant integration.

## Implemented Backend/Admin Work

- Added the `friendbuy_referrals` feature flag, default off.
- Added the `friendbuy` platform integration settings:
  - `merchant_id`
  - `campaign_id`
  - `mount_element_id`
  - `secret_key` (verifies inbound webhook signatures only)
  - `api_key` / `api_secret_key` (Merchant API credential pair)
  - `placement`
  - `banner_title`
  - `reward_label`
- Added multi-tenant referral tables:
  - `referral_program_configs`
  - `referral_records`
  - `referral_sync_events`
  - `referral_reward_actions`
  - `friendbuy_event_logs`
- Added safe tenant-info exposure:
  - `integrations.friendbuy`
  - `integrations.referral_program`
  - Secrets are not exposed to Patient UI.
- Added shared Friendbuy backend helper for:
  - tenant config lookup
  - Merchant API bearer-token auth
  - signup conversion events
  - purchase conversion events
  - pending referral reward total lookup (DB-derived; the "available" balance
    is read live from Friendbuy's Merchant API `GET /ledger-balance`, keyed on
    `patient.id`, see `GET /friendbuy/balance`)
  - webhook payload ingestion, dispatched by Friendbuy's real webhook
    event types (`advocateReward`/`friendIncentive`/`emailCapture`/
    `emailOptOut`, plus recognized-but-ignored Loyalty-only events)
  - idempotent event logging
- Added authenticated Patient API routes:
  - `POST /friendbuy/attribution`
  - `GET /friendbuy/balance`
- Wired signup tracking:
  - Patient UI sends stored Friendbuy attribution with `/auth/signup`.
  - Patient API persists attribution into `patients.metadata.friendbuy_attribution`.
  - Patient API sends Friendbuy signup conversion after successful account creation.
- Wired purchase tracking:
  - RTDH `payment_collected` direct status events trigger Friendbuy purchase tracking.
  - Replayed/already-collected RTDH events also trigger the idempotent purchase tracker.
  - Stripe/payment-intent collection paths trigger Friendbuy purchase tracking after payment is collected.
  - Existing Stripe webhook invoice/order collection paths also trigger the same idempotent purchase tracker.
  - Duplicate sends are prevented by `friendbuy_event_logs`.
- Added `friendbuy-webhook` for push-based webhook ingestion. Requests are
  authenticated by recomputing `Base64(HMAC-SHA256(secret_key, raw_body))`
  and comparing it (constant-time) against the `X-Friendbuy-Hmac-SHA256`
  header, using the tenant's Merchant API `secret_key` — there is no
  separate webhook secret setting.
- Added `friendbuy-reconcile` for a narrow pull covering the only two things
  Friendbuy's webhook system never reports: a reward's final credited/rejected
  outcome (Merchant API `GetReferralRewards`) and coupon distribution/redemption
  (Merchant API `GetCoupons`). Not a general-purpose multi-collection sync —
  every other referral signal already arrives via `friendbuy-webhook`.
  Runs on an hourly `pg_cron` schedule (authenticated with `CRON_SECRET`,
  loops the Friendbuy-enabled tenants, 7-day lookback), with the admin-triggered
  "Reconcile" button retained for on-demand/backfill pulls.
- Added PP-admin referral settings page:
  - tenant referral status/currency/reward display fallback editing
  - per-code tracking/search over synced referral records
  - issuance, delivery, redemption, validity/expiry, and reward status columns
  - lifecycle, validity, and reward filters
  - per-code detail dialog with Friendbuy/Stripe ids, sync events, and raw payload
  - "Reconcile rewards & coupons" button (`friendbuy-reconcile`)
  - audit log entries for display config changes

PP-admin does not currently create or configure Friendbuy/Stripe coupon rules.
Coupon amount, minimum purchase, validity, redemption limits, invite copy, and
reward economics are managed in Friendbuy/Stripe.

## Implemented Patient UI Work

- Added Friendbuy tenant feature flag typing.
- Added safe Friendbuy and referral program tenant types.
- Added Friendbuy SDK service that:
  - initializes `window.friendbuyAPI`
  - pushes the tenant `merchant_id`
  - loads `https://static.fbot.me/friendbuy.js` globally on app load
  - loads `https://campaign.fbot.me/{merchant_id}/campaigns.js` separately,
    only from the referral widget itself once its hosted-widget mount
    container already exists in the DOM (`ensureCampaignScript`), since that
    script is what scans the page for the container and renders the widget
    into it
  - tracks authenticated customers
  - captures referral attribution
  - persists attribution to Patient API
  - fetches referral credit balance from Patient API
- Added global `FriendbuyProvider`.
- Added dashboard referral surface gated by:
  - `friendbuy_referrals`
  - safe Friendbuy tenant config
  - authenticated patient
- Added Figma-aligned referral banner:
  - purple credits card
  - balance display
  - pending pill
  - refill helper text
  - referral steps
  - tenant-safe reward label fallback
- Rendered Friendbuy hosted widget mount directly below the banner.
- The default PP mount element id is `friendbuy-referral-widget`. In Friendbuy's
  "HTML Elements to insert widget into" setting, enter the CSS selector
  `#friendbuy-referral-widget`.
- The PP-owned referral banner title is tenant-configurable with
  `banner_title`. For Brello/Figma parity, set it to `Brello Bestie`.
- PP-UI does not create the invite form itself. The email input, send invite button, copy link button, emails, and form styling come from Friendbuy.

## Multi-Tenant Behavior

- Patient UI initializes Friendbuy from `tenant-info`; there are no hardcoded tenant IDs.
- Backend conversion events look up Friendbuy credentials by `tenant_id`.
- Purchase events are scoped by the order's tenant.
- Webhook ingestion supports tenant routing by:
  - `x-tenant-id`
  - `tenant_id`
  - `tenantId`
  - `x-tenant-slug`
  - `tenant_slug`
  - `tenantSlug`
- For Brello, use:

```txt
https://<supabase-project>.functions.supabase.co/friendbuy-webhook?tenant_slug=brello
```

## Friendbuy/Stripe Ownership

- Friendbuy owns the hosted referral UI.
- Friendbuy sends referral emails.
- Friendbuy creates and delivers friend-side Stripe coupons through its Stripe integration.
- Friendbuy handles referrer rewards through its Stripe integration.
- Patient Platform sends signup and purchase conversion events so Friendbuy can attribute and reward correctly.
- Patient Platform stores Friendbuy reward and referral lifecycle data for admin visibility, populated via the real-time `friendbuy-webhook` receiver, plus a narrow `friendbuy-reconcile` pull for the two things the webhook never reports (reward outcome, coupon distribution/redemption).

## Verification Completed

The following checks passed:

```txt
deno test --allow-env --allow-net supabase/functions/_shared/friendbuy.test.ts
deno check supabase/functions/patient-api/index.ts supabase/functions/rtdh-webhook/index.ts supabase/functions/order-lifecycle/stripe-helper.ts supabase/functions/stripe-webhook/index.ts supabase/functions/friendbuy-webhook/index.ts supabase/functions/friendbuy-reconcile/index.ts supabase/functions/tenant-info/index.ts
npx vitest run src/components/friendbuy/FriendbuyReferralWidget.test.tsx
npm run build
```

Patient UI build completes successfully. It still logs the existing tenant-branding runtime fallback warning when branding sync cannot fetch remote tenant branding during build.

The webhook signature fix and Patient UI campaign-script timing fix were verified separately:

```txt
deno test --no-check supabase/functions/_shared/friendbuy.test.ts   # includes verifyFriendbuyWebhookSignature cases
deno check supabase/functions/_shared/friendbuy.ts supabase/functions/friendbuy-webhook/index.ts
deno lint supabase/functions/_shared/friendbuy.ts supabase/functions/friendbuy-webhook/index.ts
npx tsc --noEmit -p .   # both repos
npx vitest run src/services/friendbuyService.test.ts src/components/friendbuy/FriendbuyReferralWidget.test.tsx src/components/friendbuy/FriendbuyProvider.test.tsx
```

## Remaining Caveats

- There is no existing route-level Patient API test harness, so `/friendbuy/attribution` and `/friendbuy/balance` are type-checked but not covered by route tests.
- `friendbuy-reconcile` only fetches one page per invocation from each of `GetReferralRewards`/`GetCoupons`; `nextPageToken` isn't surfaced back to the caller, so a tenant with more than one page of either collection is only partially reconciled per click.
- GetCoupons' real response has no expiry field, so coupon validity window/expiry still isn't captured by any current source (webhook or reconciliation).
- Of the real Friendbuy **webhook** payload mappers, `advocateReward` and `emailCapture` have been verified against real webhook payloads (including a real-payload bug fix: `emailCapture` items carry no per-item `createdOn`, only the envelope does). `friendIncentive` is still built from published API docs, not a real webhook delivery, and should be validated before relying on it in production.
- Friendbuy campaign economics must still be configured in Friendbuy unless Friendbuy provides a confirmed writable campaign configuration API.
- Tenants must still be configured manually:
  - enable `friendbuy_referrals`
  - enable the tenant `friendbuy` integration
  - add Friendbuy credentials and IDs
  - configure Friendbuy Stripe integration
  - set Friendbuy's widget mount HTML element selector to `#friendbuy-referral-widget`
  - configure webhook URL with tenant slug
