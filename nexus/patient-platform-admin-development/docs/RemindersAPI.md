# Reminders API Documentation

> **Version:** 1.0.0\
> **Last Updated:** June 9, 2026\
> **Audience:** Patient Mobile App Developers\
> **Function:** `patient-api` (deployed to Supabase Edge Functions)

This document describes the reminder management endpoints exposed via
`/functions/v1/patient-api/reminders`. Reminders are persisted server-side so
settings sync across devices for the same patient account. Push notifications
are scheduled through the tenant's configured **OneSignal** integration.

---

## Table of Contents

1. [Overview](#overview)
2. [Base URL & Authentication](#base-url--authentication)
3. [Data Model](#data-model)
4. [Endpoints](#endpoints)
   - [List Reminders](#list-reminders)
   - [Create Reminder](#create-reminder)
   - [Update Reminder](#update-reminder)
   - [Toggle Reminder](#toggle-reminder)
   - [Delete Reminder](#delete-reminder)
5. [Error Codes](#error-codes)
6. [Push Notification Scheduling](#push-notification-scheduling)
7. [Backend Scheduler](#backend-scheduler)

---

## Overview

### Key Concepts

| Concept                   | Description                                                                            |
| ------------------------- | -------------------------------------------------------------------------------------- |
| **Reminder**              | A recurring schedule entry owned by one patient                                        |
| **OneSignal External ID** | The patient's Supabase `auth_user_id` — used to target push notifications cross-device |
| **Soft Delete**           | `DELETE` sets `deleted_at`; rows are never physically removed                          |
| **Notification Window**   | On create/update/enable, 30 days of OneSignal notifications are pre-scheduled          |
| **Scheduler**             | `reminder-scheduler` Edge Function runs daily (01:00 UTC) to top up the 30-day window  |

### Cross-Device Sync

Reminders are stored per patient account, not per device. A patient logging in
on a new device will see all their existing reminders. Push delivery uses the
OneSignal `external_id` (= patient `auth_user_id`), so notifications reach all
enrolled devices for that patient.

---

## Base URL & Authentication

```
VITE_SUPABASE_URL/functions/v1/patient-api
```

All reminder endpoints require a valid Bearer token obtained from
`POST /functions/v1/patient-api/auth/signin`.

### Required Headers

| Header          | Description             | Required                  |
| --------------- | ----------------------- | ------------------------- |
| `apikey`        | Supabase anon key       | Yes                       |
| `Authorization` | `Bearer <access_token>` | Yes                       |
| `Content-Type`  | `application/json`      | For POST / PATCH requests |

> `x-tenant-slug` is **not** required for reminder endpoints — tenant context
> is resolved from the authenticated patient session.

---

## Data Model

### Reminder Object

Returned by all endpoints in `data` (or as a single item inside `data`).

| Field                 | Type                | Description                                                                          |
| --------------------- | ------------------- | ------------------------------------------------------------------------------------ |
| `id`                  | `string` (UUID)     | Reminder identifier                                                                  |
| `category`            | `string`            | One of: `medication`, `body`, `energy`, `weight`                                     |
| `title`               | `string`            | Server-derived display label (see [Title Derivation](#title-derivation))             |
| `medication_id`       | `string \| null`    | UUID of linked medication (only for `medication` category)                           |
| `frequency`           | `string`            | `daily` or `weekly`                                                                  |
| `repeat_days`         | `number[] \| null`  | Days to fire for weekly reminders (0=Sun … 6=Sat). `null` for daily.                 |
| `time_local`          | `string`            | Local fire time in `HH:MM` format                                                    |
| `timezone`            | `string`            | IANA timezone, e.g. `America/New_York`                                               |
| `is_enabled`          | `boolean`           | Whether the reminder is active                                                       |
| `disabled_reason`     | `string \| null`    | `"user_disabled"` when toggled off by the patient; `null` otherwise                  |
| `subscription_linked` | `boolean`           | `true` when the reminder is coupled to a subscription lifecycle                      |
| `subscription_id`     | `string \| null`    | UUID of the linked subscription (optional)                                           |
| `schedule_summary`    | `string`            | Human-readable schedule, e.g. `"Mon, Wed, Fri • 9:00 AM"` or `"Every day • 9:00 AM"` |
| `created_at`          | `string` (ISO 8601) | Creation timestamp                                                                   |
| `updated_at`          | `string` (ISO 8601) | Last update timestamp                                                                |

### Title Derivation

`title` is always computed server-side and cannot be set by the client.

| Category     | Effective Title                                                                         |
| ------------ | --------------------------------------------------------------------------------------- |
| `medication` | Name of the linked medication (e.g. `"Semaglutide"`), or `"Medication"` if lookup fails |
| `body`       | `"Body Measure"`                                                                        |
| `energy`     | `"Energy Check"`                                                                        |
| `weight`     | `"Weight Check"`                                                                        |

---

## Endpoints

### List Reminders

Returns all active (non-deleted) reminders for the authenticated patient,
ordered by creation date ascending.

```http
GET /functions/v1/patient-api/reminders
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`

```json
{
  "data": [
    {
      "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
      "category": "medication",
      "title": "Semaglutide",
      "medication_id": "med-uuid",
      "frequency": "weekly",
      "repeat_days": [1, 3, 5],
      "time_local": "09:00",
      "timezone": "America/New_York",
      "is_enabled": true,
      "disabled_reason": null,
      "subscription_linked": false,
      "subscription_id": null,
      "schedule_summary": "Mon, Wed, Fri • 9:00 AM",
      "created_at": "2026-05-20T10:00:00Z",
      "updated_at": "2026-05-20T10:00:00Z"
    }
  ]
}
```

**Error Responses:**

| Code | Error              | Description                       |
| ---- | ------------------ | --------------------------------- |
| 401  | `UNAUTHORIZED`     | Missing or invalid Bearer token   |
| 403  | `ACCOUNT_INACTIVE` | Patient account is suspended      |
| 404  | `NOT_FOUND`        | Patient profile not found         |
| 500  | `FETCH_ERROR`      | Database error fetching reminders |

---

### Create Reminder

Creates a new reminder and immediately pre-schedules 30 days of push
notifications through OneSignal (non-blocking — creation succeeds even if
OneSignal scheduling fails).

```http
POST /functions/v1/patient-api/reminders
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
Content-Type: application/json
```

**Request Body:**

| Field                 | Type            | Required                        | Description                                                                            |
| --------------------- | --------------- | ------------------------------- | -------------------------------------------------------------------------------------- |
| `category`            | `string`        | Yes                             | One of: `medication`, `body`, `energy`, `weight`                                       |
| `medication_id`       | `string` (UUID) | Yes, if `category = medication` | Must belong to the patient's tenant. Rejected for other categories.                    |
| `frequency`           | `string`        | Yes                             | `daily` or `weekly`                                                                    |
| `repeat_days`         | `number[]`      | Yes, if `frequency = weekly`    | Non-empty array of weekday numbers (0–6). Ignored and stored as `null` for daily.      |
| `time_local`          | `string`        | Yes                             | Fire time in `HH:MM` or `HH:MM:SS` format                                              |
| `timezone`            | `string`        | Yes                             | IANA timezone (validated via `Intl.DateTimeFormat`)                                    |
| `subscription_linked` | `boolean`       | No                              | Defaults to `false`. Set to `true` to couple the reminder to a subscription lifecycle. |
| `subscription_id`     | `string` (UUID) | No                              | UUID of an active subscription belonging to this patient.                              |

> **Note:** `title` is derived server-side; do not send it.

**Example Request:**

```json
{
  "category": "medication",
  "medication_id": "med-uuid",
  "frequency": "weekly",
  "repeat_days": [1, 3, 5],
  "time_local": "09:00",
  "timezone": "America/New_York"
}
```

**Response:** `201 Created`

```json
{
  "data": {
    "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "category": "medication",
    "title": "Semaglutide",
    "medication_id": "med-uuid",
    "frequency": "weekly",
    "repeat_days": [1, 3, 5],
    "time_local": "09:00",
    "timezone": "America/New_York",
    "is_enabled": true,
    "disabled_reason": null,
    "subscription_linked": false,
    "subscription_id": null,
    "schedule_summary": "Mon, Wed, Fri • 9:00 AM",
    "created_at": "2026-06-09T12:00:00Z",
    "updated_at": "2026-06-09T12:00:00Z"
  }
}
```

**Error Responses:**

| Code | Error                    | Description                                            |
| ---- | ------------------------ | ------------------------------------------------------ |
| 400  | `INVALID_JSON`           | Request body is not valid JSON                         |
| 400  | `INVALID_CATEGORY`       | `category` not in the allowed set                      |
| 400  | `INVALID_FREQUENCY`      | `frequency` is not `daily` or `weekly`                 |
| 400  | `INVALID_TIME`           | `time_local` does not match `HH:MM` format             |
| 400  | `INVALID_TIMEZONE`       | `timezone` is missing or empty                         |
| 400  | `INVALID_TIMEZONE`       | `timezone` is not a recognised IANA timezone           |
| 400  | `INVALID_REPEAT_DAYS`    | `repeat_days` missing or invalid for weekly frequency  |
| 400  | `MEDICATION_REQUIRED`    | `medication_id` not provided for `medication` category |
| 400  | `MEDICATION_NOT_ALLOWED` | `medication_id` supplied for a non-medication category |
| 401  | `UNAUTHORIZED`           | Missing or invalid Bearer token                        |
| 403  | `ACCOUNT_INACTIVE`       | Patient account is suspended                           |
| 422  | `INVALID_MEDICATION`     | `medication_id` not found for this tenant              |
| 500  | `INSERT_ERROR`           | Database error creating reminder                       |

---

### Update Reminder

Partially updates an existing reminder. Only fields included in the request
body are changed; omitted fields retain their current values.

On a successful update, all future scheduled OneSignal notifications for the
reminder are **cancelled and rescheduled** from the updated definition (30-day
window). If the reminder is currently disabled, only cancellation occurs —
rescheduling happens when the reminder is re-enabled.

```http
PATCH /functions/v1/patient-api/reminders/{id}
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
Content-Type: application/json
```

**Path Parameters:**

| Parameter | Description                    |
| --------- | ------------------------------ |
| `id`      | UUID of the reminder to update |

**Request Body** (all fields optional):

| Field                 | Type               | Description                                                                                            |
| --------------------- | ------------------ | ------------------------------------------------------------------------------------------------------ |
| `category`            | `string`           | One of: `medication`, `body`, `energy`, `weight`                                                       |
| `medication_id`       | `string \| null`   | Required when changing to or from `medication` category. Must be `null` for non-medication categories. |
| `frequency`           | `string`           | `daily` or `weekly`                                                                                    |
| `repeat_days`         | `number[] \| null` | Required when frequency is (or remains) `weekly`                                                       |
| `time_local`          | `string`           | Fire time in `HH:MM` format                                                                            |
| `timezone`            | `string`           | IANA timezone                                                                                          |
| `subscription_linked` | `boolean`          | Couple/decouple from subscription lifecycle                                                            |
| `subscription_id`     | `string \| null`   | Linked subscription UUID, or `null` to unlink                                                          |

**Example Request:**

```json
{
  "time_local": "10:30",
  "repeat_days": [2, 4]
}
```

**Response:** `200 OK`

```json
{
  "data": {
    "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "category": "medication",
    "title": "Semaglutide",
    "medication_id": "med-uuid",
    "frequency": "weekly",
    "repeat_days": [2, 4],
    "time_local": "10:30",
    "timezone": "America/New_York",
    "is_enabled": true,
    "disabled_reason": null,
    "subscription_linked": false,
    "subscription_id": null,
    "schedule_summary": "Tue, Thu • 10:30 AM",
    "created_at": "2026-06-09T12:00:00Z",
    "updated_at": "2026-06-09T14:00:00Z"
  }
}
```

**Error Responses:**

| Code | Error                    | Description                                           |
| ---- | ------------------------ | ----------------------------------------------------- |
| 400  | `INVALID_JSON`           | Request body is not valid JSON                        |
| 400  | `INVALID_CATEGORY`       | `category` not in the allowed set                     |
| 400  | `INVALID_FREQUENCY`      | `frequency` not `daily` or `weekly`                   |
| 400  | `INVALID_REPEAT_DAYS`    | `repeat_days` missing or invalid for weekly frequency |
| 400  | `INVALID_TIMEZONE`       | Unrecognised IANA timezone                            |
| 400  | `MEDICATION_REQUIRED`    | `medication_id` missing for `medication` category     |
| 400  | `MEDICATION_NOT_ALLOWED` | `medication_id` supplied for non-medication category  |
| 401  | `UNAUTHORIZED`           | Missing or invalid Bearer token                       |
| 403  | `ACCOUNT_INACTIVE`       | Patient account is suspended                          |
| 404  | `NOT_FOUND`              | Reminder not found (or belongs to another patient)    |
| 422  | `INVALID_MEDICATION`     | `medication_id` not found for this tenant             |
| 500  | `FETCH_ERROR`            | Database error fetching existing reminder             |
| 500  | `UPDATE_ERROR`           | Database error updating reminder                      |

---

### Toggle Reminder

Enables or disables a reminder without changing any other settings.

- **Disabling** (`is_enabled: false`) cancels all future scheduled OneSignal
  notifications and sets `disabled_reason` to `"user_disabled"`.
- **Enabling** (`is_enabled: true`) clears `disabled_reason` (if it was
  `"user_disabled"`) and pre-schedules a fresh 30-day notification window.

```http
PATCH /functions/v1/patient-api/reminders/{id}/enabled
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
Content-Type: application/json
```

**Path Parameters:**

| Parameter | Description          |
| --------- | -------------------- |
| `id`      | UUID of the reminder |

**Request Body:**

| Field        | Type      | Required | Description                          |
| ------------ | --------- | -------- | ------------------------------------ |
| `is_enabled` | `boolean` | Yes      | `true` to enable, `false` to disable |

**Example Request:**

```json
{ "is_enabled": false }
```

**Response:** `200 OK`

```json
{
  "data": {
    "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "category": "medication",
    "title": "Semaglutide",
    "medication_id": "med-uuid",
    "frequency": "weekly",
    "repeat_days": [1, 3, 5],
    "time_local": "09:00",
    "timezone": "America/New_York",
    "is_enabled": false,
    "disabled_reason": "user_disabled",
    "subscription_linked": false,
    "subscription_id": null,
    "schedule_summary": "Mon, Wed, Fri • 9:00 AM",
    "created_at": "2026-06-09T12:00:00Z",
    "updated_at": "2026-06-09T15:00:00Z"
  }
}
```

**Error Responses:**

| Code | Error              | Description                                        |
| ---- | ------------------ | -------------------------------------------------- |
| 400  | `INVALID_JSON`     | Request body is not valid JSON                     |
| 400  | `INVALID_INPUT`    | `is_enabled` is missing or not a boolean           |
| 401  | `UNAUTHORIZED`     | Missing or invalid Bearer token                    |
| 403  | `ACCOUNT_INACTIVE` | Patient account is suspended                       |
| 404  | `NOT_FOUND`        | Reminder not found (or belongs to another patient) |
| 500  | `FETCH_ERROR`      | Database error fetching existing reminder          |
| 500  | `UPDATE_ERROR`     | Database error updating reminder                   |

---

### Delete Reminder

Soft-deletes a reminder (`deleted_at` is set; the row is retained). All future
scheduled OneSignal notifications are cancelled before deletion.

```http
DELETE /functions/v1/patient-api/reminders/{id}
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Path Parameters:**

| Parameter | Description                    |
| --------- | ------------------------------ |
| `id`      | UUID of the reminder to delete |

**Response:** `200 OK`

```json
{
  "data": {
    "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "deleted": true
  }
}
```

**Error Responses:**

| Code | Error              | Description                                        |
| ---- | ------------------ | -------------------------------------------------- |
| 401  | `UNAUTHORIZED`     | Missing or invalid Bearer token                    |
| 403  | `ACCOUNT_INACTIVE` | Patient account is suspended                       |
| 404  | `NOT_FOUND`        | Reminder not found (or belongs to another patient) |
| 500  | `FETCH_ERROR`      | Database error fetching existing reminder          |
| 500  | `DELETE_ERROR`     | Database error soft-deleting reminder              |

---

## Error Codes

All error responses follow this shape:

```json
{
  "error": {
    "code": "NOT_FOUND",
    "message": "Reminder not found"
  }
}
```

### Common Errors (all endpoints)

| HTTP | Code                  | Cause                                                                           |
| ---- | --------------------- | ------------------------------------------------------------------------------- |
| 401  | `UNAUTHORIZED`        | Missing, expired, or invalid Bearer token                                       |
| 403  | `ACCOUNT_INACTIVE`    | Patient account is suspended or deactivated                                     |
| 404  | `NOT_FOUND`           | Patient profile missing (`GET`/`DELETE`) or reminder missing (`PATCH`/`DELETE`) |
| 429  | `RATE_LIMIT_EXCEEDED` | Too many requests from the same IP                                              |

---

## Push Notification Scheduling

Push notifications are delivered via the tenant's **OneSignal** integration.

### OneSignal Setup

| Tenant Integration Key   | Description                     |
| ------------------------ | ------------------------------- |
| `onesignal.app_id`       | OneSignal App ID for the tenant |
| `onesignal.rest_api_key` | OneSignal REST API key          |

If the tenant has no OneSignal integration configured, reminders are saved
normally but no push notifications are scheduled. This is non-fatal.

### External User Identification

The mobile app must identify the OneSignal user using the patient's
`auth_user_id`, returned as part of the sign-in response. This enables
targeting notifications across all devices enrolled for the same account.

```
OneSignal external_id = user.id  (Supabase auth user UUID, from sign-in response)
```

### Idempotency

Each OneSignal notification is keyed by `{reminder_id}:{YYYY-MM-DD}`, which
prevents duplicate scheduling when the scheduler or a retry re-processes the
same reminder day.

### Notification Window

| Trigger                           | Window Scheduled                                |
| --------------------------------- | ----------------------------------------------- |
| Create reminder                   | 30 days from now                                |
| Update reminder (schedule change) | Cancel existing + 30 days from now              |
| Toggle ON                         | 30 days from now                                |
| Toggle OFF                        | Cancel all future notifications                 |
| Delete reminder                   | Cancel all future notifications                 |
| Daily scheduler run               | Top up to 30 days if window drops below 14 days |

---

## Backend Scheduler

`reminder-scheduler` is an internal Supabase Edge Function. It is **not**
called by the mobile app directly.

### Schedule

Configured in `supabase/config.toml` to run daily at **01:00 UTC** via
`pg_cron`.

### What It Does

1. Fetches all enabled, non-deleted reminders across all tenants.
2. For each reminder, checks how far ahead notifications are already scheduled.
3. If the furthest scheduled notification is within 14 days, schedules
   additional occurrences to restore the 30-day window.
4. Marks `patient_reminder_notifications` rows whose `scheduled_for` is more
   than 1 hour in the past as `delivered` (hygiene cleanup).

### Authorization

The scheduler validates requests with a `CRON_SECRET` environment variable.
Calls without a matching `Authorization: Bearer <CRON_SECRET>` header are
rejected with `401`.

### Response

```json
{
  "ok": true,
  "reminders_processed": 42,
  "notifications_scheduled": 156,
  "notifications_cleaned": 12
}
```

### Required Secrets

| Secret                      | Description                                           |
| --------------------------- | ----------------------------------------------------- |
| `SUPABASE_URL`              | Supabase project URL                                  |
| `SUPABASE_SERVICE_ROLE_KEY` | Service role key (bypasses RLS)                       |
| `CRON_SECRET`               | Shared secret validated on every scheduler invocation |
