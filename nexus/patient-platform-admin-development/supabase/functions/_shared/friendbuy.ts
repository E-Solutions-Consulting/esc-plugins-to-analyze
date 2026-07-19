const FRIENDBUY_MERCHANT_API_BASE_URL = "https://mapi.fbot.me/v1";

// deno-lint-ignore no-explicit-any
type SupabaseAdminClient = any;

export interface FriendbuyAttribution {
  referralCode?: string | null;
  attributionId?: string | null;
  campaignId?: string | null;
}

export interface FriendbuyCustomer {
  id: string;
  email: string;
  firstName?: string | null;
  lastName?: string | null;
}

export interface FriendbuyPurchaseProduct {
  sku?: string | null;
  name?: string | null;
  quantity: number;
  price: number;
}

export interface FriendbuyPurchase {
  orderId: string;
  amount: number;
  currency: string;
  customer: FriendbuyCustomer;
  couponCode?: string | null;
  products?: FriendbuyPurchaseProduct[];
  attribution?: FriendbuyAttribution | null;
}

export interface FriendbuyIntegrationConfig {
  merchantId: string;
  campaignId: string;
  mountElementId?: string | null;
  // Verifies inbound Friendbuy webhook signatures only.
  secretKey: string;
  // Merchant API credential pair — used for outbound Friendbuy API calls
  // (sending signup/purchase conversion events). Distinct from secretKey above.
  apiKey: string;
  apiSecretKey: string;
}

export interface PendingFriendbuyRewardTotal {
  pendingTotal: number;
  formattedPendingTotal: string | null;
  currency: string;
}

export interface FriendbuySyncPayload {
  eventType: string;
  eventId?: string | null;
  payload: unknown;
}

export interface FriendbuyReferralSnapshot {
  referrerEmail?: string | null;
  friendEmail?: string | null;
  referralCode?: string | null;
  // The friend's redeemable discount code (Friendbuy `couponCode` / GetCoupons
  // `code`), distinct from the advocate's shareable `referralCode` above.
  friendCouponCode?: string | null;
  referralUrl?: string | null;
  friendbuyCustomerId?: string | null;
  friendbuyPurlId?: string | null;
  friendbuyShareId?: string | null;
  friendbuyClickId?: string | null;
  friendbuyConversionId?: string | null;
  friendbuyRewardId?: string | null;
  friendbuyCampaignId?: string | null;
  friendbuyWidgetId?: string | null;
  stripeCouponId?: string | null;
  stripePromotionCodeId?: string | null;
  status?: string | null;
  rewardStatus?: string | null;
  couponStatus?: string | null;
  validityStatus?: string | null;
  redemptionCount?: number | null;
  maxRedemptions?: number | null;
  // Transient (not persisted): tells the upsert to fall back to matching an
  // existing referral row by (campaign + referrer_email + friend_email) when no
  // code/id key matches. Set only by the advocateReward/friendIncentive webhook
  // mappers, which carry no referralCode of their own — so they can still merge
  // into the PURL-referralCode-keyed row for the same referral instead of
  // orphaning. Deliberately left unset for emailCapture/share/click/conversion,
  // whose separate-row behavior is intentional.
  identityMatch?: boolean;
  occurredAt?: string | null;
  issuedAt?: string | null;
  deliveredAt?: string | null;
  clickedAt?: string | null;
  purchasedAt?: string | null;
  redeemedAt?: string | null;
  rewardPendingAt?: string | null;
  rewardCreditedAt?: string | null;
  rewardRejectedAt?: string | null;
  expiresAt?: string | null;
}

function readNonEmptyString(value: unknown): string | null {
  if (typeof value !== "string") return null;
  const normalized = value.trim();
  return normalized.length > 0 ? normalized : null;
}

function asRecord(value: unknown): Record<string, unknown> | null {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    return null;
  }
  return value as Record<string, unknown>;
}

function readNumber(value: unknown): number | null {
  if (typeof value === "number" && Number.isFinite(value)) {
    return value;
  }
  if (typeof value === "string" && value.trim().length > 0) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  }
  return null;
}

export function formatCurrency(amount: number, currency: string): string {
  try {
    return new Intl.NumberFormat("en-US", {
      style: "currency",
      currency,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount);
  } catch {
    return `${currency} ${amount.toFixed(2)}`;
  }
}

export function normalizeFriendbuyAttribution(
  value: unknown,
): FriendbuyAttribution | null {
  const record = asRecord(value);
  if (!record) return null;

  const referralCode = readNonEmptyString(record.referralCode);
  const attributionId = readNonEmptyString(record.attributionId);
  const campaignId = readNonEmptyString(record.campaignId);

  if (!referralCode && !attributionId && !campaignId) {
    return null;
  }

  return {
    ...(referralCode ? { referralCode } : {}),
    ...(attributionId ? { attributionId } : {}),
    ...(campaignId ? { campaignId } : {}),
  };
}

export function getFriendbuyAttributionFromMetadata(
  metadata: unknown,
): FriendbuyAttribution | null {
  const record = asRecord(metadata);
  if (!record) return null;
  return normalizeFriendbuyAttribution(record.friendbuy_attribution);
}

export async function getFriendbuyIntegrationConfig(
  supabaseAdmin: SupabaseAdminClient,
  tenantId: string,
): Promise<FriendbuyIntegrationConfig | null> {
  const { data, error } = await supabaseAdmin
    .from("tenant_integrations")
    .select("is_enabled, settings")
    .eq("tenant_id", tenantId)
    .eq("integration_key", "friendbuy")
    .eq("is_enabled", true)
    .maybeSingle();

  if (error) {
    console.warn("Friendbuy integration lookup failed", {
      tenantId,
      error: error.message,
    });
    return null;
  }

  const settings = asRecord(data?.settings);
  if (!data?.is_enabled || !settings) return null;

  const merchantId = readNonEmptyString(settings.merchant_id);
  const campaignId = readNonEmptyString(settings.campaign_id);
  const secretKey = readNonEmptyString(settings.secret_key);
  const apiKey = readNonEmptyString(settings.api_key);
  const apiSecretKey = readNonEmptyString(settings.api_secret_key);

  if (!merchantId || !campaignId || !secretKey || !apiKey || !apiSecretKey) {
    return null;
  }

  return {
    merchantId,
    campaignId,
    mountElementId: readNonEmptyString(settings.mount_element_id) ||
      readNonEmptyString(settings.widget_id),
    secretKey,
    apiKey,
    apiSecretKey,
  };
}

// Friendbuy signs webhook calls with Base64(HMAC-SHA256(secret_key, raw_body))
// in the X-Friendbuy-Hmac-SHA256 header. This secret_key is Friendbuy's
// dedicated webhook-signing secret (found in the retailer app's Developer
// Center), distinct from the Merchant API api_key/api_secret_key pair used
// for outbound API calls below.
export async function verifyFriendbuyWebhookSignature(
  secretKey: string,
  rawBody: string,
  signatureHeader: string | null,
): Promise<boolean> {
  if (!signatureHeader) return false;

  const key = await crypto.subtle.importKey(
    "raw",
    new TextEncoder().encode(secretKey),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"],
  );
  const signature = await crypto.subtle.sign(
    "HMAC",
    key,
    new TextEncoder().encode(rawBody),
  );
  const expected = btoa(String.fromCharCode(...new Uint8Array(signature)));

  if (expected.length !== signatureHeader.length) return false;
  let diff = 0;
  for (let index = 0; index < expected.length; index += 1) {
    diff |= expected.charCodeAt(index) ^ signatureHeader.charCodeAt(index);
  }
  return diff === 0;
}

async function getFriendbuyBearerToken(
  config: FriendbuyIntegrationConfig,
): Promise<string | null> {
  console.log("[friendbuy] auth attempt", {
    keyPrefix: config.apiKey?.slice(0, 8),
    secretPrefix: config.apiSecretKey?.slice(0, 8),
  });
  const response = await fetch(
    `${FRIENDBUY_MERCHANT_API_BASE_URL}/authorization`,
    {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({
        key: config.apiKey,
        secret: config.apiSecretKey,
      }),
    },
  );

  if (!response.ok) {
    console.warn("Friendbuy authorization failed", {
      status: response.status,
      body: await response.text().catch(() => ""),
    });
    return null;
  }

  const payload = await response.json().catch(() => null);
  const token = readNonEmptyString(payload?.token);
  return token;
}

async function postFriendbuyMerchantApi(
  config: FriendbuyIntegrationConfig,
  path: string,
  payload: Record<string, unknown>,
): Promise<{ ok: boolean; status: number; body: unknown }> {
  const token = await getFriendbuyBearerToken(config);
  if (!token) {
    return { ok: false, status: 0, body: { error: "authorization_failed" } };
  }

  const response = await fetch(`${FRIENDBUY_MERCHANT_API_BASE_URL}${path}`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
    },
    body: JSON.stringify(payload),
  });

  const body = await response.json().catch(async () =>
    await response.text().catch(() => null)
  );
  return { ok: response.ok, status: response.status, body };
}

async function getFriendbuyMerchantApi(
  config: FriendbuyIntegrationConfig,
  path: string,
): Promise<{ ok: boolean; status: number; body: unknown }> {
  const token = await getFriendbuyBearerToken(config);
  if (!token) {
    return { ok: false, status: 0, body: { error: "authorization_failed" } };
  }

  const response = await fetch(`${FRIENDBUY_MERCHANT_API_BASE_URL}${path}`, {
    method: "GET",
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
    },
  });

  const body = await response.json().catch(async () =>
    await response.text().catch(() => null)
  );
  return { ok: response.ok, status: response.status, body };
}

function readFirstRecord(value: unknown): Record<string, unknown> {
  const record = asRecord(value);
  if (!record) return {};
  const data = record.data;
  if (Array.isArray(data)) return asRecord(data[0]) || record;
  return asRecord(data) || record;
}

