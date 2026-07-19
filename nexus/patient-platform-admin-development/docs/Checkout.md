# Checkout

The embedded checkout (`/products/:id/checkout` in the patient app) is
guest-first: no account or login is required before payment. This document
records the rules that hold it together and the constraints that shaped them —
most of them exist because something went wrong once.

## The payment-first invariant

> **No checkout step past payment may be reached — _or acted on_ — unless the
> order has actually been paid for.**

Steps 2–4 (contact details + email verification, the medical questionnaire,
provider intake) are downstream of payment. They must be unreachable, and
un-actionable, on an unpaid order.

**Enforce this on the server.** Hiding a step in the UI does not stop anyone from
calling the endpoint directly. This codebase has already shipped two bugs of
exactly this shape:

- the questionnaire-submit handler ran a state-blind `advanceOrderToNextStatus`
  and pushed `provider_review_pending → provider_approved` with **no real provider
  approval**, capturing $499 before the provider had approved;
- an inbound `order_sent_to_pharmacy` event skipped `provider_approved` and
  payment entirely, and a **$499 order shipped with `paid_at` still null**.

In both cases a step was reachable without its precondition.

`assertOrderPaid()` (`supabase/functions/plan-api/index.ts`) is the gate. It is
applied to `POST /orders/:id/resume`, which is what releases an order past the
contact-validation hold and toward the questionnaire and provider intake. A
manual-capture PaymentIntent in `requires_capture` is authorized and passes this
gate even though `paid_at` remains null until clinical approval triggers capture.

**A $0 order passes the gate.** A 100%-off coupon is legitimately unpaid. The
gate therefore accepts captured orders, authorized `requires_capture`
transactions, and zero-value orders.

## Why payment comes first

The buyer pays before an account, contact details, or a questionnaire exist.
Nothing downstream is reachable because, until the card is confirmed, **nothing
downstream exists**: no order, no patient, no account.

## The flow

The current flow is **authorize → confirm → finalize**: the card is authorized
*before* anything exists in our database, and the order is minted only after Stripe
confirms the money.

```
page load   Stripe Elements MOUNT immediately (deferred mode — see below).
            Nothing exists: no intent, no order, no patient, no account.

coupon      POST /checkout/quote          Prices a typed promo code. Creates
(optional)                                nothing — no account, no order, no
                                          intent. Returns the net amount and
                                          requires_payment=false when a 100%-off
                                          coupon zeroes the total.

Purchase    1. elements.submit()          Stripe validates the card.
            2. POST /checkout/authorize   Eligibility + duplicate-plan guards on
                                          the email, then a Stripe Customer +
                                          manual-capture PaymentIntent. Creates
                                          NOTHING in our DB — no patient, no
                                          order. THE LAST POINT AT WHICH WE CAN
                                          REFUSE WITHOUT MONEY MOVING.
            3. confirmPayment({ elements, clientSecret })
            4. POST /checkout/finalize    Re-retrieves the PaymentIntent from
                                          Stripe, requires an authorized status,
                                          then — and only then — creates the
                                          patient and the order.

step 2+     Everything downstream flows from that paid order, and is gated on it
            server-side.
```

### Why authorize/finalize replaced "payment-intent creates everything"

The legacy route `POST /orders/:id/payment-intent` created the **account and the
order before the card was charged**. A real dev test proved the consequence: a
failed payment left the buyer signed in, with a live $499 order sitting on their
dashboard at "Add Shipping Details", never paid for.

`POST /checkout/authorize` inverts that. It runs the same eligibility guards, then
creates a Stripe **Customer straight from the email** (not from a patient row —
none exists yet) and returns a PaymentIntent client secret. It creates **no
patient and no order**, so a declined card or a buyer who walks away leaves nothing
behind. All the context `finalize` needs is written onto the PaymentIntent
`metadata` (tenant, product, email, amount, discount, coupon, `checkout_flow=payment_first`)
— there is no order id or patient id to reference yet.

`POST /checkout/finalize` is the only place the order is created. It **re-retrieves
the PaymentIntent from Stripe** with the tenant's secret key and requires
`requires_capture` (the expected manual-capture state), `succeeded`, or `processing`
— the client is **never trusted** to say it paid — and checks the intent's metadata
matches this tenant and product. It is **idempotent on the payment intent id** (via
`order_payment_provider_transactions`), so a double-submit or a retried request
cannot mint two orders for one payment; a duplicate call returns the existing order.

