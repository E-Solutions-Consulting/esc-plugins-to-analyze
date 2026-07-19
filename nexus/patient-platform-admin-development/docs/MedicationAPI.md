# Medication API Documentation

> **Version:** 1.1.0  
> **Last Updated:** June 1, 2026  
> **Audience:** Patient UI Developers

This document describes the Medication API endpoints used to fetch medications available to an authenticated patient, check product medication eligibility, and retrieve symptom, mood change, and activity definitions configured by the tenant.

---

## Table of Contents

1. [Overview](#overview)
2. [Authentication](#authentication)
3. [Base Configuration](#base-configuration)
4. [Endpoints](#endpoints)
   - [Get Available Medications](#get-available-medications)
   - [Check Product Medication Eligibility](#check-product-medication-eligibility)
5. [Data Model](#data-model)
6. [Error Handling](#error-handling)
7. [Rate Limiting](#rate-limiting)
8. [Security Considerations](#security-considerations)

---

## Overview

The Medication API is an authenticated Edge Function that returns the set of medications available to a patient based on their purchased products.

### Key Concepts

| Concept | Description |
|---------|-------------|
| **Medication** | A record from the `medications` table referenced by `medication_id` |
| **Patient** | The authenticated user (Supabase Auth) mapped to a `patients` table record |
| **Blocking Plan** | A patient subscription that is not cancelled and not expired, determined from `cancelled_at` and `expires_at` |
| **Health Tracking** | Shot, weight, body measurement, mood, activity, energy, and symptom tracking endpoints live in the Health Tracking API |
| **Medication Capability** | A platform-level behavior flag assigned to medications, such as `weight_tracker` or `body_measurement` |

---

## Authentication

All endpoints require a valid `Authorization: Bearer <token>` header. The authenticated user is mapped to the `patients` table via `auth_user_id`.

### Base URL

```
VITE_SUPABASE_URL/functions/v1/medication-api
```

### Required Headers

| Header | Description | Required |
|--------|-------------|----------|
| `apikey` | Supabase anon key | Yes |
| `Authorization` | Bearer access token | Yes |
| `Content-Type` | `application/json` | For POST requests |

---

## Endpoints

### Get Available Medications

Returns the set of medications available to the authenticated patient based on their purchased products. Each medication includes its assigned capabilities.

```http
GET /functions/v1/medication-api/medications
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`
```json
{
  "data": [
    {
      "id": "medication-uuid",
      "title": "Semaglutide",
      "description": "Weekly injection",
      "image_url": "https://cdn.example.com/medication.png",
      "provider_sku": "SEMA-001",
      "form": "injection",
      "is_enabled": true,
      "capabilities": [
        {
          "id": "capability-uuid",
          "key": "requires_prior_auth",
          "name": "Requires Prior Auth",
          "description": "Medication requires prior authorization.",
          "is_active": true,
          "display_order": 10
        }
      ]
    }
  ]
}
```

---

### Check Product Medication Eligibility

Returns whether the authenticated patient is eligible to purchase a given product based on medication overlap with their other active plans.

The endpoint blocks eligibility when the patient already has another subscription that:

- is linked to a different product using at least one of the same medications
- has not been cancelled (`cancelled_at IS NULL`)
- has not expired (`expires_at` is in the future, or unset)

This means a patient cannot hold two subscriptions for products that use the same medication at the same time.

```http
GET /functions/v1/medication-api/products/{product_id}/eligibility
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`
```json
{
  "data": {
    "product_id": "product-uuid",
    "product_name": "Semaglutide Monthly Plan",
    "is_eligible": false,
    "message": "Patient is not eligible because there is already an active plan using the same medication.",
    "conflicting_plans": [
      {
        "id": "subscription-uuid",
        "status": "active",
        "expires_at": "2026-04-21T12:00:00Z",
        "cancelled_at": null,
        "product": {
          "id": "existing-product-uuid",
          "name": "Semaglutide Starter Plan"
        },
        "medications": [
          {
            "id": "medication-uuid",
            "title": "Semaglutide"
          }
        ]
      }
    ],
    "conflicting_medications": [
      {
        "id": "medication-uuid",
        "title": "Semaglutide"
      }
    ]
  }
}
```

**Eligible response example**
```json
{
  "data": {
    "product_id": "product-uuid",
    "product_name": "Semaglutide Monthly Plan",
    "is_eligible": true,
    "message": "Product is eligible because the patient has no active plans using the same medication.",
    "conflicting_plans": [],
    "conflicting_medications": []
  }
}
```

**Notes**

- The target product must belong to the authenticated patient's tenant and be enabled.
- Products with no linked medications are considered eligible.
- `pending_cancellation` plans still block eligibility until they expire.
- `paused` plans still block eligibility if they are not expired.

---

### Get Tenant Symptom Definitions

Returns whether the authenticated patient is eligible to purchase a given product based on medication overlap with their other active plans.

The endpoint blocks eligibility when the patient already has another subscription that:

- is linked to a different product using at least one of the same medications
- has not been cancelled (`cancelled_at IS NULL`)
- has not expired (`expires_at` is in the future, or unset)

This means a patient cannot hold two subscriptions for products that use the same medication at the same time.

```http
GET /functions/v1/medication-api/products/{product_id}/eligibility
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`
```json
{
  "data": {
    "product_id": "product-uuid",
    "product_name": "Semaglutide Monthly Plan",
    "is_eligible": false,
    "message": "Patient is not eligible because there is already an active plan using the same medication.",
    "conflicting_plans": [
      {
        "id": "subscription-uuid",
        "status": "active",
        "expires_at": "2026-04-21T12:00:00Z",
        "cancelled_at": null,
        "product": {
          "id": "existing-product-uuid",
          "name": "Semaglutide Starter Plan"
        },
        "medications": [
          {
            "id": "medication-uuid",
            "title": "Semaglutide"
          }
        ]
      }
    ],
    "conflicting_medications": [
      {
        "id": "medication-uuid",
        "title": "Semaglutide"
      }
    ]
  }
}
```

**Eligible response example**
```json
{
  "data": {
    "product_id": "product-uuid",
    "product_name": "Semaglutide Monthly Plan",
    "is_eligible": true,
    "message": "Product is eligible because the patient has no active plans using the same medication.",
    "conflicting_plans": [],
    "conflicting_medications": []
  }
}
```

**Notes**

- The target product must belong to the authenticated patient's tenant and be enabled.
- Products with no linked medications are considered eligible.
- `pending_cancellation` plans still block eligibility until they expire.
- `paused` plans still block eligibility if they are not expired.

---

## Data Model

The API surfaces data from `medications`, `product_medications`, `subscriptions`, related capability tables, `tenant_symptom_definitions`, `tenant_activity_definitions`, and `tenant_mood_change_definitions`. See `docs/HealthTrackingAPI.md` for shot, weight, and body measurement tracking data models.

### Medication capability behavior

Capabilities are platform-level rows in `medication_capabilities` and are
assigned to medications through `medication_capability_assignments`. Tenant
admins can manually adjust advanced capability assignments in the medication
catalog, but the admin UI also derives defaults from medication attributes.

For weight-loss medications, the admin UI selects both `weight_tracker` and
`body_measurement`. Patient UI clients should use these returned capability keys
as feature gates for the corresponding health tracking experiences. The
`body_measurement` capability maps to the fixed four-location body measurement
log in the Health Tracking API: chest, waist, hips, and arms, all recorded in
inches in a single log entry.

| Key | Name | Behavior |
| --- | --- | --- |
| `weight_tracker` | Weight Tracker | Automatically selected for weight-loss medications and returned in medication capability payloads |
| `body_measurement` | Body Measurement | Automatically selected for weight-loss medications, returned in medication capability payloads, and used by Patient UI to show fixed chest/waist/hips/arms measurement tracking |

## Error Handling

Errors use a consistent JSON structure:

```json
{
  "error": {
    "code": "UNAUTHORIZED",
    "message": "Authorization header required"
  }
}
```

Common error codes:
- `UNAUTHORIZED`
- `NOT_FOUND`
- `PRODUCT_NOT_FOUND`
- `VALIDATION_ERROR`
- `FETCH_ERROR`
- `INSERT_ERROR`
- `RATE_LIMIT_EXCEEDED`
- `SERVER_ERROR`

---

## Rate Limiting

Requests are limited to **100 per minute** per client IP.

---

## Security Considerations

- Row Level Security (RLS) restricts access to the authenticated patient or tenant admins.
- Ensure all requests include a valid `Authorization` bearer token.