// Friendbuy's webhook envelope wraps records in a top-level `data` array that
// can contain multiple items in one delivery. Falls back to the record itself
// for callers that pass a flat single-item payload (no `data` wrapper).
function readDataItems(value: unknown): Record<string, unknown>[] {
  const record = asRecord(value);
  if (!record) return [];
  const data = record.data;
  if (Array.isArray(data)) {
    return data
      .map(asRecord)
      .filter((item): item is Record<string, unknown> => item !== null);
  }
  return [record];
}

function getPathValue(value: unknown, path: string[]): unknown {
  let current = value;
  for (const key of path) {
    const record = asRecord(current);
    if (!record) return undefined;
    current = record[key];
  }
  return current;
}

function readAnyString(value: unknown, paths: string[][]): string | null {
  for (const path of paths) {
    const result = readNonEmptyString(getPathValue(value, path));
    if (result) return result;
  }
  return null;
}

function readAnyNumber(value: unknown, paths: string[][]): number | null {
  for (const path of paths) {
    const result = readNumber(getPathValue(value, path));
    if (result !== null) return result;
  }
  return null;
}

function readAnyDateString(value: unknown, paths: string[][]): string | null {
  for (const path of paths) {
    const candidate = getPathValue(value, path);
    const text = readNonEmptyString(candidate);
    if (text) return text;
    const number = readNumber(candidate);
    if (number !== null) {
      const millis = number > 10_000_000_000 ? number : number * 1000;
      return new Date(millis).toISOString();
    }
  }
  return null;
}

function normalizeProviderStatus(value: string | null): string | null {
  if (!value) return null;
  return value.trim().toLowerCase().replace(/[\s-]+/g, "_");
}

function deriveValidityStatus(params: {
  providerStatus?: string | null;
  expiresAt?: string | null;
  redemptionCount?: number | null;
  maxRedemptions?: number | null;
}): string | null {
  const status = normalizeProviderStatus(params.providerStatus || null);
  if (
    status?.includes("invalid") ||
    status?.includes("blocked") ||
    status?.includes("rejected") ||
    status?.includes("cancelled")
  ) {
    return "invalid";
  }
  if (
    status?.includes("expired") ||
    (params.expiresAt && Date.parse(params.expiresAt) < Date.now())
  ) {
    return "expired";
  }
  if (
    params.maxRedemptions !== null &&
    params.maxRedemptions !== undefined &&
    params.redemptionCount !== null &&
    params.redemptionCount !== undefined &&
    params.redemptionCount >= params.maxRedemptions
  ) {
    return "redeemed";
  }
  if (
    status?.includes("redeemed") ||
    status?.includes("used") ||
    status?.includes("converted")
  ) {
    return "redeemed";
  }
  if (
    status?.includes("active") ||
    status?.includes("issued") ||
    status?.includes("sent") ||
    status?.includes("delivered")
  ) {
    return "active";
  }
  return status ? "unknown" : null;
}

function deriveCouponStatus(value: string | null): string | null {
  const status = normalizeProviderStatus(value);
  if (!status) return null;
  if (status.includes("expired")) return "expired";
  if (
    status.includes("invalid") ||
    status.includes("blocked") ||
    status.includes("rejected")
  ) {
    return "invalid";
  }
  if (status.includes("redeemed") || status.includes("used")) {
    return "redeemed";
  }
  if (status.includes("sent") || status.includes("delivered")) {
    return "delivered";
  }
  if (status.includes("active") || status.includes("issued")) {
    return "active";
  }
  return status;
}

// Fallback for any eventType not explicitly recognized below, by
// substring-matching the label itself (e.g. legacy/synthetic labels like
// "coupon" or "reward_credited"). Friendbuy's real webhook event types
// (advocateReward, friendIncentive, emailCapture, emailOptOut, ...) are
// handled explicitly by normalizeFriendbuyEventSnapshots below and never
// reach this function.
function normalizeFriendbuyEventSnapshotLegacy(
  eventType: string,
  payload: unknown,
  config?: FriendbuyIntegrationConfig | null,
): FriendbuyReferralSnapshot {
  const root = readFirstRecord(payload);
  const lowerEvent = eventType.toLowerCase();
  const providerStatus = readAnyString(root, [
    ["status"],
    ["state"],
    ["coupon_status"],
    ["couponStatus"],
    ["reward_status"],
    ["rewardStatus"],
    ["delivery_status"],
    ["deliveryStatus"],
  ]);
  const redemptionCount = readAnyNumber(root, [
    ["redemption_count"],
    ["redemptionCount"],
    ["redemptions"],
    ["times_redeemed"],
    ["timesRedeemed"],
    ["coupon", "redemption_count"],
    ["coupon", "redemptionCount"],
    ["coupon", "times_redeemed"],
    ["coupon", "timesRedeemed"],
  ]);
  const maxRedemptions = readAnyNumber(root, [
    ["max_redemptions"],
    ["maxRedemptions"],
    ["max_uses"],
    ["maxUses"],
    ["coupon", "max_redemptions"],
    ["coupon", "maxRedemptions"],
    ["coupon", "max_uses"],
    ["coupon", "maxUses"],
  ]);
  const expiresAt = readAnyDateString(root, [
    ["expires_at"],
    ["expiresAt"],
    ["expiration_date"],
    ["expirationDate"],
    ["valid_until"],
    ["validUntil"],
    ["coupon", "expires_at"],
    ["coupon", "expiresAt"],
    ["coupon", "expiration_date"],
    ["coupon", "expirationDate"],
    ["coupon", "valid_until"],
    ["coupon", "validUntil"],
  ]);
  const status = lowerEvent.includes("click")
    ? "clicked"
    : lowerEvent.includes("conversion") || lowerEvent.includes("purchase")
    ? "conversion_recorded"
    : lowerEvent.includes("reward")
    ? providerStatus?.toLowerCase().includes("credited") ||
        providerStatus?.toLowerCase().includes("approved")
      ? "rewarded"
      : "reward_pending"
    : lowerEvent.includes("share") || lowerEvent.includes("email")
    ? "delivered"
    : lowerEvent.includes("coupon") || lowerEvent.includes("referral")
    ? "issued"
    : null;
  const rewardStatus = lowerEvent.includes("reward")
    ? lowerEvent.includes("credited") ||
        lowerEvent.includes("approved") ||
        providerStatus?.toLowerCase().includes("credited") ||
        providerStatus?.toLowerCase().includes("approved")
      ? "credited"
      : lowerEvent.includes("reject") ||
          providerStatus?.toLowerCase().includes("reject") ||
          providerStatus?.toLowerCase().includes("invalid")
      ? "rejected"
      : "pending_eligibility"
    : null;
  const occurredAt = readAnyDateString(root, [
    ["created_at"],
    ["createdAt"],
    ["timestamp"],
    ["occurred_at"],
    ["occurredAt"],
    ["sent_at"],
    ["sentAt"],
    ["delivered_at"],
    ["deliveredAt"],
  ]);
  const purchasedAt = readAnyDateString(root, [
    ["purchased_at"],
    ["purchasedAt"],
    ["converted_at"],
    ["convertedAt"],
    ["conversion_at"],
    ["conversionAt"],
    ["redeemed_at"],
    ["redeemedAt"],
  ]);
  const rewardCreditedAt = readAnyDateString(root, [
    ["credited_at"],
    ["creditedAt"],
    ["approved_at"],
    ["approvedAt"],
    ["rewarded_at"],
    ["rewardedAt"],
  ]);
  const rewardRejectedAt = readAnyDateString(root, [
    ["rejected_at"],
    ["rejectedAt"],
    ["declined_at"],
    ["declinedAt"],
  ]);
  const deliveredAt = readAnyDateString(root, [
    ["delivered_at"],
    ["deliveredAt"],
    ["sent_at"],
    ["sentAt"],
    ["emailed_at"],
    ["emailedAt"],
  ]);
  const clickedAt = readAnyDateString(root, [
    ["clicked_at"],
    ["clickedAt"],
  ]);

  return {
    referrerEmail: readAnyString(root, [
      ["referrer", "email"],
      ["advocate", "email"],
      ["from", "email"],
      ["customer", "email"],
      ["referrer_email"],
      ["advocate_email"],
      ["from_email"],
    ]),
    friendEmail: readAnyString(root, [
      ["friend", "email"],
      ["recipient", "email"],
      ["to", "email"],
      ["referred_customer", "email"],
      ["friend_email"],
      ["recipient_email"],
      ["to_email"],
      ["email"],
    ]),
    referralCode: readAnyString(root, [
      ["referral_code"],
      ["referralCode"],
      ["coupon_code"],
      ["couponCode"],
      ["code"],
      ["coupon", "code"],
    ]),
    referralUrl: readAnyString(root, [
      ["referral_url"],
      ["referralUrl"],
      ["purl"],
      ["url"],
      ["link"],
    ]),
    friendbuyCustomerId: readAnyString(root, [
      ["customer_id"],
      ["customerId"],
      ["customer", "id"],
      ["advocate", "id"],
      ["referrer", "id"],
    ]),
    friendbuyPurlId: readAnyString(root, [
      ["purl_id"],
      ["purlId"],
      ["personal_referral_link_id"],
      ["personalReferralLinkId"],
    ]),
    friendbuyShareId: readAnyString(root, [["share_id"], ["shareId"], ["id"]]),
    friendbuyClickId: readAnyString(root, [["click_id"], ["clickId"], ["id"]]),
    friendbuyConversionId: readAnyString(root, [
      ["conversion_id"],
      ["conversionId"],
      ["purchase_id"],
      ["purchaseId"],
      ["id"],
    ]),
    friendbuyRewardId: readAnyString(root, [["reward_id"], ["rewardId"], [
      "id",
    ]]),
    friendbuyCampaignId: readAnyString(root, [
      ["campaign_id"],
      ["campaignId"],
      ["campaign", "id"],
    ]) || config?.campaignId || null,
    friendbuyWidgetId: readAnyString(root, [["widget_id"], ["widgetId"]]) ||
      null,
    stripeCouponId: readAnyString(root, [
      ["stripe_coupon_id"],
      ["stripeCouponId"],
      ["coupon", "stripe_coupon_id"],
      ["coupon", "stripeCouponId"],
      ["coupon", "stripe", "coupon_id"],
      ["coupon", "stripe", "couponId"],
    ]),
    stripePromotionCodeId: readAnyString(root, [
      ["stripe_promotion_code_id"],
      ["stripePromotionCodeId"],
      ["promotion_code_id"],
      ["promotionCodeId"],
      ["coupon", "stripe_promotion_code_id"],
      ["coupon", "stripePromotionCodeId"],
      ["coupon", "promotion_code_id"],
      ["coupon", "promotionCodeId"],
      ["coupon", "stripe", "promotion_code_id"],
      ["coupon", "stripe", "promotionCodeId"],
    ]),
    status,
    rewardStatus,
    couponStatus: deriveCouponStatus(providerStatus),
    validityStatus: deriveValidityStatus({
      providerStatus,
      expiresAt,
      redemptionCount,
      maxRedemptions,
    }),
    redemptionCount,
    maxRedemptions,
    occurredAt,
    issuedAt: readAnyDateString(root, [["issued_at"], ["issuedAt"]]) ||
      (lowerEvent.includes("coupon") || lowerEvent.includes("referral")
        ? occurredAt
        : null),
    deliveredAt: deliveredAt ||
      (lowerEvent.includes("share") || lowerEvent.includes("email")
        ? occurredAt
        : null),
    clickedAt: clickedAt || (lowerEvent.includes("click") ? occurredAt : null),
    purchasedAt: purchasedAt ||
      (lowerEvent.includes("purchase") || lowerEvent.includes("conversion")
        ? occurredAt
        : null),
    redeemedAt: readAnyDateString(root, [["redeemed_at"], ["redeemedAt"]]) ||
      purchasedAt ||
      (lowerEvent.includes("purchase") || lowerEvent.includes("conversion")
        ? occurredAt
        : null),
    rewardPendingAt:
      lowerEvent.includes("reward") && rewardStatus !== "credited"
        ? occurredAt
        : null,
    rewardCreditedAt: rewardCreditedAt ||
      (rewardStatus === "credited" ? occurredAt : null),
    rewardRejectedAt: rewardRejectedAt ||
      (rewardStatus === "rejected" ? occurredAt : null),
    expiresAt,
  };
}

