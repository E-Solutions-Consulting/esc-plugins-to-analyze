# Health Tracking API Documentation

> **Version:** 1.0.0
> **Last Updated:** June 1, 2026
> **Audience:** Patient UI Developers

This document describes the Health Tracking API endpoints used to record and retrieve patient-reported shot intake, weight, body measurement, mood, activity, mood change, energy, symptom data, and active tenant mood, symptom, activity, and injection site definitions.

---

## Table of Contents

1. [Overview](#overview)
2. [Authentication](#authentication)
3. [Base Configuration](#base-configuration)
4. [Endpoints](#endpoints)
   - [Get Shot Tracker](#get-shot-tracker)
   - [Get Injection Sites](#get-injection-sites)
   - [Create Shot Tracker Entry](#create-shot-tracker-entry)
   - [Get Weight Tracker](#get-weight-tracker)
   - [Create Weight Tracker Entry](#create-weight-tracker-entry)
   - [Delete Weight Tracker Entry](#delete-weight-tracker-entry)
   - [Get Body Measurement Tracker](#get-body-measurement-tracker)
   - [Create Body Measurement Tracker Entry](#create-body-measurement-tracker-entry)
   - [Delete Body Measurement Tracker Entry](#delete-body-measurement-tracker-entry)
   - [Get Mood Tracker](#get-mood-tracker)
   - [Create Mood Tracker Entry](#create-mood-tracker-entry)
   - [Delete Mood Tracker Entry](#delete-mood-tracker-entry)
   - [Get Activity Tracker](#get-activity-tracker)
   - [Create Activity Tracker Entry](#create-activity-tracker-entry)
   - [Delete Activity Tracker Entry](#delete-activity-tracker-entry)
   - [Get Energy Tracker](#get-energy-tracker)
   - [Create Energy Tracker Entry](#create-energy-tracker-entry)
   - [Delete Energy Tracker Entry](#delete-energy-tracker-entry)
   - [Get Mood Definitions](#get-mood-definitions)
   - [Get Activity Definitions](#get-activity-definitions)
   - [Get Symptom Definitions](#get-symptom-definitions)
   - [Get Symptom Tracker](#get-symptom-tracker)
   - [Create Symptom Tracker Entry](#create-symptom-tracker-entry)
   - [Delete Symptom Tracker Entry](#delete-symptom-tracker-entry)
5. [Data Model](#data-model)
6. [Error Handling](#error-handling)
7. [Rate Limiting](#rate-limiting)
8. [Security Considerations](#security-considerations)

---

## Overview

The Health Tracking API is an authenticated Edge Function that allows patients to record and retrieve their medication shot intake records, weight entries, body measurement entries, mood entries, activity entries, energy entries, symptom entries, and active mood, symptom, activity, and injection site definitions for their tenant.

### Key Concepts

| Concept                 | Description                                                                      |
| ----------------------- | -------------------------------------------------------------------------------- |
| **Medication Intake**   | A patient-reported record of a medication shot taken at a specific time          |
| **Injection Site**      | A tenant-managed shot location with a name and image shown in shot-tracker forms |
| **Body Measurement Capability** | The `body_measurement` medication capability that enables body measurement behavior for eligible medications |
| **Body Measurement Entry** | A patient-reported body measurement log containing chest, waist, hips, and arms values in inches |
| **Weight Entry**        | A patient-reported weight measurement                                            |
| **Mood Entry**          | A patient-reported mood label                                                    |
| **Activity Entry**      | A patient-reported activity label                                                |
| **Patient**             | The authenticated user (Supabase Auth) mapped to a `patients` table record       |
| **Mood Definition**     | An active tenant-configured mood option available for patient tracking           |
| **Activity Definition** | An active tenant-configured activity option available for patient tracking       |
| **Symptom Definition**  | An active tenant-configured symptom option available for patient tracking        |
| **Symptom Entry**       | A patient-reported symptom with optional severity and notes                      |
| **Energy Entry**        | A patient-reported energy score with optional notes                              |

---

## Authentication

All endpoints require a valid `Authorization: Bearer <token>` header. The
authenticated user is mapped to the `patients` table via `auth_user_id`.

### Base URL

```
VITE_SUPABASE_URL/functions/v1/healthtracking-api
```

### Required Headers

| Header          | Description         | Required          |
| --------------- | ------------------- | ----------------- |
| `apikey`        | Supabase anon key   | Yes               |
| `Authorization` | Bearer access token | Yes               |
| `Content-Type`  | `application/json`  | For POST requests |

---

## Endpoints

### Get Shot Tracker

Returns medication shot tracker entries for the authenticated patient.

```http
GET /functions/v1/healthtracking-api/shot_tracker
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`

```json
{
  "data": [
    {
      "id": "intake-uuid",
      "medication_id": "medication-uuid",
      "injection_site": {
        "id": "site-uuid",
        "label": "Left abdomen",
        "image_url": "https://cdn.example.com/injection-sites/left-abdomen.png",
        "is_active": true,
        "display_order": 0,
        "created_at": "2026-03-30T12:00:00Z",
        "updated_at": "2026-03-30T12:00:00Z"
      },
      "dosage_strength": 10.5,
      "pain_level": 2,
      "intake_date": "2026-02-08T19:30:00Z",
      "created_at": "2026-02-08T19:31:02Z",
      "updated_at": "2026-02-08T19:31:02Z"
    }
  ]
}
```

---

### Get Injection Sites

Returns the list of active tenant-configured injection sites for the
authenticated patient.

```http
GET /functions/v1/healthtracking-api/injection_sites
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`

```json
{
  "data": [
    {
      "id": "site-uuid",
      "label": "Left abdomen",
      "image_url": "https://cdn.example.com/injection-sites/left-abdomen.png",
      "is_active": true,
      "display_order": 0,
      "created_at": "2026-03-30T12:00:00Z",
      "updated_at": "2026-03-30T12:00:00Z"
    }
  ]
}
```

---

### Create Shot Tracker Entry

Creates a new medication shot tracker record for the authenticated patient.

`shot_location` is no longer accepted by this endpoint. Clients must send
`injection_site_id`.

```http
POST /functions/v1/healthtracking-api/shot_tracker
Authorization: Bearer <access_token>
Content-Type: application/json
apikey: <supabase-anon-key>

{
  "injection_site_id": "site-uuid",
  "dosage_strength": 10.5,
  "pain_level": 2,
  "medication_id": "medication-uuid",
  "intake_date": "2026-02-08T19:30:00Z"
}
```

**Request Body:**

| Field               | Type          | Required | Description                                          |
| ------------------- | ------------- | -------- | ---------------------------------------------------- |
| `injection_site_id` | string (UUID) | Yes      | Active injection site ID from `GET /injection_sites` |
| `dosage_strength`   | number        | Yes      | Dosage strength (decimal)                            |
| `pain_level`        | integer       | Yes      | Pain level from 0 to 5                               |
| `medication_id`     | string (UUID) | Yes      | ID from the `medications` table                      |
| `intake_date`       | string        | Yes      | ISO 8601 date/time string                            |

**Response:** `201 Created`

```json
{
  "data": {
    "id": "intake-uuid",
    "medication_id": "medication-uuid",
    "injection_site": {
      "id": "site-uuid",
      "label": "Left abdomen",
      "image_url": "https://cdn.example.com/injection-sites/left-abdomen.png",
      "is_active": true,
      "display_order": 0,
      "created_at": "2026-03-30T12:00:00Z",
      "updated_at": "2026-03-30T12:00:00Z"
    },
    "dosage_strength": 10.5,
    "pain_level": 2,
    "intake_date": "2026-02-08T19:30:00Z",
    "created_at": "2026-02-08T19:31:02Z",
    "updated_at": "2026-02-08T19:31:02Z"
  }
}
```

---

### Get Weight Tracker

Returns weight entries for the authenticated patient.

```http
GET /functions/v1/healthtracking-api/weight_tracker
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`

```json
{
  "data": [
    {
      "id": "weight-uuid",
      "weight_value": 160.2,
      "weight_unit": "lb",
      "weighed_at": "2026-02-10T19:30:00Z",
      "created_at": "2026-02-10T19:31:02Z",
      "updated_at": "2026-02-10T19:31:02Z"
    }
  ]
}
```

---

### Create Weight Tracker Entry

Creates a new weight entry for the authenticated patient.

```http
POST /functions/v1/healthtracking-api/weight_tracker
Authorization: Bearer <access_token>
Content-Type: application/json
apikey: <supabase-anon-key>

{
  "weight": 160.2,
  "weight_unit": "lb",
  "weighed_at": "2026-02-10T19:30:00Z"
}
```

**Request Body:**

| Field         | Type   | Required | Description                                 |
| ------------- | ------ | -------- | ------------------------------------------- |
| `weight`      | number | Yes      | Weight value (positive number)              |
| `weight_unit` | string | No       | `lb` or `kg` (defaults to `lb`)             |
| `weighed_at`  | string | No       | ISO 8601 date/time string (defaults to now) |

**Response:** `201 Created`

```json
{
  "data": {
    "id": "weight-uuid",
    "weight_value": 160.2,
    "weight_unit": "lb",
    "weighed_at": "2026-02-10T19:30:00Z",
    "created_at": "2026-02-10T19:31:02Z",
    "updated_at": "2026-02-10T19:31:02Z"
  }
}
```

---

### Get Body Measurement Tracker

Returns body measurement entries for the authenticated patient. Each log entry
contains all four supported locations: chest, waist, hips, and arms.

```http
GET /functions/v1/healthtracking-api/body_measurement_tracker
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`

```json
{
  "data": [
    {
      "id": "measurement-uuid",
      "chest_inches": 39.25,
      "waist_inches": 34.5,
      "hips_inches": 41.0,
      "arms_inches": 13.25,
      "measured_at": "2026-06-01T19:30:00Z",
      "created_at": "2026-06-01T19:31:02Z",
      "updated_at": "2026-06-01T19:31:02Z"
    }
  ]
}
```

---

### Create Body Measurement Tracker Entry

Creates a new body measurement entry for the authenticated patient. The request
must include all four measurements in inches.

```http
POST /functions/v1/healthtracking-api/body_measurement_tracker
Authorization: Bearer <access_token>
Content-Type: application/json
apikey: <supabase-anon-key>

{
  "chest_inches": 39.25,
  "waist_inches": 34.5,
  "hips_inches": 41.0,
  "arms_inches": 13.25,
  "measured_at": "2026-06-01T19:30:00Z"
}
```

**Request Body:**

| Field          | Type   | Required | Description                                |
| -------------- | ------ | -------- | ------------------------------------------ |
| `chest_inches` | number | Yes      | Chest measurement in inches; must be positive |
| `waist_inches` | number | Yes      | Waist measurement in inches; must be positive |
| `hips_inches`  | number | Yes      | Hips measurement in inches; must be positive |
| `arms_inches`  | number | Yes      | Arms measurement in inches; must be positive |
| `measured_at`  | string | No       | ISO 8601 date/time string; defaults to now |

`recorded_at` and `measurement_date` are accepted as aliases for `measured_at`.

**Response:** `201 Created`

```json
{
  "data": {
    "id": "measurement-uuid",
    "chest_inches": 39.25,
    "waist_inches": 34.5,
    "hips_inches": 41.0,
    "arms_inches": 13.25,
    "measured_at": "2026-06-01T19:30:00Z",
    "created_at": "2026-06-01T19:31:02Z",
    "updated_at": "2026-06-01T19:31:02Z"
  }
}
```

---

### Get Mood Tracker

Returns mood entries for the authenticated patient.

```http
GET /functions/v1/healthtracking-api/mood_tracker
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`

```json
{
  "data": [
    {
      "id": "mood-uuid",
      "mood_change_definition_id": "mood-def-uuid",
      "mood_change_label": "calm",
      "recorded_at": "2026-02-10T19:30:00Z",
      "created_at": "2026-02-10T19:31:02Z",
      "updated_at": "2026-02-10T19:31:02Z"
    }
  ]
}
```

---

### Create Mood Tracker Entry

Creates a new mood entry for the authenticated patient.

```http
POST /functions/v1/healthtracking-api/mood_tracker
Authorization: Bearer <access_token>
Content-Type: application/json
apikey: <supabase-anon-key>

{
  "mood_ids": ["mood-def-uuid-1", "mood-def-uuid-2"],
  "recorded_at": "2026-02-10T19:30:00Z"
}
```

**Request Body:**

| Field         | Type   | Required | Description                                 |
| ------------- | ------ | -------- | ------------------------------------------- |
| `mood_ids`    | array  | Yes      | List of tenant mood definition IDs          |
| `recorded_at` | string | No       | ISO 8601 date/time string (defaults to now) |

**Response:** `201 Created`

```json
{
  "data": [
    {
      "id": "mood-uuid",
      "mood_change_definition_id": "mood-def-uuid-1",
      "mood_change_label": "calm",
      "recorded_at": "2026-02-10T19:30:00Z",
      "created_at": "2026-02-10T19:31:02Z",
      "updated_at": "2026-02-10T19:31:02Z"
    }
  ]
}
```

---

### Delete Mood Tracker Entry

Deletes a mood entry for the authenticated patient.

```http
DELETE /functions/v1/healthtracking-api/mood_tracker/{mood_id}
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`

```json
{
  "data": {
    "id": "mood-uuid",
    "deleted": true
  }
}
```

---

### Get Activity Tracker

Returns activity entries for the authenticated patient.

```http
GET /functions/v1/healthtracking-api/activity_tracker
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`

```json
{
  "data": [
    {
      "id": "activity-uuid",
      "activity_definition_id": "activity-def-uuid",
      "activity_label": "Walking",
      "recorded_at": "2026-02-11T08:30:00Z",
      "created_at": "2026-02-11T08:31:02Z",
      "updated_at": "2026-02-11T08:31:02Z"
    }
  ]
}
```

---

### Create Activity Tracker Entry

Creates a new activity entry for the authenticated patient.

```http
POST /functions/v1/healthtracking-api/activity_tracker
Authorization: Bearer <access_token>
Content-Type: application/json
apikey: <supabase-anon-key>

{
  "activity_ids": ["activity-def-uuid-1", "activity-def-uuid-2"],
  "recorded_at": "2026-02-11T08:30:00Z"
}
```

**Request Body:**

| Field          | Type   | Required | Description                                 |
| -------------- | ------ | -------- | ------------------------------------------- |
| `activity_ids` | array  | Yes      | List of tenant activity definition IDs      |
| `recorded_at`  | string | No       | ISO 8601 date/time string (defaults to now) |

**Response:** `201 Created`

```json
{
  "data": [
    {
      "id": "activity-uuid",
      "activity_definition_id": "activity-def-uuid-1",
      "activity_label": "Walking",
      "recorded_at": "2026-02-11T08:30:00Z",
      "created_at": "2026-02-11T08:31:02Z",
      "updated_at": "2026-02-11T08:31:02Z"
    }
  ]
}
```

---

### Delete Activity Tracker Entry

Deletes an activity entry for the authenticated patient.

```http
DELETE /functions/v1/healthtracking-api/activity_tracker/{activity_id}
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`

```json
{
  "data": {
    "id": "activity-uuid",
    "deleted": true
  }
}
```

---

### Get Energy Tracker

Returns energy entries for the authenticated patient.

```http
GET /functions/v1/healthtracking-api/energy_tracker
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`

```json
{
  "data": [
    {
      "id": "energy-uuid",
      "energy_value": 6,
      "recorded_at": "2026-02-11T16:15:00Z",
      "created_at": "2026-02-11T16:15:30Z",
      "updated_at": "2026-02-11T16:15:30Z"
    }
  ]
}
```

---

### Create Energy Tracker Entry

Creates a new energy entry for the authenticated patient.

```http
POST /functions/v1/healthtracking-api/energy_tracker
Authorization: Bearer <access_token>
Content-Type: application/json
apikey: <supabase-anon-key>

{
  "energy_value": 6,
  "recorded_at": "2026-02-11T16:15:00Z"
}
```

**Request Body:**

| Field          | Type    | Required | Description                                 |
| -------------- | ------- | -------- | ------------------------------------------- |
| `energy_value` | integer | Yes      | Energy score from 1 to 10                   |
| `recorded_at`  | string  | No       | ISO 8601 date/time string (defaults to now) |

**Response:** `201 Created`

```json
{
  "data": {
    "id": "energy-uuid",
    "energy_value": 6,
    "recorded_at": "2026-02-11T16:15:00Z",
    "created_at": "2026-02-11T16:15:30Z",
    "updated_at": "2026-02-11T16:15:30Z"
  }
}
```

---

### Delete Energy Tracker Entry

Deletes a specific energy entry for the authenticated patient.

```http
DELETE /functions/v1/healthtracking-api/energy_tracker/{energy_id}
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`

```json
{
  "data": {
    "id": "energy-uuid",
    "deleted": true
  }
}
```

---

### Get Mood Definitions

Returns the list of active mood change definitions configured for the authenticated patient's tenant.

```http
GET /functions/v1/healthtracking-api/moods
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

`GET /functions/v1/healthtracking-api/mood_changes` is also accepted as an alias.

**Response:** `200 OK`

```json
{
  "data": [
    {
      "id": "mood-change-uuid",
      "label": "Irritable",
      "is_active": true,
      "display_order": 0,
      "created_at": "2026-02-12T19:31:02Z",
      "updated_at": "2026-02-12T19:31:02Z"
    }
  ]
}
```

---

### Get Activity Definitions

Returns the list of active activity definitions configured for the authenticated patient's tenant.

```http
GET /functions/v1/healthtracking-api/activities
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`

```json
{
  "data": [
    {
      "id": "activity-uuid",
      "label": "Walking",
      "is_active": true,
      "display_order": 0,
      "created_at": "2026-02-12T19:31:02Z",
      "updated_at": "2026-02-12T19:31:02Z"
    }
  ]
}
```

---

### Get Symptom Definitions

Returns the list of active symptom definitions configured for the authenticated patient's tenant.

```http
GET /functions/v1/healthtracking-api/symptoms
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`

```json
{
  "data": [
    {
      "id": "symptom-uuid",
      "label": "Nausea",
      "is_active": true,
      "display_order": 0,
      "created_at": "2026-02-12T19:31:02Z",
      "updated_at": "2026-02-12T19:31:02Z"
    }
  ]
}
```

---

### Get Symptom Tracker

Returns symptom entries for the authenticated patient.

```http
GET /functions/v1/healthtracking-api/symptom_tracker
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`

```json
{
  "data": [
    {
      "id": "symptom-uuid",
      "symptom_definition_id": "symptom-def-uuid",
      "symptom_label": "nausea",
      "recorded_at": "2026-02-11T19:30:00Z",
      "created_at": "2026-02-11T19:31:02Z",
      "updated_at": "2026-02-11T19:31:02Z"
    }
  ]
}
```

---

### Create Symptom Tracker Entry

Creates a new symptom entry for the authenticated patient.

```http
POST /functions/v1/healthtracking-api/symptom_tracker
Authorization: Bearer <access_token>
Content-Type: application/json
apikey: <supabase-anon-key>

{
  "symptom_ids": ["symptom-def-uuid-1", "symptom-def-uuid-2"],
  "recorded_at": "2026-02-11T19:30:00Z"
}
```

**Request Body:**

| Field           | Type   | Required | Description                                 |
| --------------- | ------ | -------- | ------------------------------------------- |
| `symptom_ids`   | array  | Yes\*    | List of tenant symptom definition IDs       |
| `symptom_label` | string | Yes\*    | Symptom name/label (single entry)           |
| `recorded_at`   | string | No       | ISO 8601 date/time string (defaults to now) |

**\*Either `symptom_ids` or `symptom_label` is required.**

**Response:** `201 Created`

```json
{
  "data": [
    {
      "id": "symptom-entry-uuid-1",
      "symptom_definition_id": "symptom-def-uuid-1",
      "symptom_label": "nausea",
      "recorded_at": "2026-02-11T19:30:00Z",
      "created_at": "2026-02-11T19:31:02Z",
      "updated_at": "2026-02-11T19:31:02Z"
    }
  ]
}
```

---

### Delete Symptom Tracker Entry

Deletes a symptom entry for the authenticated patient.

```http
DELETE /functions/v1/healthtracking-api/symptom_tracker/{symptom_id}
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`

```json
{
  "data": {
    "id": "symptom-uuid",
    "deleted": true
  }
}
```

---

### Delete Weight Tracker Entry

Deletes a weight entry for the authenticated patient.

```http
DELETE /functions/v1/healthtracking-api/weight_tracker/{weight_entry_id}
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`

```json
{
  "message": "Weight entry deleted",
  "data": {
    "id": "weight-uuid"
  }
}
```

---

### Delete Body Measurement Tracker Entry

Deletes a body measurement entry for the authenticated patient.

```http
DELETE /functions/v1/healthtracking-api/body_measurement_tracker/{measurement_entry_id}
Authorization: Bearer <access_token>
apikey: <supabase-anon-key>
```

**Response:** `200 OK`

```json
{
  "message": "Body measurement entry deleted",
  "data": {
    "id": "measurement-uuid"
  }
}
```

---

## Data Model

### `medication_shot_intakes`

| Column              | Type        | Description                              |
| ------------------- | ----------- | ---------------------------------------- |
| `id`                | UUID        | Primary key                              |
| `tenant_id`         | UUID        | Tenant associated with the patient       |
| `patient_id`        | UUID        | Patient who recorded the intake          |
| `medication_id`     | UUID        | Medication reference                     |
| `injection_site_id` | UUID        | Injection site reference used by the API |
| `dosage_strength`   | numeric     | Dosage strength                          |
| `pain_level`        | integer     | Pain level from 0 to 5                   |
| `intake_date`       | timestamptz | Date/time of intake                      |
| `created_at`        | timestamptz | Record creation time                     |
| `updated_at`        | timestamptz | Record last update time                  |

---

### `tenant_injection_site_definitions`

| Column          | Type        | Description                                       |
| --------------- | ----------- | ------------------------------------------------- |
| `id`            | UUID        | Primary key                                       |
| `tenant_id`     | UUID        | Owning tenant                                     |
| `label`         | text        | Injection site label shown to patients            |
| `image_url`     | text        | Public image URL shown in the shot tracker picker |
| `is_active`     | boolean     | Whether the site is available for selection       |
| `display_order` | integer     | Optional display sort order                       |
| `created_at`    | timestamptz | Record creation time                              |
| `updated_at`    | timestamptz | Record last update time                           |

---

### `patient_weight_entries`

| Column         | Type        | Description                        |
| -------------- | ----------- | ---------------------------------- |
| `id`           | UUID        | Primary key                        |
| `tenant_id`    | UUID        | Tenant associated with the patient |
| `patient_id`   | UUID        | Patient who recorded the entry     |
| `weight_value` | numeric     | Weight value                       |
| `weight_unit`  | text        | `lb` or `kg`                       |
| `weighed_at`   | timestamptz | Date/time of the measurement       |
| `created_at`   | timestamptz | Record creation time               |
| `updated_at`   | timestamptz | Record last update time            |

---

### `patient_body_measurement_entries`

| Column                              | Type        | Description                                      |
| ----------------------------------- | ----------- | ------------------------------------------------ |
| `id`                                | UUID        | Primary key                                      |
| `tenant_id`                         | UUID        | Tenant associated with the patient               |
| `patient_id`                        | UUID        | Patient who recorded the entry                   |
| `chest_inches`                      | numeric     | Chest measurement in inches                      |
| `waist_inches`                      | numeric     | Waist measurement in inches                      |
| `hips_inches`                       | numeric     | Hips measurement in inches                       |
| `arms_inches`                       | numeric     | Arms measurement in inches                       |
| `measured_at`                       | timestamptz | Date/time of the body measurement                |
| `created_at`                        | timestamptz | Record creation time                             |
| `updated_at`                        | timestamptz | Record last update time                          |

Each entry is scoped to the authenticated patient and must include positive
values for chest, waist, hips, and arms.

---

### `patient_mood_change_entries`

| Column                      | Type        | Description                             |
| --------------------------- | ----------- | --------------------------------------- |
| `id`                        | UUID        | Primary key                             |
| `tenant_id`                 | UUID        | Tenant associated with the patient      |
| `patient_id`                | UUID        | Patient who recorded the entry          |
| `mood_change_definition_id` | UUID        | Tenant mood change definition reference |
| `mood_change_label`         | text        | Mood change label/name                  |
| `recorded_at`               | timestamptz | Date/time of mood change entry          |
| `created_at`                | timestamptz | Record creation time                    |
| `updated_at`                | timestamptz | Record last update time                 |

---

### `tenant_mood_change_definitions`

| Column                   | Type        | Description                          |
| ------------------------ | ----------- | ------------------------------------ |
| `id`                     | UUID        | Primary key                          |
| `tenant_id`              | UUID        | Tenant associated with the patient   |
| `patient_id`             | UUID        | Patient who recorded the entry       |
| `activity_definition_id` | UUID        | Tenant activity definition reference |
| `activity_label`         | text        | Activity label/name                  |
| `recorded_at`            | timestamptz | Date/time of activity entry          |
| `created_at`             | timestamptz | Record creation time                 |
| `updated_at`             | timestamptz | Record last update time              |

---

### `tenant_activity_definitions`

| Column         | Type        | Description                        |
| -------------- | ----------- | ---------------------------------- |
| `id`           | UUID        | Primary key                        |
| `tenant_id`    | UUID        | Tenant associated with the patient |
| `patient_id`   | UUID        | Patient who recorded the entry     |
| `energy_value` | integer     | Energy score from 1 to 10          |
| `recorded_at`  | timestamptz | Date/time of energy entry          |
| `created_at`   | timestamptz | Record creation time               |
| `updated_at`   | timestamptz | Record last update time            |

---

### `tenant_symptom_definitions`

| Column                  | Type        | Description                         |
| ----------------------- | ----------- | ----------------------------------- |
| `id`                    | UUID        | Primary key                         |
| `tenant_id`             | UUID        | Tenant associated with the patient  |
| `patient_id`            | UUID        | Patient who recorded the entry      |
| `symptom_definition_id` | UUID        | Tenant symptom definition reference |
| `symptom_label`         | text        | Symptom label/name                  |
| `recorded_at`           | timestamptz | Date/time of symptom entry          |
| `created_at`            | timestamptz | Record creation time                |
| `updated_at`            | timestamptz | Record last update time             |

---

### `patient_symptom_entries`

| Column                  | Type        | Description                         |
| ----------------------- | ----------- | ----------------------------------- |
| `id`                    | UUID        | Primary key                         |
| `tenant_id`             | UUID        | Tenant associated with the patient  |
| `patient_id`            | UUID        | Patient who recorded the entry      |
| `symptom_definition_id` | UUID        | Tenant symptom definition reference |
| `symptom_label`         | text        | Symptom label/name                  |
| `recorded_at`           | timestamptz | Date/time of symptom entry          |
| `created_at`            | timestamptz | Record creation time                |
| `updated_at`            | timestamptz | Record last update time             |

---

## Error Handling

Errors use a consistent JSON structure:

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "weight must be a positive number"
  }
}
```

Common error codes:

- `UNAUTHORIZED`
- `NOT_FOUND`
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

- Row Level Security (RLS) restricts access to the authenticated patient or
  tenant admins.
- Records are only insertable by the patient who owns the record.
- Ensure all requests include a valid `Authorization` bearer token.