**$0 (100%-off) still falls back to the legacy `POST /orders/:id/payment-intent`
route.** Stripe rejects a zero-amount PaymentIntent, and a $0 subscription still
needs a card on file for renewal (the SetupIntent path). Re-implementing that path
inside authorize/finalize was not worth the risk, so `quote` returns
`requires_payment=false` to tell the client to take the legacy/SetupIntent branch.

**Order creation is not duplicated.** The four phases — Stripe credentials, coupon
resolution, patient resolution, order creation — are extracted into shared helpers
(`resolveTenantStripe`, `resolveCheckoutCoupon`, and the patient/order builders)
used by **both** the new routes and the legacy payment-intent route, which keeps
its behaviour byte-for-byte. Capture semantics are unchanged: `paid_at` is still set
by the existing capture/webhook flow.

## Stripe deferred-intent mode

`<Elements>` normally needs a `clientSecret` to mount, which is why the card form
used to appear only *after* the buyer had filled in the entire form — the page had
to create an intent (and an order, and an account) first.

Stripe's **deferred intent mode** removes that: mount with `{ mode, amount,
currency }` and **no client secret**, then create the intent at submit time.

Two things follow from this, and both are load-bearing:

**1. Do not key `<Elements>` on the amount.** `<Elements>` remounts when its `key`
changes, and a remount **silently wipes the card the buyer has already typed**. In
deferred mode the key is constant and `elements.update({ amount })` applies a
coupon *in place*, so the card survives. (In the legacy `clientSecret` path the key
*must* be the secret, because Stripe ignores a changed `clientSecret` after mount —
a $0 subscription swaps a PaymentIntent secret for a SetupIntent secret, and
without the remount `confirmSetup` would run against the stale PaymentIntent.)

**2. $0 needs `mode: "setup"`.** Stripe rejects a $0 payment-mode element. A
100%-off subscription still has to capture a card for renewal, so it goes through
the SetupIntent path.

### Why there is no "preview intent"

An earlier design created a throwaway PaymentIntent on page load purely so Elements
could mount. It was removed: it would orphan an uncaptured PaymentIntent in Stripe
**every time anyone merely opened the checkout page**. Deferred mode makes it
unnecessary.

### Why there is no "draft order"

The other rejected design was a draft order per page view, materialised after
capture. **`orders.patient_id` is `NOT NULL REFERENCES patients(id)`** — an order
cannot exist without a patient, so a draft order would mean inventing a *patient
row for every anonymous visitor*.

Materialising the order after capture would also have required a **second
implementation** of patient creation, order creation, coupon resolution and the
$0/SetupIntent path — duplicating the riskiest code in the system, in the area that
has already produced two payment bugs. **Order creation stays in exactly one
place**: the existing `POST /orders/:productId/payment-intent` route, called at
submit.

## The eligibility guards, and why they run before the confirm

Two guest-safe guards (`checkCheckoutEligibility()`) run on the buyer's email:

- **medication overlap** — an active subscription whose product shares a medication
  with the one being bought;
- **same-product duplicate** — an in-progress order or plan for this same product.

These run **before the card is confirmed**, so a blocked buyer is stopped _while no
money has moved_. If they ran after capture instead, an ineligible customer would be
**charged and then refunded** — support tickets, chargeback risk, and a payment path
this codebase has already been burned by.

In the current flow the guards run **inside `POST /checkout/authorize`**, before it
creates the Stripe Customer/PaymentIntent — so a blocked buyer never even gets a
client secret. `POST /checkout/preflight` still exists as a standalone read that
runs the same guards and creates nothing; it is the guard entry point for the legacy
payment-intent path. A guard call that cannot reach the server **blocks the
payment**. Failing open would defeat the guards.

Both routes also return `account_exists` / `should_sign_in`, so a buyer who already
has this plan **and** a verified account is offered a sign-in prompt ("we found an
account for you — you have not been charged") rather than a dead-end 409.
`account_exists` means specifically a **returning** customer: a patient linked to an
auth user whose email is already verified (`email_verified_at`), so the UI shows a
LOGIN step instead of the create-password + email-verify step. A buyer who is
**already signed in** never gets that prompt: `authorize` sets
`should_sign_in: false` + `already_signed_in: true` on blocked responses, and the
UI shows the reason inline — a "sign in" CTA for someone with a live session is
nonsense.

## Step 2 — account & contact (first-time buyers)

Step 2 is a **guided two-sub-step wizard**, not a single stacked screen:

1. **Choose how you'll sign in** — Google, passkey, or password.
2. **Verify your email** — 6-digit OTP; auto-continues into the questionnaire.

Password/passkey don't prove email ownership, so they advance to sub-step 2.
**Google skips sub-step 2 entirely**: `POST /auth/oauth/resolve` (patient-api)
treats a Google session whose email maps to the tenant patient as proof of
ownership — it stamps `patients.email_verified_at` and releases gate-held orders,
exactly like an OTP verify. Because Google is a full-page redirect, the checkout
stashes resume state (`sessionStorage`, `checkoutResume` key) and `/auth/callback`
returns the buyer to `/products/:id/checkout?resumeOrder=<orderId>`, which restores
straight into the questionnaire. Google sign-in at checkout uses the same
`signInWithOAuth` + resolve flow as the login page (with `login_hint` preselecting
the purchase email) — the earlier `linkIdentity` approach was removed: it required
Supabase's "Allow manual linking" project toggle and never worked.

The contact-validation gate (order-lifecycle) holds orders pre-provider until
`patients.email_verified_at` is set; it passes automatically for anyone already
verified, so verification can arrive by OTP, Google, or a previous purchase.

## Returning buyers (signed in)

A signed-in buyer purchasing again gets two things:

**Saved payment methods.** The UI calls `POST /checkout/customer-session`
(auth-required, plan-api) at page load, which mints a **Stripe CustomerSession**
for the patient's cached customer (`patients.metadata.stripe_customer_id`, via
`ensureStripeCustomerForPatient` — the same cache the billing portal reads). The
PaymentElement then redisplays saved cards and offers saving new ones.

Load-bearing details:

- **The PaymentIntent's `customer` must equal the CustomerSession's customer** or
  a saved-card confirm is a Stripe 400. That is why `/checkout/authorize` accepts
  optional auth: for a signed-in buyer it resolves the customer through the same
  `ensureStripeCustomerForPatient` helper (falling back to the guest email-based
  create on failure). Guests are byte-identical to before.
- The session's `payment_method_allow_redisplay_filters` **must include
  `unspecified`** — cards saved by the subscription `setup_future_usage` path
  carry no explicit `allow_redisplay` flag and would otherwise never show.
- The client secret lives ~30 minutes and is only consumed at Elements **mount**
  (it also joins the Elements `key`); the payment itself confirms against the
  PaymentIntent secret. An idle buyer past expiry retries with a new card.
- Session-fetch failure degrades silently to the plain card form.

**How a card gets saved** (for the next purchase to redisplay):

- _Subscription product_ → saved automatically (`setup_future_usage=off_session`
  on the PI; renewals need it). Applies to guests too — finalize writes the
  customer id onto the new patient, so the card shows on their next signed-in
  checkout.
- _One-time product, signed in_ → the PaymentElement shows a **"save for future
  payments" checkbox** (CustomerSession `payment_method_save=enabled`).
- _One-time product, guest_ → not saved (no session, no save UI).

**Skip step 2.** After payment, a signed-in buyer with `email_verified_at` set
whose purchase email equals their account email goes **straight to the
questionnaire** — the account exists and the contact gate passes server-side
(`POST /orders/:id/resume` is fired as belt-and-braces). To keep that invariant
honest, the **email field is locked to the account email** for signed-in buyers
(it also anchors the reused Stripe customer), and name/phone prefill from the
profile.

## Chrome on checkout

Step 1 is embedded on an external marketing site. For a buyer who is **not signed
in** it shows the tenant logo and nothing else: no nav, no Support, no Sign In, no
"back to product" — there is no way out of an embedded checkout, and a visitor who
came to buy should not be nudged to log in mid-purchase. The logo is deliberately
not a link.

The chrome returns at **step 2**, once they have paid and are securing their
account. **Signed-in buyers are unaffected** and keep the normal header throughout.

Driven by `src/stores/checkoutChrome.ts` in the patient app (`hidden` = guest on
step 1; `paidGuest` = guest past step 1).