// A reward record created for the advocate after a friend's conversion is
// evaluated as rewardable. Each coupon code is issued to and redeemed by
// exactly one friend, so `friends[]` holds at most one entry in practice —
// iterated defensively rather than assumed. Emits a single referrer-only
// snapshot if the payload lists no friend at all.
function mapAdvocateRewardEvent(
  payload: unknown,
  config?: FriendbuyIntegrationConfig | null,
): FriendbuyReferralSnapshot[] {
  const snapshots: FriendbuyReferralSnapshot[] = [];

  for (const item of readDataItems(payload)) {
    const referrerEmail = readNonEmptyString(item.emailAddress);
    const friendbuyCustomerId = readNonEmptyString(item.customerId);
    const friendbuyRewardId = readNonEmptyString(item.rewardId);
    const occurredAt = readAnyDateString(item, [["createdOn"]]);
    const friendbuyCampaignId = readNonEmptyString(item.campaignId) ||
      config?.campaignId || null;

    // NOTE: item.couponCode here is the ADVOCATE's reward coupon, which in this
    // integration is a Stripe customer-balance credit, not a redeemable code —
    // and it is NOT the advocate's PURL referralCode (this event carries none).
    // So we deliberately do not store it as referral_code or friend_coupon_code;
    // this event only advances reward status for the friend it names. We rely on
    // identityMatch to attach it to the existing referralCode-keyed row.
    const base: FriendbuyReferralSnapshot = {
      referrerEmail,
      friendbuyCustomerId,
      friendbuyRewardId,
      friendbuyCampaignId,
      status: "reward_pending",
      rewardStatus: "pending_eligibility",
      identityMatch: true,
      occurredAt,
      issuedAt: occurredAt,
      rewardPendingAt: occurredAt,
    };

    const friendEmails = (Array.isArray(item.friends) ? item.friends : [])
      .map((friend) => readNonEmptyString(asRecord(friend)?.friendEmailAddress))
      .filter((email): email is string => email !== null);

    if (friendEmails.length === 0) {
      snapshots.push(base);
    } else {
      for (const friendEmail of friendEmails) {
        snapshots.push({ ...base, friendEmail });
      }
    }
  }

  return snapshots;
}

// The friend's own incentive being issued after they convert. Describes the
// friend's side of the referral, not the advocate's reward — rewardStatus is
// intentionally left unset so it never clobbers an existing advocateReward
// snapshot for the same referral_records row.
function mapFriendIncentiveEvent(
  payload: unknown,
  config?: FriendbuyIntegrationConfig | null,
): FriendbuyReferralSnapshot[] {
  const snapshots: FriendbuyReferralSnapshot[] = [];

  for (const item of readDataItems(payload)) {
    const friendEmail = readNonEmptyString(item.emailAddress);
    if (!friendEmail) continue;

    const occurredAt = readAnyDateString(item, [["createdOn"]]);
    // item.couponCode is the FRIEND's redeemable incentive coupon — the code
    // the friend actually gets — NOT the advocate's PURL referralCode (this
    // event carries none). Store it as friend_coupon_code, and use identityMatch
    // to merge into the existing referralCode-keyed row for this referral.
    snapshots.push({
      friendEmail,
      referrerEmail: readNonEmptyString(item.advocateEmailAddress),
      friendbuyCustomerId: readNonEmptyString(item.advocateCustomerId),
      friendCouponCode: readNonEmptyString(item.couponCode),
      friendbuyRewardId: readNonEmptyString(item.rewardId),
      friendbuyCampaignId: readNonEmptyString(item.campaignId) ||
        config?.campaignId || null,
      status: "conversion_recorded",
      identityMatch: true,
      occurredAt,
      purchasedAt: occurredAt,
    });
  }

  return snapshots;
}

// Fires when a friend enters their email into a referral/incentive widget —
// the earliest touchpoint in the friend's journey.
function mapEmailCaptureEvent(
  payload: unknown,
  config?: FriendbuyIntegrationConfig | null,
): FriendbuyReferralSnapshot[] {
  const snapshots: FriendbuyReferralSnapshot[] = [];
  // Real emailCapture data[] items carry no createdOn of their own — only
  // the webhook envelope does — so fall back to that when the item lacks it.
  const envelopeCreatedOn = readAnyDateString(asRecord(payload), [[
    "createdOn",
  ]]);

  for (const item of readDataItems(payload)) {
    const friendEmail = readNonEmptyString(item.emailAddress);
    if (!friendEmail) continue;

    const advocate = asRecord(item.advocate);
    const referral = asRecord(item.referral);
    const incentive = asRecord(item.incentive);
    const campaign = asRecord(item.campaign);
    const occurredAt = readAnyDateString(item, [["createdOn"]]) ||
      envelopeCreatedOn;

    snapshots.push({
      friendEmail,
      referrerEmail: readNonEmptyString(advocate?.email),
      // referral.code is the advocate's PURL referral code (the join key);
      // incentive.couponCode is the friend's redeemable coupon — keep them in
      // their own columns rather than conflating both into referral_code.
      referralCode: readNonEmptyString(referral?.code),
      friendCouponCode: readNonEmptyString(incentive?.couponCode),
      friendbuyCampaignId: readNonEmptyString(campaign?.id) ||
        config?.campaignId || null,
      status: "delivered",
      occurredAt,
      deliveredAt: occurredAt,
    });
  }

  return snapshots;
}

// Confirmed from real /analytics/shares and /analytics/clicks responses:
// both are flat, camelCase, and carry no friend info and no per-record id
// (no shareId/clickId field) — the only usable matching key back to
// referral_records is referralCode.
function mapShareOrClickEvent(
  eventType: "share" | "click",
  payload: unknown,
  config?: FriendbuyIntegrationConfig | null,
): FriendbuyReferralSnapshot[] {
  const snapshots: FriendbuyReferralSnapshot[] = [];

  for (const item of readDataItems(payload)) {
    const referralCode = readNonEmptyString(item.referralCode);
    if (!referralCode) continue;

    const occurredAt = readAnyDateString(item, [["createdOn"]]);
    snapshots.push({
      referrerEmail: readNonEmptyString(item.advocateEmail),
      friendbuyCustomerId: readNonEmptyString(item.advocateCustomerId),
      referralCode,
      friendbuyCampaignId: readNonEmptyString(item.campaignId) ||
        config?.campaignId || null,
      status: eventType === "click" ? "clicked" : "delivered",
      occurredAt,
      ...(eventType === "click"
        ? { clickedAt: occurredAt }
        : { deliveredAt: occurredAt }),
    });
  }

  return snapshots;
}

// Confirmed from real /analytics/purchases and /analytics/account-sign-ups
// responses — both flat, camelCase, and carry the friend's identity
// (email/name/customerId) plus the advocate's
// (advocateCustomerId/advocateEmail/advocateName). Purchases additionally
// have a genuinely unique orderId (mapped to friendbuyConversionId, and used
// for sync idempotency) and a couponCode fallback; account-sign-ups have
// neither, which readNonEmptyString/readAnyString handle gracefully as
// simply absent. Both represent the friend converting, so both map to the
// same "conversion_recorded" status.
function mapConversionEvent(
  payload: unknown,
  config?: FriendbuyIntegrationConfig | null,
): FriendbuyReferralSnapshot[] {
  const snapshots: FriendbuyReferralSnapshot[] = [];

  for (const item of readDataItems(payload)) {
    const friendEmail = readNonEmptyString(item.email);
    if (!friendEmail) continue;

    const occurredAt = readAnyDateString(item, [["createdOn"]]);
    snapshots.push({
      friendEmail,
      referrerEmail: readNonEmptyString(item.advocateEmail),
      friendbuyCustomerId: readNonEmptyString(item.advocateCustomerId),
      referralCode: readNonEmptyString(item.referralCode) ||
        readNonEmptyString(item.couponCode),
      friendbuyConversionId: readNonEmptyString(item.orderId),
      friendbuyCampaignId: readNonEmptyString(item.campaignId) ||
        config?.campaignId || null,
      status: "conversion_recorded",
      occurredAt,
      purchasedAt: occurredAt,
    });
  }

  return snapshots;
}

