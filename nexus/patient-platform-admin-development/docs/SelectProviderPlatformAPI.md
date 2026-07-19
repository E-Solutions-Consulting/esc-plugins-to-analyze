# Select Provider Platform API Documentation

> **Version:** 1.0.0\
> **Last Updated:** April 2026\
> **Audience:** Frontend Developers, Backend Developers, Integrations Team

This document describes the `select-provider-platform` Edge Function. It is an
internal endpoint that selects which provider platform should fulfill a product
order, based on load balancing rule sets with state-level routing and percentage
allocations.

Note: the default automated production flow defines provider platform selection
inside `order-lifecycle` when an order reaches
`provider_order_creation_pending`. This endpoint remains available for explicit
selection calls and dry-run/preview scenarios.

---

## Table of Contents

1. [Overview](#overview)
2. [Base Configuration](#base-configuration)
3. [Endpoint](#endpoint)
4. [Selection Algorithm](#selection-algorithm)
5. [Persistence Behavior](#persistence-behavior)
6. [Data Dependencies](#data-dependencies)
7. [Response Model](#response-model)
8. [Error Handling](#error-handling)

---

## Overview

When a product has multiple provider platforms enabled, the system needs a
deterministic way to route each order to a single provider. This function
evaluates the product's load balancing rule sets and picks a provider platform
using a random bucket against the configured allocation percentages.

### Key Features

| Feature | Description |
|---------|-------------|
| **Order-based lookup** | Accepts an `orderId` to automatically resolve tenant, product, and shipping state |
| **Direct lookup** | Accepts `tenantId` + `productId` + optional `state` for preview/dry-run calls |
| **State-specific routing** | Matches the patient's state against state-specific rule sets before falling back to the default rule |
| **Percentage-based load balancing** | Uses a random bucket (1–100) against cumulative allocation percentages to select a provider |
| **Single-provider fallback** | When only one eligible provider exists and no rules are configured, it is selected automatically |
| **Persist or preview** | `persistSelection` flag controls whether the result is written to `order_provider_platform_links` and logged |

---

## Base Configuration

| Setting | Value |
|---------|-------|
| **Edge Function** | `select-provider-platform` |
| **HTTP Method** | `POST` |
| **Authentication** | Supabase JWT (Bearer token) |
| **CORS** | Configured via shared `buildCorsHeaders` |

---

## Endpoint

### `POST /select-provider-platform`

Selects a provider platform for a given order or product.

#### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `orderId` | `string` | No | Order UUID. When provided, tenant, product, and state are resolved from the order record. |
| `tenantId` | `string` | Conditional | Required when `orderId` is not provided. Can also be sent via `x-tenant-id` header. |
| `productId` | `string` | Conditional | Required when `orderId` is not provided. |
| `state` | `string \| null` | No | Two-letter US state code (e.g. `"CA"`). Overrides the order's `shipping_state` when both are provided. |
| `persistSelection` | `boolean` | No | Defaults to `true`. When `false`, performs a dry-run without writing to the database. |

#### Example — Select by order

```json
{
  "orderId": "abc-123"
}
```

#### Example — Preview by product (dry-run)

```json
{
  "tenantId": "tenant-uuid",
  "productId": "product-uuid",
  "state": "TX",
  "persistSelection": false
}
```

#### Success Response (200)

```json
{
  "data": {
    "tenant_id": "tenant-uuid",
    "product_id": "product-uuid",
    "order_id": "abc-123",
    "state_code": "TX",
    "selection": {
      "productProviderPlatformId": "pp-uuid",
      "tenantIntegrationId": "ti-uuid",
      "integrationKey": "telegra",
      "providerProductSku": "SKU-001",
      "providerProductVariationSku": "VAR-001",
      "partnerCompoundId": "COMPOUND-001",
      "requestedStateCode": "TX",
      "appliedStateCode": "TX",
      "selectionReason": "state_rule",
      "allocationPercentage": 60,
      "randomBucket": 42
    }
  }
}
```

---

## Selection Algorithm

The function follows this decision sequence:

1. **Resolve eligible assignments** — load all `product_provider_platforms`
   records for the product whose linked `tenant_integrations` are enabled.

2. **Zero eligible providers** → return `null` (no selection possible).

3. **Generate random bucket** — a random integer between 1 and 100 (inclusive).

4. **Match a rule set**:
   - If a `stateCode` is provided, look for a rule set whose
     `rule_set_states` contain that state code. If found, use the
     **state-specific rule set**.
   - Otherwise, fall back to the rule set marked `is_default = true`.

5. **No rule set found**:
   - If exactly one eligible provider exists, select it automatically
     (`single_provider_fallback` reason).
   - If multiple providers exist, throw an error — rules must be configured.

6. **Apply allocation percentages** — iterate over eligible assignments in
   deterministic order, accumulating each assignment's percentage. The first
   assignment whose cumulative percentage is ≥ the random bucket is selected.

7. **Validation** — allocation percentages for the matched rule set must total
   exactly 100%. Throws an error otherwise.

### Selection Reasons

| Reason | When applied |
|--------|-------------|
| `state_rule` | A rule set with a matching state code was found |
| `default_rule` | No state match; the default rule set was used |
| `single_provider_fallback` | No rules configured but only one eligible provider exists |

---

## Persistence Behavior

When `persistSelection` is `true` (default), the function calls
`resolveAndPersistProviderPlatformSelection` which:

1. **Upserts `order_provider_platform_links`** — creates or updates the link
   between the order and the selected provider platform's tenant integration.
   The link includes metadata with the selection reason, state codes, random
   bucket, and timestamp.

2. **Inserts into `provider_platform_selection_logs`** — an append-only audit
   log capturing every selection decision for analytics and debugging.

When `persistSelection` is `false`, only `resolveProviderPlatformSelection` is
called — no database writes occur.

---

## Data Dependencies

| Table | Purpose |
|-------|---------|
| `orders` | Resolve tenant, product, and shipping state from an order ID |
| `products` | Validate product exists for the tenant (direct lookup mode) |
| `product_provider_platforms` | Enabled provider platform assignments for the product |
| `tenant_integrations` | Filter assignments to enabled integrations for the tenant |
| `product_provider_platform_load_balancing_rule_sets` | Rule set definitions (default vs. state-specific) |
| `product_provider_platform_load_balancing_rule_set_states` | State codes linked to each rule set |
| `product_provider_platform_load_balancing_rule_set_allocations` | Percentage allocations per provider per rule set |
| `order_provider_platform_links` | Persisted selection result (written on persist) |
| `provider_platform_selection_logs` | Audit log of all selections (written on persist) |

---

## Response Model

### Selection Object

| Field | Type | Description |
|-------|------|-------------|
| `productProviderPlatformId` | `string` | The selected `product_provider_platforms.id` |
| `tenantIntegrationId` | `string` | The selected provider's `tenant_integrations.id` |
| `integrationKey` | `string` | Integration identifier (e.g. `"telegra"`) |
| `providerProductSku` | `string \| null` | Provider-specific product SKU |
| `providerProductVariationSku` | `string \| null` | Provider-specific variation SKU |
| `partnerCompoundId` | `string \| null` | Partner compound ID for the provider |
| `requestedStateCode` | `string \| null` | The state code sent in the request |
| `appliedStateCode` | `string \| null` | The state code that matched a rule set (`null` for default/fallback) |
| `selectionReason` | `string` | One of `state_rule`, `default_rule`, `single_provider_fallback` |
| `allocationPercentage` | `number` | The allocation percentage of the selected provider |
| `randomBucket` | `number` | The random value (1–100) used for selection |

---

## Error Handling

| Status | Condition |
|--------|-----------|
| `400` | Missing `tenantId`/`productId` when `orderId` is not provided |
| `401` | Invalid or missing authentication token |
| `404` | Order or product not found |
| `405` | HTTP method other than `POST` |
| `500` | Allocation percentages don't total 100%, no rules configured for multiple providers, or unexpected errors |

All error responses follow the format:

```json
{
  "error": "Human-readable error message"
}
```