// Confirmed from a real /analytics/rewards/referral response: flat,
// camelCase, has a genuine unique `id`, a flat `friendEmail`, a real
// `status` string ("Rewarded" in the example), and a `recipientType`
// ("advocate" vs "friend") telling us whose reward this row describes —
// mirrors the advocateReward-vs-friendIncentive split from the webhook
// mappers. Advocate rewards update rewardStatus; friend-side rows only
// update the general lifecycle status, same reasoning as
// mapFriendIncentiveEvent (never clobber the advocate's rewardStatus).
function mapRewardEvent(
  payload: unknown,
  config?: FriendbuyIntegrationConfig | null,
): FriendbuyReferralSnapshot[] {
  const snapshots: FriendbuyReferralSnapshot[] = [];

  for (const item of readDataItems(payload)) {
    const occurredAt = readAnyDateString(item, [["createdOn"]]);
    const base: FriendbuyReferralSnapshot = {
      referrerEmail: readNonEmptyString(item.advocateEmail),
      friendbuyCustomerId: readNonEmptyString(item.advocateCustomerId),
      friendbuyRewardId: readNonEmptyString(item.id),
      referralCode: readNonEmptyString(item.referralCode),
      friendbuyCampaignId: readNonEmptyString(item.campaignId) ||
        config?.campaignId || null,
      friendEmail: readNonEmptyString(item.friendEmail),
      occurredAt,
    };

    const isAdvocateReward =
      normalizeProviderStatus(readNonEmptyString(item.recipientType)) !==
        "friend";

    if (!isAdvocateReward) {
      // Friend-side reward row — parallels friendIncentive; don't set
      // rewardStatus so it can't clobber the advocate's own reward row.
      snapshots.push({
        ...base,
        status: "conversion_recorded",
        purchasedAt: occurredAt,
      });
      continue;
    }

    const providerStatus = normalizeProviderStatus(
      readNonEmptyString(item.status),
    );
    const rewardStatus = providerStatus?.includes("reject") ||
        providerStatus?.includes("denied") ||
        providerStatus?.includes("invalid")
      ? "rejected"
      : providerStatus?.includes("reward") ||
          providerStatus?.includes("credit") ||
          providerStatus?.includes("approv")
      ? "credited"
      : "pending_eligibility";

    snapshots.push({
      ...base,
      status: rewardStatus === "credited" ? "rewarded" : "reward_pending",
      rewardStatus,
      rewardPendingAt: rewardStatus !== "credited" ? occurredAt : null,
      rewardCreditedAt: rewardStatus === "credited" ? occurredAt : null,
      rewardRejectedAt: rewardStatus === "rejected" ? occurredAt : null,
    });
  }

  return snapshots;
}

// Friendbuy's webhook system has no event for coupon distribution/redemption
// either — same gap as reward status. Confirmed from a real GetCoupons
// response: records are flat (`code`, `status`, `distributedOn`,
// `redeemedOn`, `redemptionOptionName`), no expiry field, and `code` is the
// same value as `referral_code` (Friendbuy's `couponCode` elsewhere) — so a
// lookup by referralCode alone finds the right row, no advocate/email
// identity needed. Dispatched under the internal label "coupon_status" (not
// "coupon") to avoid colliding with the legacy substring-mapper's own
// "coupon" test fixture, which exercises a different, older field shape.
function mapCouponEvent(
  payload: unknown,
  config?: FriendbuyIntegrationConfig | null,
): FriendbuyReferralSnapshot[] {
  const snapshots: FriendbuyReferralSnapshot[] = [];

  for (const item of readDataItems(payload)) {
    // GetCoupons `code` is the friend's redeemable coupon code, i.e. Friendbuy's
    // `couponCode` — NOT the advocate's `referralCode`. Match the referral row
    // on friend_coupon_code so we don't clobber referral_code or orphan the row.
    const friendCouponCode = readNonEmptyString(item.code);
    if (!friendCouponCode) continue;

    const couponStatus = normalizeProviderStatus(
      readNonEmptyString(item.status),
    );
    const isRedeemed = couponStatus === "redeemed";

    snapshots.push({
      friendCouponCode,
      friendbuyCampaignId: config?.campaignId || null,
      couponStatus,
      validityStatus: isRedeemed ? "redeemed" : "active",
      redemptionCount: isRedeemed ? 1 : 0,
      deliveredAt: readAnyDateString(item, [["distributedOn"]]),
      redeemedAt: readAnyDateString(item, [["redeemedOn"]]),
    });
  }

  return snapshots;
}

// Single entry point used by ingestFriendbuySyncPayload. Dispatches on
// Friendbuy's real webhook event types; falls back to the legacy
// substring-based mapper for anything else. None of these labels collide
// with Friendbuy's camelCase webhook type names.
function normalizeFriendbuyEventSnapshots(
  eventType: string,
  payload: unknown,
  config?: FriendbuyIntegrationConfig | null,
): FriendbuyReferralSnapshot[] {
  switch (eventType) {
    case "advocateReward":
      return mapAdvocateRewardEvent(payload, config);
    case "friendIncentive":
      return mapFriendIncentiveEvent(payload, config);
    case "emailCapture":
      return mapEmailCaptureEvent(payload, config);
    case "share":
      return mapShareOrClickEvent("share", payload, config);
    case "click":
      return mapShareOrClickEvent("click", payload, config);
    case "purchase":
    case "conversion":
      return mapConversionEvent(payload, config);
    case "reward":
      return mapRewardEvent(payload, config);
    case "coupon_status":
      return mapCouponEvent(payload, config);
    // emailOptOut has no referral_records concept to update; the rest are
    // Friendbuy Loyalty-product events this (plain refer-a-friend)
    // integration never uses. Logged via recordFriendbuySyncEvent in
    // ingestFriendbuySyncPayload, but intentionally not actioned here.
    case "emailOptOut":
    case "loyaltyReward":
    case "receipt":
    case "ledgerTransaction":
    case "customerUpdate":
      return [];
    default:
      return [
        normalizeFriendbuyEventSnapshotLegacy(eventType, payload, config),
      ];
  }
}

function referralStatusTimestampPatch(
  status?: string | null,
  occurredAt?: string | null,
): Record<string, unknown> {
  const timestamp = occurredAt || new Date().toISOString();
  if (status === "delivered") return { delivered_at: timestamp };
  if (status === "clicked") return { clicked_at: timestamp };
  if (status === "purchased" || status === "conversion_recorded") {
    return { purchased_at: timestamp, redeemed_at: timestamp };
  }
  if (status === "reward_pending") return { reward_pending_at: timestamp };
  if (status === "rewarded") return { reward_credited_at: timestamp };
  return {};
}

function definedPatch(
  values: Record<string, unknown>,
): Record<string, unknown> {
  return Object.fromEntries(
    Object.entries(values).filter(([, value]) =>
      value !== undefined && value !== null
    ),
  );
}

// Postgres unique_violation. supabase-js surfaces it as { code: "23505" };
// mirrors the inline check in recordFriendbuySyncEvent below.
function isUniqueViolation(error: unknown): boolean {
  return (error as { code?: string } | null)?.code === "23505";
}

export async function upsertFriendbuyReferralSnapshot(
  supabaseAdmin: SupabaseAdminClient,
  params: {
    tenantId: string;
    snapshot: FriendbuyReferralSnapshot;
    rawPayload?: unknown;
  },
): Promise<string | null> {
  const snapshot = params.snapshot;
  const lookupCode = snapshot.referralCode || null;
  const lookupFriendCoupon = snapshot.friendCouponCode || null;
  const lookupShare = snapshot.friendbuyShareId || null;
  const lookupConversion = snapshot.friendbuyConversionId || null;
  const lookupReward = snapshot.friendbuyRewardId || null;

  let existing: { id: string } | null = null;
  for (
    const [column, value] of [
      ["referral_code", lookupCode],
      ["friend_coupon_code", lookupFriendCoupon],
      ["friendbuy_share_id", lookupShare],
      ["friendbuy_conversion_id", lookupConversion],
      ["friendbuy_reward_id", lookupReward],
    ] as const
  ) {
    if (!value) continue;
    let query = supabaseAdmin
      .from("referral_records")
      .select("id")
      .eq("tenant_id", params.tenantId)
      .eq(column, value);
    // (tenant_id, referral_code) is uniquely constrained and each coupon/reward
    // code belongs to exactly one friend, so the code alone identifies the row —
    // scoping the code lookup by friend_email can only cause a false miss (which
    // then turns into an insert that trips the unique index). Keep the
    // friend_email guard only for the non-unique id columns, where it genuinely
    // defends against a lookup column matching the wrong friend's row.
    if (
      snapshot.friendEmail && column !== "referral_code" &&
      column !== "friend_coupon_code"
    ) {
      query = query.eq("friend_email", snapshot.friendEmail);
    }
    const { data } = await query.maybeSingle();
    if (data?.id) {
      existing = data;
      break;
    }
  }

  // Code-less snapshots (an emailCapture that arrives before Friendbuy has
  // minted a code) carry none of the four id keys above, so the loop can't
  // match and every repeat/redelivery would insert a fresh row — the partial
  // unique index only covers non-null referral_code, so there's no 23505 to
  // recover from. Dedupe these by (tenant_id, friend_email, campaign) among
  // code-less rows so the friend's pre-code touchpoints collapse to one row.
  // Scoped to referral_code IS NULL so it never matches — nor regresses the
  // status of — a code-bearing row.
  if (
    !existing && !lookupCode && !lookupShare && !lookupConversion &&
    !lookupReward && snapshot.friendEmail
  ) {
    let query = supabaseAdmin
      .from("referral_records")
      .select("id")
      .eq("tenant_id", params.tenantId)
      .eq("friend_email", snapshot.friendEmail)
      .is("referral_code", null);
    if (snapshot.friendbuyCampaignId) {
      query = query.eq("friendbuy_campaign_id", snapshot.friendbuyCampaignId);
    }
    // limit(1) keeps this safe if pre-cleanup duplicates still exist: pick the
    // oldest and let the normal UPDATE path merge into it.
    const { data } = await query
      .order("created_at", { ascending: true })
      .limit(1)
      .maybeSingle();
    if (data?.id) {
      existing = data;
    }
  }

  // Email-identity fallback for advocateReward/friendIncentive webhooks, which
  // carry no referralCode of their own. When none of the code/id keys above
  // matched, attach to the existing referral row for the same
  // (campaign + referrer_email + friend_email) — the referralCode-keyed row
  // created by share/click/conversion/reward-API — instead of orphaning a
  // second row. Gated on identityMatch so it never changes the deliberate
  // separate-row behavior of emailCapture/share/click/conversion. Unlike the
  // code-less branch above, this can merge into a row that HAS a referral_code
  // (that is the whole point — reward/incentive events have no code to match).
  if (
    !existing && snapshot.identityMatch && snapshot.referrerEmail &&
    snapshot.friendEmail
  ) {
    let query = supabaseAdmin
      .from("referral_records")
      .select("id")
      .eq("tenant_id", params.tenantId)
      .eq("referrer_email", snapshot.referrerEmail)
      .eq("friend_email", snapshot.friendEmail);
    if (snapshot.friendbuyCampaignId) {
      query = query.eq("friendbuy_campaign_id", snapshot.friendbuyCampaignId);
    }
    const { data } = await query
      .order("created_at", { ascending: true })
      .limit(1)
      .maybeSingle();
    if (data?.id) {
      existing = data;
    }
  }

  const payload = {
    tenant_id: params.tenantId,
    referrer_email: snapshot.referrerEmail || undefined,
    friend_email: snapshot.friendEmail || undefined,
    referral_code: snapshot.referralCode || undefined,
    friend_coupon_code: snapshot.friendCouponCode || undefined,
    referral_url: snapshot.referralUrl || undefined,
    friendbuy_customer_id: snapshot.friendbuyCustomerId || undefined,
    friendbuy_purl_id: snapshot.friendbuyPurlId || undefined,
    friendbuy_share_id: snapshot.friendbuyShareId || undefined,
    friendbuy_click_id: snapshot.friendbuyClickId || undefined,
    friendbuy_conversion_id: snapshot.friendbuyConversionId || undefined,
    friendbuy_reward_id: snapshot.friendbuyRewardId || undefined,
    friendbuy_campaign_id: snapshot.friendbuyCampaignId || undefined,
    friendbuy_widget_id: snapshot.friendbuyWidgetId || undefined,
    stripe_coupon_id: snapshot.stripeCouponId || undefined,
    stripe_promotion_code_id: snapshot.stripePromotionCodeId || undefined,
    status: snapshot.status || undefined,
    reward_status: snapshot.rewardStatus || undefined,
    coupon_status: snapshot.couponStatus || undefined,
    validity_status: snapshot.validityStatus || undefined,
    redemption_count: snapshot.redemptionCount ?? undefined,
    max_redemptions: snapshot.maxRedemptions ?? undefined,
    ...referralStatusTimestampPatch(snapshot.status, snapshot.occurredAt),
    ...definedPatch({
      issued_at: snapshot.issuedAt,
      delivered_at: snapshot.deliveredAt,
      clicked_at: snapshot.clickedAt,
      purchased_at: snapshot.purchasedAt,
      redeemed_at: snapshot.redeemedAt,
      reward_pending_at: snapshot.rewardPendingAt,
      reward_credited_at: snapshot.rewardCreditedAt,
      reward_rejected_at: snapshot.rewardRejectedAt,
      expires_at: snapshot.expiresAt,
    }),
    raw_friendbuy: params.rawPayload || {},
  };

  if (existing?.id) {
    const { data, error } = await supabaseAdmin
      .from("referral_records")
      .update(payload)
      .eq("id", existing.id)
      .select("id")
      .maybeSingle();
    if (error) {
      console.warn("Friendbuy referral snapshot update failed", {
        tenantId: params.tenantId,
        error: error.message,
      });
      return existing.id;
    }
    return data?.id || existing.id;
  }

  const { data, error } = await supabaseAdmin
    .from("referral_records")
    .insert({
      ...payload,
      status: snapshot.status || "issued",
      reward_status: snapshot.rewardStatus || "not_earned",
      issued_at: new Date().toISOString(),
    })
    .select("id")
    .maybeSingle();

  if (error) {
    // A concurrent writer (the reconcile's parallel syncs, a webhook, or the
    // Nexus purchase tracker) can create this row in the window between our
    // existence check above and this insert, so the (tenant_id, referral_code)
    // unique index rejects us. That's expected under concurrency — recover by
    // re-reading the row that won and updating it, instead of losing the
    // snapshot. Only referral_code has a unique index, so this can only apply
    // when we have a code.
    if (isUniqueViolation(error) && lookupCode) {
      const { data: raced } = await supabaseAdmin
        .from("referral_records")
        .select("id")
        .eq("tenant_id", params.tenantId)
        .eq("referral_code", lookupCode)
        .maybeSingle();
      if (raced?.id) {
        const { data: updated, error: updateError } = await supabaseAdmin
          .from("referral_records")
          .update(payload)
          .eq("id", raced.id)
          .select("id")
          .maybeSingle();
        if (updateError) {
          console.warn("Friendbuy referral snapshot update-after-conflict failed", {
            tenantId: params.tenantId,
            error: updateError.message,
          });
          return raced.id;
        }
        return updated?.id || raced.id;
      }
    }
    console.warn("Friendbuy referral snapshot insert failed", {
      tenantId: params.tenantId,
      error: error.message,
    });
    return null;
  }

  return data?.id || null;
}

export async function recordFriendbuySyncEvent(
  supabaseAdmin: SupabaseAdminClient,
  params: {
    tenantId: string;
    source: "friendbuy_api" | "friendbuy_webhook" | "nexus";
    eventType: string;
    eventId: string;
    referralRecordId?: string | null;
    payload: unknown;
    status?: "processed" | "ignored" | "failed";
    errorMessage?: string | null;
  },
): Promise<boolean> {
  const { error } = await supabaseAdmin
    .from("referral_sync_events")
    .insert({
      tenant_id: params.tenantId,
      source: params.source,
      source_event_type: params.eventType,
      source_event_id: params.eventId,
      referral_record_id: params.referralRecordId || null,
      status: params.status || "processed",
      payload: params.payload || {},
      error_message: params.errorMessage || null,
    });

  if (!error) return true;
  if (error.code === "23505") return false;

  console.warn("Friendbuy sync event insert failed", {
    tenantId: params.tenantId,
    eventType: params.eventType,
    eventId: params.eventId,
    error: error.message,
  });
  return false;
}

export async function ingestFriendbuySyncPayload(
  supabaseAdmin: SupabaseAdminClient,
  params: {
    tenantId: string;
    source: "friendbuy_api" | "friendbuy_webhook" | "nexus";
    eventType: string;
    eventId?: string | null;
    payload: unknown;
  },
): Promise<{ processed: boolean; referralRecordId: string | null }> {
  const config = await getFriendbuyIntegrationConfig(
    supabaseAdmin,
    params.tenantId,
  );
  if (!config) {
    return { processed: false, referralRecordId: null };
  }

  const root = readFirstRecord(params.payload);
  const eventId = params.eventId ||
    readAnyString(root, [["event_id"], ["eventId"], ["id"]]) ||
    `${params.eventType}:${crypto.randomUUID()}`;

  const snapshots = normalizeFriendbuyEventSnapshots(
    params.eventType,
    params.payload,
    config,
  );

  let referralRecordId: string | null = null;
  for (const snapshot of snapshots) {
    referralRecordId = await upsertFriendbuyReferralSnapshot(
      supabaseAdmin,
      {
        tenantId: params.tenantId,
        snapshot,
        rawPayload: params.payload,
      },
    );
  }

  const processed = await recordFriendbuySyncEvent(supabaseAdmin, {
    tenantId: params.tenantId,
    source: params.source,
    eventType: params.eventType,
    eventId,
    referralRecordId,
    payload: params.payload,
  });

  return { processed, referralRecordId };
}

type FriendbuyGapSyncParams = {
  tenantId: string;
  pageToken?: string | null;
  fromDate?: string | null;
  toDate?: string | null;
  pageSize?: string | null;
};

// Shared GET helper for the two narrow reconciliation pulls below. Not a
// general-purpose multi-collection sync entry point (that was `friendbuy-sync`,
// removed) — only ever called for the two Merchant API endpoints that have no
// webhook equivalent at all.
async function getFriendbuyMerchantApiForReconciliation(
  config: FriendbuyIntegrationConfig,
  path: string,
  params: FriendbuyGapSyncParams,
): Promise<{ ok: boolean; status: number; body: unknown }> {
  const token = await getFriendbuyBearerToken(config);
  if (!token) {
    console.error("[friendbuy] bearer token failed — check api_key/api_secret_key", { path });
    return { ok: false, status: 0, body: { error: "authorization_failed" } };
  }

  const url = new URL(`${FRIENDBUY_MERCHANT_API_BASE_URL}${path}`);
  const queryParams: Record<string, string | undefined | null> = {
    pageToken: params.pageToken,
    fromDate: params.fromDate,
    toDate: params.toDate,
    pageSize: params.pageSize,
  };
  Object.entries(queryParams).forEach(([key, value]) => {
    if (value) url.searchParams.set(key, value);
  });

  console.log("[friendbuy] GET", url.toString());
  const response = await fetch(url.toString(), {
    method: "GET",
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
    },
  });
  const body = await response.json().catch(async () =>
    await response.text().catch(() => null)
  );
  if (!response.ok) {
    console.error("[friendbuy] non-200 from Friendbuy API", { path, status: response.status, body });
  }
  return { ok: response.ok, status: response.status, body };
}

// Friendbuy's webhook system has no event for a reward's final
// credited/rejected outcome — `advocateReward` only ever announces creation
// (mapped conservatively to `pending_eligibility`; confirmed empty of any
// status field in a real delivery). The Merchant API's GetReferralRewards
// endpoint (`/analytics/rewards/referral`) is the only source for the
// confirmed outcome.
async function syncFriendbuyRewardStatuses(
  supabaseAdmin: SupabaseAdminClient,
  params: FriendbuyGapSyncParams,
  config: FriendbuyIntegrationConfig,
): Promise<{ ok: boolean; status: number; synced: number }> {
  // /analytics/rewards/referral requires fromDate and toDate; default to last 90 days.
  const toDate = params.toDate || new Date().toISOString();
  const fromDate = params.fromDate ||
    new Date(Date.now() - 90 * 24 * 60 * 60 * 1000).toISOString();

  // Confirmed envelope: /analytics/* returns
  // { nextPageToken, totalResults, results: [...] }.
  let pageToken: string | null = null;
  let synced = 0;
  do {
    const result = await getFriendbuyMerchantApiForReconciliation(
      config,
      "/analytics/rewards/referral",
      { ...params, fromDate, toDate, pageToken },
    );
    if (!result.ok) return { ok: false, status: result.status, synced };

    const bodyRecord = asRecord(result.body);
    const items = Array.isArray(bodyRecord?.results) ? bodyRecord.results : [];

    for (const item of items) {
      const record = readFirstRecord(item);
      // Reward records carry a real, natural `id` (confirmed from a real
      // response), unlike shares/clicks which have none.
      const eventId = readAnyString(record, [["id"], ["event_id"], ["eventId"]]);
      const outcome = await ingestFriendbuySyncPayload(supabaseAdmin, {
        tenantId: params.tenantId,
        source: "friendbuy_api",
        eventType: "reward",
        eventId,
        payload: item,
      });
      if (outcome.processed) synced += 1;
    }

    pageToken = readNonEmptyString(bodyRecord?.nextPageToken);
  } while (pageToken);

  return { ok: true, status: 200, synced };
}

// Same gap, different endpoint: no webhook reports coupon distribution or
// redemption either. GetCoupons (`/reward/coupons`) is the only source. Its
// `code` field is the friend's redeemable coupon code (Friendbuy's couponCode),
// so we match it back to referral_records on friend_coupon_code (populated by
// the friendIncentive/emailCapture webhooks) — NOT on referral_code.
//
// Unlike the /analytics/* endpoints, /reward/coupons has no bulk/date-filtered
// mode — it requires either `email` or `customerId` and returns all coupons for
// that customer. The coupon belongs to the FRIEND (the advocate's reward is a
// Stripe credit, not a coupon), so we fan out one request per unique FRIEND
// email in this tenant.
async function syncFriendbuyCouponStatuses(
  supabaseAdmin: SupabaseAdminClient,
  params: FriendbuyGapSyncParams,
  config: FriendbuyIntegrationConfig,
): Promise<{ ok: boolean; status: number; synced: number }> {
  const { data: referralRows, error: dbError } = await supabaseAdmin
    .from("referral_records")
    .select("friend_email")
    .eq("tenant_id", params.tenantId)
    .not("friend_email", "is", null);

  if (dbError) {
    console.warn("Friendbuy coupon sync: referral_records lookup failed", {
      tenantId: params.tenantId,
      error: dbError.message,
    });
    return { ok: false, status: 0, synced: 0 };
  }

  const emails = [
    ...new Set(
      ((referralRows ?? []) as Record<string, unknown>[])
        .map((r) => normalizeEmail(readNonEmptyString(r.friend_email)))
        .filter((e): e is string => e !== null),
    ),
  ];

  if (emails.length === 0) {
    return { ok: true, status: 200, synced: 0 };
  }

  const token = await getFriendbuyBearerToken(config);
  if (!token) {
    return { ok: false, status: 0, synced: 0 };
  }

  let synced = 0;
  for (const email of emails) {
    const url = new URL(`${FRIENDBUY_MERCHANT_API_BASE_URL}/reward/coupons`);
    url.searchParams.set("email", email);

    const response = await fetch(url.toString(), {
      method: "GET",
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
    });

    if (!response.ok) {
      console.warn("Friendbuy /reward/coupons failed", {
        tenantId: params.tenantId,
        email,
        status: response.status,
      });
      continue;
    }

    const body = await response.json().catch(() => null);
    const bodyRecord = asRecord(body);
    // Confirmed envelope: /reward/coupons returns { records: [...] } — a
    // different key than the /analytics/* endpoints' `results`.
    const items = Array.isArray(bodyRecord?.records) ? bodyRecord.records : [];

    for (const item of items) {
      const record = readFirstRecord(item);
      // Coupon records have no separate id field — `code` is the natural,
      // stable identity (and doubles as the referralCode lookup key).
      const eventId = readAnyString(record, [["code"]]);
      const outcome = await ingestFriendbuySyncPayload(supabaseAdmin, {
        tenantId: params.tenantId,
        source: "friendbuy_api",
        eventType: "coupon_status",
        eventId,
        payload: item,
      });
      if (outcome.processed) synced += 1;
    }
  }

  return { ok: true, status: 200, synced };
}

// Pulls `/analytics/distributed-advocate-rewards` — the only source for the
// actual per-referral reward amount (rewardAmount) and the advocate's reward
// couponCode. Unlike `/analytics/rewards/referral`, this endpoint only returns
// successfully distributed rewards (no rejections), so rejection detection
// still relies on syncFriendbuyRewardStatuses. fromDate/toDate are required by
// the endpoint; defaults to the last 90 days when the caller doesn't provide them.
async function syncFriendbuyDistributedRewards(
  supabaseAdmin: SupabaseAdminClient,
  params: FriendbuyGapSyncParams,
  config: FriendbuyIntegrationConfig,
): Promise<{ ok: boolean; status: number; synced: number }> {
  const toDate = params.toDate || new Date().toISOString();
  const fromDate = params.fromDate ||
    new Date(Date.now() - 90 * 24 * 60 * 60 * 1000).toISOString();

  let pageToken: string | null = null;
  let synced = 0;
  do {
    const result = await getFriendbuyMerchantApiForReconciliation(
      config,
      "/analytics/distributed-advocate-rewards",
      { ...params, fromDate, toDate, pageToken },
    );
    if (!result.ok) return { ok: false, status: result.status, synced };

    const bodyRecord = asRecord(result.body);
    const items = Array.isArray(bodyRecord?.results) ? bodyRecord.results : [];

    for (const item of items) {
      const record = asRecord(item);
      if (!record) continue;

      const referralCode = readNonEmptyString(record.referralCode);
      if (!referralCode) continue;

      const couponCode = readNonEmptyString(record.couponCode);
      const rewardAmount = readNumber(record.rewardAmount);
      const occurredAt = readAnyDateString(record, [["createdOn"]]);

      // Stable idempotency key: prefer couponCode (unique per delivery),
      // fall back to referralCode:friendEmail:createdOn.
      const eventId = couponCode ||
        [
          referralCode,
          readNonEmptyString(record.friendEmail) || "",
          occurredAt || "",
        ].join(":");

      // record.couponCode is the ADVOCATE's reward coupon (a Stripe credit in
      // this integration), not the friend's coupon — so it is used only as the
      // idempotency key below, never written to friend_coupon_code.
      const snapshot: FriendbuyReferralSnapshot = {
        referrerEmail: readNonEmptyString(record.advocateEmail),
        friendbuyCustomerId: readNonEmptyString(record.advocateCustomerId),
        referralCode,
        friendEmail: readNonEmptyString(record.friendEmail),
        friendbuyCampaignId: readNonEmptyString(record.campaignId) ||
          config.campaignId || null,
        status: "rewarded",
        rewardStatus: "credited",
        occurredAt,
        rewardCreditedAt: occurredAt,
      };

      const referralRecordId = await upsertFriendbuyReferralSnapshot(
        supabaseAdmin,
        { tenantId: params.tenantId, snapshot, rawPayload: item },
      );

      // Update reward_amount_cents from Friendbuy's actual per-referral value.
      // Only when positive — don't overwrite with zero if the field is absent.
      if (referralRecordId && rewardAmount !== null && rewardAmount > 0) {
        await supabaseAdmin
          .from("referral_records")
          .update({ reward_amount_cents: Math.round(rewardAmount * 100) })
          .eq("id", referralRecordId)
          .eq("tenant_id", params.tenantId)
          .select("id")
          .maybeSingle();
      }

      const processed = await recordFriendbuySyncEvent(supabaseAdmin, {
        tenantId: params.tenantId,
        source: "friendbuy_api",
        eventType: "distributed_advocate_reward",
        eventId,
        referralRecordId,
        payload: item,
      });
      if (processed) synced += 1;
    }

    pageToken = readNonEmptyString(bodyRecord?.nextPageToken);
  } while (pageToken);

  return { ok: true, status: 200, synced };
}

// Combined entry point for the admin "Reconcile Friendbuy" action. Pulls the
// three Merchant API endpoints that have no webhook equivalent:
//   - reward credited/rejected outcome (/analytics/rewards/referral)
//   - coupon distribution/redemption (/reward/coupons)
//   - per-referral reward amount + advocate coupon code
//     (/analytics/distributed-advocate-rewards)
// Every other referral signal (share/click/conversion/email-capture/opt-out)
// already arrives via `friendbuy-webhook` in near-real-time.
export async function reconcileFriendbuyRewardsAndCoupons(
  supabaseAdmin: SupabaseAdminClient,
  params: FriendbuyGapSyncParams,
): Promise<{ ok: boolean; status: number; synced: number }> {
  const config = await getFriendbuyIntegrationConfig(
    supabaseAdmin,
    params.tenantId,
  );
  if (!config) {
    console.error("[friendbuy] reconcile: no integration config found for tenant", { tenantId: params.tenantId });
    return { ok: false, status: 0, synced: 0 };
  }

  console.log("[friendbuy] reconcile: config found, starting 3 syncs");
  const [rewards, coupons, distributed] = await Promise.all([
    syncFriendbuyRewardStatuses(supabaseAdmin, params, config),
    syncFriendbuyCouponStatuses(supabaseAdmin, params, config),
    syncFriendbuyDistributedRewards(supabaseAdmin, params, config),
  ]);

  console.log("[friendbuy] reconcile: sync results", {
    rewards: { ok: rewards.ok, status: rewards.status, synced: rewards.synced },
    coupons: { ok: coupons.ok, status: coupons.status, synced: coupons.synced },
    distributed: { ok: distributed.ok, status: distributed.status, synced: distributed.synced },
  });

  return {
    ok: rewards.ok && coupons.ok && distributed.ok,
    status: !rewards.ok
      ? rewards.status
      : !coupons.ok
      ? coupons.status
      : distributed.status,
    synced: rewards.synced + coupons.synced + distributed.synced,
  };
}

async function acquireEventSendLock(
  supabaseAdmin: SupabaseAdminClient,
  tenantId: string,
  eventType: string,
  entityId: string,
): Promise<boolean> {
  const { error: insertError } = await supabaseAdmin
    .from("friendbuy_event_logs")
    .insert({
      tenant_id: tenantId,
      event_type: eventType,
      entity_id: entityId,
      status: "pending",
      request_payload: {},
    });

  if (!insertError) {
    return true;
  }

  if (insertError.code !== "23505") {
    console.warn("Friendbuy event lock insert failed", {
      tenantId,
      eventType,
      entityId,
      error: insertError.message,
    });
    return true;
  }

  const { data, error } = await supabaseAdmin
    .from("friendbuy_event_logs")
    .select("status, error_message")
    .eq("tenant_id", tenantId)
    .eq("event_type", eventType)
    .eq("entity_id", entityId)
    .maybeSingle();

  if (error) {
    console.warn("Friendbuy event log lookup failed", {
      tenantId,
      eventType,
      entityId,
      error: error.message,
    });
    return false;
  }

  if (data?.status === "success" || data?.status === "pending") {
    return false;
  }

  const { data: retryLock, error: retryLockError } = await supabaseAdmin
    .from("friendbuy_event_logs")
    .update({
      status: "pending",
      error_message: null,
      updated_at: new Date().toISOString(),
    })
    .eq("tenant_id", tenantId)
    .eq("event_type", eventType)
    .eq("entity_id", entityId)
    .eq("status", "failed")
    .select("id")
    .maybeSingle();

  if (retryLockError || !retryLock) {
    console.warn("Friendbuy event retry lock failed", {
      tenantId,
      eventType,
      entityId,
      error: retryLockError?.message || "event_not_retryable",
    });
    return false;
  }

  return true;
}

async function writeEventLog(
  supabaseAdmin: SupabaseAdminClient,
  params: {
    tenantId: string;
    eventType: string;
    entityId: string;
    status: "success" | "failed";
    requestPayload: Record<string, unknown>;
    responsePayload?: unknown;
    errorMessage?: string | null;
  },
): Promise<void> {
  const { error } = await supabaseAdmin
    .from("friendbuy_event_logs")
    .upsert(
      {
        tenant_id: params.tenantId,
        event_type: params.eventType,
        entity_id: params.entityId,
        status: params.status,
        request_payload: params.requestPayload,
        response_payload: params.responsePayload ?? null,
        error_message: params.errorMessage ?? null,
        sent_at: params.status === "success" ? new Date().toISOString() : null,
      },
      { onConflict: "tenant_id,event_type,entity_id" },
    );

  if (error) {
    console.warn("Friendbuy event log write failed", {
      tenantId: params.tenantId,
      eventType: params.eventType,
      entityId: params.entityId,
      error: error.message,
    });
  }
}

export async function sendFriendbuySignupEvent(
  supabaseAdmin: SupabaseAdminClient,
  params: {
    tenantId: string;
    patientId: string;
    customer: FriendbuyCustomer;
    attribution?: FriendbuyAttribution | null;
  },
): Promise<void> {
  const config = await getFriendbuyIntegrationConfig(
    supabaseAdmin,
    params.tenantId,
  );
  if (!config) return;

  const eventType = "sign_up";
  const shouldSend = await acquireEventSendLock(
    supabaseAdmin,
    params.tenantId,
    eventType,
    params.patientId,
  );
  if (!shouldSend) {
    return;
  }

  const payload: Record<string, unknown> = {
    customerId: params.customer.id,
    email: params.customer.email,
    firstName: params.customer.firstName || undefined,
    lastName: params.customer.lastName || undefined,
    campaignId: config.campaignId,
    referralCode: params.attribution?.referralCode || undefined,
    attributionId: params.attribution?.attributionId || undefined,
  };

  const result = await postFriendbuyMerchantApi(
    config,
    "/event/account-sign-up",
    payload,
  );
  await writeEventLog(supabaseAdmin, {
    tenantId: params.tenantId,
    eventType,
    entityId: params.patientId,
    status: result.ok ? "success" : "failed",
    requestPayload: payload,
    responsePayload: result.body,
    errorMessage: result.ok
      ? null
      : `Friendbuy sign_up failed: ${result.status}`,
  });
}

export function dollarsFromCents(cents: number): number {
  return Number((cents / 100).toFixed(2));
}

function normalizeEmail(value: string | null | undefined): string | null {
  const normalized = value?.trim().toLowerCase();
  return normalized ? normalized : null;
}

function isExcludedReferralRewardRecord(record: Record<string, unknown>) {
  const status = readNonEmptyString(record.status)?.toLowerCase();
  const rewardStatus = readNonEmptyString(record.reward_status)?.toLowerCase();
  const validityStatus = readNonEmptyString(record.validity_status)
    ?.toLowerCase();
  const couponStatus = readNonEmptyString(record.coupon_status)?.toLowerCase();

  return (
    status === "invalid" ||
    status === "expired" ||
    status === "exception" ||
    rewardStatus === "rejected" ||
    rewardStatus === "manual_exception" ||
    validityStatus === "invalid" ||
    validityStatus === "expired" ||
    couponStatus === "invalid" ||
    couponStatus === "expired"
  );
}

export interface FriendbuyLedgerBalance {
  availableCents: number;
  currency: string;
}

// Fetches a customer's current Friendbuy credit balance via the Merchant API
// GET /ledger-balance endpoint (keyed on the customerId supplied to Friendbuy
// when tracking the customer/purchase — i.e. our patient id). Friendbuy reports
// `total` in major currency units (dollars), matching how reward amounts are
// reported elsewhere, so it is converted to cents here. Returns null when the
// integration is not configured, the customerId is missing, or the call fails —
// letting callers fall back to a zero balance.
export async function getFriendbuyLedgerBalance(
  supabaseAdmin: SupabaseAdminClient,
  params: {
    tenantId: string;
    customerId: string;
    currency?: string;
  },
): Promise<FriendbuyLedgerBalance | null> {
  const customerId = readNonEmptyString(params.customerId);
  if (!customerId) return null;

  const config = await getFriendbuyIntegrationConfig(
    supabaseAdmin,
    params.tenantId,
  );
  if (!config) return null;

  const currency = (params.currency || "USD").toUpperCase();
  const query = new URLSearchParams({ customerId, currency });
  const { ok, status, body } = await getFriendbuyMerchantApi(
    config,
    `/ledger-balance?${query.toString()}`,
  );

  if (!ok) {
    console.warn("Friendbuy ledger balance lookup failed", {
      tenantId: params.tenantId,
      status,
    });
    return null;
  }

  const record = asRecord(body) || {};
  const total = readNumber(record.total) ?? 0;
  const responseCurrency = readNonEmptyString(record.currency) || currency;
  const availableCents = total > 0 ? Math.round(total * 100) : 0;

  return {
    availableCents,
    currency: responseCurrency.toUpperCase(),
  };
}

export async function getPendingFriendbuyRewardTotal(
  supabaseAdmin: SupabaseAdminClient,
  params: {
    tenantId: string;
    patientId: string;
    patientEmail?: string | null;
    currency?: string;
  },
): Promise<PendingFriendbuyRewardTotal> {
  const config = await getFriendbuyIntegrationConfig(
    supabaseAdmin,
    params.tenantId,
  );
  const fallbackCurrency = params.currency || "USD";
  const emptyTotal: PendingFriendbuyRewardTotal = {
    pendingTotal: 0,
    formattedPendingTotal: null,
    currency: fallbackCurrency,
  };
  if (!config) return emptyTotal;

  const { data: programConfig, error: programError } = await supabaseAdmin
    .from("referral_program_configs")
    .select("currency, reward_amount_cents")
    .eq("tenant_id", params.tenantId)
    .eq("status", "active")
    .maybeSingle();

  if (programError) {
    console.warn("Friendbuy pending reward total program lookup failed", {
      tenantId: params.tenantId,
      patientId: params.patientId,
      error: programError.message,
    });
  }

  const currency = readNonEmptyString(programConfig?.currency) ||
    fallbackCurrency;
  const fallbackRewardAmountCents = readNumber(
    programConfig?.reward_amount_cents,
  ) ?? 0;

  const { data: referralRows, error: referralError } = await supabaseAdmin
    .from("referral_records")
    .select(
      "id, referrer_patient_id, referrer_email, status, reward_status, coupon_status, validity_status, reward_amount_cents, currency",
    )
    .eq("tenant_id", params.tenantId);

  if (referralError) {
    console.warn("Friendbuy pending reward total referral lookup failed", {
      tenantId: params.tenantId,
      patientId: params.patientId,
      error: referralError.message,
    });
    return emptyTotal;
  }

  const rows = Array.isArray(referralRows) ? referralRows : [];
  const patientEmail = normalizeEmail(params.patientEmail);
  const matchingRows = rows.filter((row: Record<string, unknown>) => {
    const referrerPatientId = readNonEmptyString(row.referrer_patient_id);
    const referrerEmail = normalizeEmail(
      readNonEmptyString(row.referrer_email),
    );
    return referrerPatientId === params.patientId ||
      (patientEmail !== null && referrerEmail === patientEmail);
  });

  let pendingCents = 0;

  for (const row of matchingRows as Array<Record<string, unknown>>) {
    if (isExcludedReferralRewardRecord(row)) continue;

    const rewardAmountCents = readNumber(row.reward_amount_cents) ??
      fallbackRewardAmountCents;
    if (rewardAmountCents <= 0) continue;

    const rewardStatus = readNonEmptyString(row.reward_status)?.toLowerCase();
    if (
      rewardStatus === "pending_eligibility" ||
      rewardStatus === "pending_approval"
    ) {
      pendingCents += rewardAmountCents;
    }
  }

  const pendingTotal = dollarsFromCents(pendingCents);

  return {
    pendingTotal,
    formattedPendingTotal: pendingCents > 0
      ? `+${formatCurrency(pendingTotal, currency)}`
      : null,
    currency,
  };
}

export interface PatientReferralEntry {
  friendEmail: string;
  status: "credited" | "pending";
  occurredAt: string | null;
  amountCents: number;
  formattedAmount: string;
}

export interface PatientReferralsList {
  total: number;
  referrals: PatientReferralEntry[];
}

const PATIENT_REFERRALS_LIST_LIMIT = 10;

export async function getPatientReferrals(
  supabaseAdmin: SupabaseAdminClient,
  params: {
    tenantId: string;
    patientId: string;
    patientEmail?: string | null;
    currency?: string;
  },
): Promise<PatientReferralsList> {
  const fallbackCurrency = params.currency || "USD";
  const empty: PatientReferralsList = { total: 0, referrals: [] };

  const { data: programConfig, error: programError } = await supabaseAdmin
    .from("referral_program_configs")
    .select("currency, reward_amount_cents")
    .eq("tenant_id", params.tenantId)
    .eq("status", "active")
    .maybeSingle();

  if (programError) {
    console.warn("Patient referrals list program lookup failed", {
      tenantId: params.tenantId,
      patientId: params.patientId,
      error: programError.message,
    });
  }

  const currency = readNonEmptyString(programConfig?.currency) ||
    fallbackCurrency;
  const fallbackRewardAmountCents = readNumber(
    programConfig?.reward_amount_cents,
  ) ?? 0;

  const { data: referralRows, error: referralError } = await supabaseAdmin
    .from("referral_records")
    .select(
      "referrer_patient_id, referrer_email, friend_email, status, reward_status, coupon_status, validity_status, reward_amount_cents, currency, issued_at, delivered_at, purchased_at, reward_credited_at",
    )
    .eq("tenant_id", params.tenantId);

  if (referralError) {
    console.warn("Patient referrals list lookup failed", {
      tenantId: params.tenantId,
      patientId: params.patientId,
      error: referralError.message,
    });
    return empty;
  }

  const rows = Array.isArray(referralRows) ? referralRows : [];
  const patientEmail = normalizeEmail(params.patientEmail);
  const qualifyingRows = rows.filter((row: Record<string, unknown>) => {
    const referrerPatientId = readNonEmptyString(row.referrer_patient_id);
    const referrerEmail = normalizeEmail(
      readNonEmptyString(row.referrer_email),
    );
    const isReferrer = referrerPatientId === params.patientId ||
      (patientEmail !== null && referrerEmail === patientEmail);
    if (!isReferrer) return false;
    if (!readNonEmptyString(row.friend_email)) return false;
    return !isExcludedReferralRewardRecord(row);
  });

  const entries: PatientReferralEntry[] = qualifyingRows.map(
    (row: Record<string, unknown>) => {
      const rewardStatus = readNonEmptyString(row.reward_status)
        ?.toLowerCase();
      const amountCents = readNumber(row.reward_amount_cents) ??
        fallbackRewardAmountCents;
      const occurredAt = readNonEmptyString(row.reward_credited_at) ||
        readNonEmptyString(row.purchased_at) ||
        readNonEmptyString(row.delivered_at) ||
        readNonEmptyString(row.issued_at);

      return {
        friendEmail: readNonEmptyString(row.friend_email)!,
        status: rewardStatus === "credited" ? "credited" : "pending",
        occurredAt,
        amountCents,
        formattedAmount: formatCurrency(
          dollarsFromCents(amountCents),
          currency,
        ),
      };
    },
  );

  entries.sort((a, b) => {
    const aTime = a.occurredAt ? Date.parse(a.occurredAt) : 0;
    const bTime = b.occurredAt ? Date.parse(b.occurredAt) : 0;
    return bTime - aTime;
  });

  return {
    total: entries.length,
    referrals: entries.slice(0, PATIENT_REFERRALS_LIST_LIMIT),
  };
}

export async function sendFriendbuyPurchaseEvent(
  supabaseAdmin: SupabaseAdminClient,
  params: {
    tenantId: string;
    purchase: FriendbuyPurchase;
  },
): Promise<void> {
  const config = await getFriendbuyIntegrationConfig(
    supabaseAdmin,
    params.tenantId,
  );
  if (!config) return;

  const eventType = "purchase";
  const shouldSend = await acquireEventSendLock(
    supabaseAdmin,
    params.tenantId,
    eventType,
    params.purchase.orderId,
  );
  if (!shouldSend) {
    return;
  }

  const payload: Record<string, unknown> = {
    orderId: params.purchase.orderId,
    customerId: params.purchase.customer.id,
    email: params.purchase.customer.email,
    firstName: params.purchase.customer.firstName || undefined,
    lastName: params.purchase.customer.lastName || undefined,
    amount: params.purchase.amount,
    currency: params.purchase.currency,
    campaignId: config.campaignId,
    couponCode: params.purchase.couponCode || undefined,
    referralCode: params.purchase.attribution?.referralCode || undefined,
    attributionId: params.purchase.attribution?.attributionId || undefined,
    products: params.purchase.products?.length
      ? params.purchase.products
      : undefined,
  };

  const result = await postFriendbuyMerchantApi(
    config,
    "/event/purchase",
    payload,
  );
  await writeEventLog(supabaseAdmin, {
    tenantId: params.tenantId,
    eventType,
    entityId: params.purchase.orderId,
    status: result.ok ? "success" : "failed",
    requestPayload: payload,
    responsePayload: result.body,
    errorMessage: result.ok
      ? null
      : `Friendbuy purchase failed: ${result.status}`,
  });
}

export async function trackFriendbuyPurchaseForOrder(
  supabaseAdmin: SupabaseAdminClient,
  params: {
    tenantId: string;
    orderId: string;
    requestId?: string;
  },
): Promise<void> {
  const { data: order, error } = await supabaseAdmin
    .from("orders")
    .select(
      `
      id,
      order_number,
      total_cents,
      metadata,
      coupon_code,
      patients (
        id,
        email,
        first_name,
        last_name,
        metadata
      ),
      products (
        id,
        name,
        sku,
        price_cents
      )
    `,
    )
    .eq("id", params.orderId)
    .eq("tenant_id", params.tenantId)
    .maybeSingle();

  if (error || !order) {
    console.warn("Friendbuy purchase tracking skipped; order lookup failed", {
      requestId: params.requestId,
      tenantId: params.tenantId,
      orderId: params.orderId,
      error: error?.message || "Order not found",
    });
    return;
  }

  const patient = Array.isArray(order.patients)
    ? order.patients[0]
    : order.patients;
  if (!patient?.id || !patient?.email) {
    console.warn("Friendbuy purchase tracking skipped; patient missing", {
      requestId: params.requestId,
      tenantId: params.tenantId,
      orderId: params.orderId,
    });
    return;
  }

  const product = Array.isArray(order.products)
    ? order.products[0]
    : order.products;
  const totalCents = typeof order.total_cents === "number"
    ? order.total_cents
    : 0;
  const amount = Number((totalCents / 100).toFixed(2));
  if (amount <= 0) {
    console.info("Friendbuy purchase tracking skipped for zero-value order", {
      requestId: params.requestId,
      tenantId: params.tenantId,
      orderId: params.orderId,
    });
    return;
  }

  const attribution = getFriendbuyAttributionFromMetadata(order.metadata) ||
    getFriendbuyAttributionFromMetadata(patient.metadata);

  const referralCode = readNonEmptyString(order.coupon_code) ||
    attribution?.referralCode ||
    null;
  if (referralCode) {
    // A purchase tells us the friend redeemed the referral link, but NOT that
    // Friendbuy has confirmed/settled the coupon — so we no longer assume
    // validity/redemption here. The reconcile's GetCoupons pull is the
    // authoritative source for coupon_status/validity_status. We also don't set
    // referrer_email: this row is keyed by the advocate's referralCode, so the
    // upsert merges into any existing row (preserving its referrer), and the
    // reconcile backfills the advocate email for brand-new rows.
    const referralRecordId = await upsertFriendbuyReferralSnapshot(
      supabaseAdmin,
      {
        tenantId: params.tenantId,
        snapshot: {
          friendEmail: patient.email,
          referralCode,
          friendbuyCampaignId: attribution?.campaignId || null,
          status: "purchased",
          rewardStatus: "pending_eligibility",
          occurredAt: new Date().toISOString(),
        },
        rawPayload: {
          source: "nexus_purchase",
          orderId: order.id,
          referralCode,
        },
      },
    );
    await recordFriendbuySyncEvent(supabaseAdmin, {
      tenantId: params.tenantId,
      source: "nexus",
      eventType: "nexus_purchase_link",
      eventId: order.id,
      referralRecordId,
      payload: {
        orderId: order.id,
        referralCode,
        patientId: patient.id,
      },
    });
  }

  await sendFriendbuyPurchaseEvent(supabaseAdmin, {
    tenantId: params.tenantId,
    purchase: {
      orderId: order.id,
      amount,
      currency: "USD",
      couponCode: readNonEmptyString(order.coupon_code),
      customer: {
        id: patient.id,
        email: patient.email,
        firstName: patient.first_name || null,
        lastName: patient.last_name || null,
      },
      attribution,
      products: product?.id
        ? [
          {
            sku: product.sku || product.id,
            name: product.name || "Product",
            quantity: 1,
            price: Number(
              ((product.price_cents || totalCents) / 100).toFixed(2),
            ),
          },
        ]
        : undefined,
    },
  });
}
