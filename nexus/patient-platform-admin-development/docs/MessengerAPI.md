# Messenger API Documentation

> **Version:** 1.3.0 **Last Updated:** June 17, 2026 **Audience:** Patient UI
> Developers

This document describes the Messenger API endpoints used by authenticated
patients to fetch provider chat threads and send chat messages.

---

## Table of Contents

1. [Overview](#overview)
2. [Authentication](#authentication)
3. [Base Configuration](#base-configuration)
4. [Endpoints](#endpoints)
   - [List Provider Chat Contexts](#list-provider-chat-contexts)
   - [Get Provider Chat Status](#get-provider-chat-status)
   - [Get Provider Chat Thread](#get-provider-chat-thread)
   - [Upload Provider Chat File](#upload-provider-chat-file)
   - [Send Provider Chat Message](#send-provider-chat-message)
   - [Download Telegra Chat File](#download-telegra-chat-file)
   - [Mark Provider Chat Message Read](#mark-provider-chat-message-read)
   - [Get Telegra Chat Threads](#get-telegra-chat-threads)
   - [Send Telegra Chat Message](#send-telegra-chat-message)
   - [Get MDI Patient Messages](#get-mdi-patient-messages)
   - [Get MDI Message Status](#get-mdi-message-status)
   - [Send MDI Patient Message](#send-mdi-patient-message)
   - [Mark MDI Message Read](#mark-mdi-message-read)
5. [Data Model](#data-model)
6. [Error Handling](#error-handling)
7. [Rate Limiting](#rate-limiting)
8. [Security Considerations](#security-considerations)
9. [Migration Notes](#migration-notes)
10. [Changelog](#changelog)

---

## Overview

The Messenger API is an authenticated Edge Function that brokers patient
messaging with provider platforms such as Telegra and MDI.

### Key Concepts

| Concept                   | Description                                                                                                                                                                        |
| ------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Messenger Function**    | Edge Function at `functions/v1/messenger-api` that exposes messaging endpoints                                                                                                     |
| **Telegra Integration**   | Tenant provider-platform integration (`integration_key` = `telegramd`/`telegra`) with `url` plus primary `username/password` settings, and optional legacy `access_token` fallback |
| **MDI Integration**       | Tenant provider-platform integration (`integration_key` = `md_integrations`) with `backend_url`, `client_id`, and `client_secret` settings                                         |
| **Provider Patient ID**   | External Telegra patient identifier from `patient_provider_platform_links.provider_patient_id`                                                                                     |
| **MDI Patient Thread**    | One MDI patient-channel message thread per linked patient                                                                                                                          |
| **Provider Chat Context** | Opaque plan/order/provider selector returned by backend for patient-provider chat routing                                                                                          |
| **Chat Type**             | Conversation channel selector (`clinical` or `support`)                                                                                                                            |

---

## Authentication

All endpoints require a valid `Authorization: Bearer <token>` header. The
authenticated user is resolved to a patient record via `patients.auth_user_id`.

### Base URL

```txt
VITE_SUPABASE_URL/functions/v1/messenger-api
```

### Required Headers

| Header          | Description         | Required          |
| --------------- | ------------------- | ----------------- |
| `apikey`        | Supabase anon key   | Yes               |
| `Authorization` | Bearer access token | Yes               |
| `Content-Type`  | `application/json`  | For POST requests |

---

## Endpoints

### List Provider Chat Contexts

Returns the authenticated patient's provider-chat orders and backing plan
contexts. The backend owns chat eligibility and selects the provider of the most
recent order/case linked to each non-finished plan, regardless of the selected
order's current status.

```http
GET /functions/v1/messenger-api/provider-chat/contexts
Authorization: Bearer <patient_access_token>
apikey: <supabase-anon-key>
```

**Behavior:**

- Validates bearer token and resolves an active patient.
- Lists patient subscriptions that are not fully finished/cancelled.
- For each non-finished plan, inspects linked orders regardless of order status.
- Plan contexts are sorted from most recent to oldest by the selected order's
  `updated_at`, falling back to `created_at`.
- If multiple orders/providers exist inside a plan, chooses the most recent
  order with a supported provider chat link.
- `eligible_orders` is the frontend order-selection list. The name is kept for
  compatibility; order status is returned for display/context and does not hide
  the latest order of an active plan.
- Returns unavailable contexts with quiet reason codes instead of forcing the
  frontend to infer from plan/order state.
- `chat_context_id` is opaque. Frontend must pass it back unchanged.

**Response:** `200 OK`

```json
{
  "data": {
    "available": true,
    "total_eligible_orders": 1,
    "eligible_orders": [
      {
        "chat_context_id": "eyJ2ZXJzaW9uIjoxLCJwbGFuX2lkIjoiLi4uIn0",
        "plan_id": "subscription-uuid",
        "plan_title": "Weight Management",
        "plan_status": "active",
        "order_id": "order-uuid",
        "order_number": "ORD-1001",
        "order_status": {
          "key": "provider_review_pending",
          "label": "Under Medical Review",
          "is_terminal": false
        },
        "provider_id": "tenant-integration-uuid",
        "provider_order_id": "provider-case-id",
        "provider_chat_id": "provider-patient-id",
        "provider": {
          "id": "tenant-integration-uuid",
          "integration_key": "md_integrations",
          "name": "MDI",
          "chat_id": "provider-patient-id"
        },
        "medication_or_product_name": "Weight Management",
        "product": {
          "id": "product-uuid",
          "name": "Weight Management"
        },
        "created_at": "2026-06-15T12:00:00Z",
        "updated_at": "2026-06-16T12:00:00Z",
        "capabilities": {
          "attachments": true,
          "read_receipts": true
        },
        "summary": {
          "unread_count": 0,
          "last_message_at": null,
          "last_message_preview": null
        }
      }
    ],
    "total_contexts": 2,
    "contexts": [
      {
        "chat_context_id": "eyJ2ZXJzaW9uIjoxLCJwbGFuX2lkIjoiLi4uIn0",
        "plan_id": "subscription-uuid",
        "plan_title": "Weight Management",
        "plan_status": "active",
        "is_fully_finished": false,
        "chat_available": true,
        "unavailable_reason": null,
        "selected_order_id": "order-uuid",
        "provider_order_id": "provider-case-id",
        "provider": {
          "id": "tenant-integration-uuid",
          "integration_key": "md_integrations",
          "name": "MDI",
          "chat_id": "provider-patient-id"
        },
        "selected_order": {
          "id": "order-uuid",
          "order_number": "ORD-1001",
          "status": {
            "key": "provider_review_pending",
            "label": "Under Medical Review",
            "is_terminal": false
          },
          "provider_id": "tenant-integration-uuid",
          "provider_order_id": "provider-case-id",
          "provider_chat_id": "provider-patient-id",
          "medication_or_product_name": "Weight Management",
          "product": {
            "id": "product-uuid",
            "name": "Weight Management"
          },
          "created_at": "2026-06-15T12:00:00Z",
          "updated_at": "2026-06-16T12:00:00Z"
        },
        "summary": {
          "unread_count": 0,
          "last_message_at": null,
          "last_message_preview": null
        },
        "capabilities": {
          "attachments": true,
          "read_receipts": true
        }
      }
    ]
  }
}
```

**Unavailable reason codes:**

| Code                            | Meaning                                                   |
| ------------------------------- | --------------------------------------------------------- |
| `order_missing`                 | Plan has no linked order                                  |
| `provider_order_missing`        | No provider case/order link has been created yet          |
| `provider_integration_disabled` | The linked provider integration is disabled               |
| `provider_patient_missing`      | Patient has no provider patient link for that integration |
| `provider_chat_unsupported`     | Provider exists but chat proxy is not implemented yet     |

---

### Get Provider Chat Status

Provider-agnostic badge/status endpoint for a selected plan chat context.

```http
GET /functions/v1/messenger-api/provider-chat/{chat_context_id}/status
Authorization: Bearer <patient_access_token>
apikey: <supabase-anon-key>
```

For Telegra, optional `chatType=clinical|support` is accepted and defaults to
`clinical`. MDI always uses the patient channel.

**Response:** `200 OK`

```json
{
  "data": {
    "chat_context": {
      "chat_context_id": "opaque-context-id",
      "plan_id": "subscription-uuid",
      "chat_available": true
    },
    "provider_platform": {
      "name": "MDI",
      "integration_key": "md_integrations"
    },
    "ids": {
      "patient_id": "patient-uuid",
      "tenant_id": "tenant-uuid",
      "tenant_integration_id": "tenant-integration-uuid",
      "provider_patient_id": "provider-patient-id"
    },
    "summary": {
      "total_messages": 2,
      "unread_count": 1,
      "latest_message_id": "message-uuid",
      "latest_message_at": "2026-06-15T12:00:00.000000Z"
    }
  }
}
```

---

### Get Provider Chat Thread

Provider-agnostic full thread endpoint for a selected plan chat context.

```http
GET /functions/v1/messenger-api/provider-chat/{chat_context_id}/thread
Authorization: Bearer <patient_access_token>
apikey: <supabase-anon-key>
```

**Behavior:**

- Revalidates the opaque context against current patient, plan, order, and
  provider links.
- Proxies to MDI or Telegra based on the selected order/provider.
- Telegra thread response includes `chats`.
- MDI thread response includes normalized `messages` and `pagination`.

---

### Upload Provider Chat File

Uploads one staged file for a selected provider chat context. Currently this
multipart staging endpoint is for MDI contexts only. Telegra sends files inline
through [Send Provider Chat Message](#send-provider-chat-message) using the
`file` JSON object.

```http
POST /functions/v1/messenger-api/provider-chat/{chat_context_id}/files
Authorization: Bearer <patient_access_token>
Content-Type: multipart/form-data
apikey: <supabase-anon-key>

form-data:
  file: <binary file>
  name: "Lab result.pdf"
  type: "lab-result"
```

**Form fields:**

| Field  | Required | Notes                                                        |
| ------ | -------- | ------------------------------------------------------------ |
| `file` | Yes      | Binary file                                                  |
| `name` | No       | Display/file name. Defaults to uploaded filename or `upload` |
| `type` | Yes      | MDI file type/category                                       |

**Supported MDI file types:**

`document`, `review`, `other`, `insurance-policy`, `contract`, `driver-license`,
`lab-result`, `photo`, `av-video`, `full-body-photo`, `back-photo`,
`face-photo`, `avatar-photo`, `ipledge-document`, `auth-form`.

**Provider size limits enforced by the API:**

| MIME family | Max size |
| ----------- | -------- |
| `image/*`   | 25 MB    |
| `video/*`   | 140 MB   |
| other       | 16 MB    |

**Response:** `200 OK`

```json
{
  "message": "File uploaded successfully",
  "data": {
    "chat_context": {
      "chat_context_id": "opaque-context-id"
    },
    "file": {
      "id": "mdi-file-uuid",
      "name": "Lab result.pdf",
      "type": "lab-result",
      "size": 123456,
      "mime_type": "application/pdf"
    }
  }
}
```

Use the returned `file.id` in `attachments` or `files` when sending the provider
chat message.

If the selected provider does not support multipart staged attachments, the API
returns `ATTACHMENTS_NOT_SUPPORTED`.

---

### Send Provider Chat Message

Provider-agnostic message-send endpoint for a selected plan chat context.

```http
POST /functions/v1/messenger-api/provider-chat/{chat_context_id}/messages
Authorization: Bearer <patient_access_token>
Content-Type: application/json
apikey: <supabase-anon-key>

{
  "message": "Hello, I have a question about my treatment.",
  "attachments": [{ "id": "mdi-file-uuid" }],
  "file": {
    "name": "Lab result",
    "ext": "pdf",
    "base64Data": "JVBERi0x..."
  },
  "conversationID": "pcv::telegra-conversation-id",
  "channelType": "clinical"
}
```

**Request body:**

| Field                                         | Required                                                                  | Notes                                                          |
| --------------------------------------------- | ------------------------------------------------------------------------- | -------------------------------------------------------------- |
| `message` / `text`                            | Required unless sending a Telegra `file`; optional for MDI with files     | Message body                                                   |
| `file` / `attachment`                         | Telegra only; required when sending a Telegra file instead of text        | Object with `name`, optional `ext`, and raw `base64Data`       |
| `attachments` / `files`                       | MDI only                                                                  | Array of uploaded file references: `{ "id": "mdi-file-uuid" }` |
| `conversationID` / `conversationId`           | Telegra only                                                              | Telegra conversation id from thread response                   |
| `channelType`                                 | Telegra only                                                              | `clinical` or `support`, defaults to `clinical`                |
| `reference_message_id` / `referenceMessageId` | MDI only                                                                  | Optional referenced MDI message                                |

For MDI, upload files first with `POST /provider-chat/{chat_context_id}/files`,
then include the returned `file.id` values in `attachments` or `files`.

For Telegra, send either `message` or `file`, not both. `file.base64Data` must
be raw base64 without a `data:<mime>;base64,` header. The backend normalizes
padding/whitespace, sanitizes the file name, and enforces a 25 MB decoded file
limit before proxying to Telegra as:

```json
{
  "file": {
    "name": "Lab result",
    "ext": "pdf",
    "base64Data": "JVBERi0x..."
  },
  "sender": "patient",
  "channelType": "clinical"
}
```

---

### Download Telegra Chat File

Downloads a Telegra/Sendbird file referenced by a Telegra chat message. Use
this for messages whose Telegra payload has `type: "FILE"` and `file.url`.
Do not open the Sendbird/S3 `file.url` directly from the frontend.

```http
GET /functions/v1/messenger-api/provider-chat/{chat_context_id}/files/telegra?url={encoded_file_url}
Authorization: Bearer <patient_access_token>
apikey: <supabase-anon-key>
```

The response is the proxied binary file with the provider `Content-Type` when
available.

---

### Mark Provider Chat Message Read

Provider-agnostic read receipt endpoint. Currently supported for MDI contexts
only.

```http
POST /functions/v1/messenger-api/provider-chat/{chat_context_id}/messages/{message_id}/read
Authorization: Bearer <patient_access_token>
apikey: <supabase-anon-key>
```

If the selected context provider does not support read receipts, the API returns
`READ_RECEIPTS_NOT_SUPPORTED`.

---

### Get Telegra Chat Threads

Fetches patient chat threads from Telegra for the authenticated patient.

```http
GET /functions/v1/messenger-api/telegra-clinical-chat?chatType=clinical
Authorization: Bearer <patient_access_token>
apikey: <supabase-anon-key>
```

**Query Parameters:**

| Parameter  | Type   | Required | Description                     |
| ---------- | ------ | -------- | ------------------------------- |
| `chatType` | string | Yes      | Must be `clinical` or `support` |

**Behavior:**

- Validates bearer token and resolves active patient.
- Resolves the patient's enabled Telegra integration and linked provider patient
  ID.
- Calls Telegra endpoint:
  `GET /patientConversations/getByPatient/{providerPatientId}?channelType={chatType}`.
- If Telegra returns a single `channel` object, normalizes to
  `chats: [channel]`.
- If `participantIdentifier` exists in top-level/body/data payload, injects it
  into each chat object when missing.
- For `chatType=clinical`, filters Telegra messages where the normalized `type`
  value (trimmed, uppercased, and stripped of non-alphanumeric characters)
  equals `"ADMM"` from each chat `messages` array, including the first message
  in the thread when applicable.

**Response:** `200 OK`

```json
{
  "data": {
    "provider_platform": {
      "name": "TelegraMD",
      "integration_key": "telegramd"
    },
    "ids": {
      "patient_id": "patient-uuid",
      "tenant_id": "tenant-uuid",
      "tenant_integration_id": "tenant-integration-uuid",
      "provider_patient_id": "pat::12345"
    },
    "chat_type": "clinical",
    "total_chats": 1,
    "chats": [
      {
        "participantIdentifier": "pcv::be056e0d-3cce-46c7-8ef7-7318f9386d14",
        "...telegra channel payload...": "..."
      }
    ]
  }
}
```

---

### Send Telegra Chat Message

Sends a text message or one file to a Telegra patient conversation for the
authenticated patient.

```http
POST /functions/v1/messenger-api/telegra-clinical-chat
Authorization: Bearer <patient_access_token>
Content-Type: application/json
apikey: <supabase-anon-key>

{
  "conversationID": "pcv::be056e0d-3cce-46c7-8ef7-7318f9386d14",
  "channelType": "clinical",
  "message": "Hello team, I have a question about my treatment.",
  "file": {
    "name": "Lab result",
    "ext": "pdf",
    "base64Data": "JVBERi0x..."
  }
}
```

**Request Body:**

| Field            | Type   | Required                           | Description                                            |
| ---------------- | ------ | ---------------------------------- | ------------------------------------------------------ |
| `conversationID` | string | Yes                                | Telegra conversation ID                                |
| `channelType`    | string | Yes                                | Must be `clinical` or `support`                        |
| `message`        | string | Required unless `file` is present  | Message text to send                                   |
| `file`           | object | Required when sending a file       | `{ name, ext?, base64Data }` with raw base64 data only |

`conversationId` is also accepted as an alias for `conversationID`.
Send either `message` or `file`, not both.

**Behavior:**

- Validates bearer token and resolves active patient.
- Resolves enabled Telegra integration and linked provider patient ID.
- Calls Telegra endpoint:
  `POST /patientConversations/{conversationID}/sendMessage`.
- Sends either a text body with `message` or a file body with
  `file: { name, ext?, base64Data }`, plus `sender: "patient"` and
  `channelType`.

**Response:** `200 OK`

```json
{
  "message": "Message sent successfully",
  "data": {
    "provider_platform": {
      "name": "TelegraMD",
      "integration_key": "telegramd"
    },
    "ids": {
      "patient_id": "patient-uuid",
      "tenant_id": "tenant-uuid",
      "tenant_integration_id": "tenant-integration-uuid",
      "provider_patient_id": "pat::12345"
    },
    "conversation_id": "pcv::be056e0d-3cce-46c7-8ef7-7318f9386d14",
    "channel_type": "clinical",
    "telegra_response": {
      "...raw telegra response...": "..."
    }
  }
}
```

---

### Get MDI Patient Messages

Fetches the authenticated patient's MDI patient-channel messages.

```http
GET /functions/v1/messenger-api/mdi-patient-chat
Authorization: Bearer <patient_access_token>
apikey: <supabase-anon-key>
```

**Behavior:**

- Validates bearer token and resolves active patient.
- Resolves the patient's enabled MDI integration and linked MDI patient ID.
- Calls MDI endpoint:
  `GET /v1/partner/patients/{providerPatientId}/messages?channel=patient`.
- Normalizes MDI messages for frontend rendering.
- Computes `summary.unread_count` for inbound clinician/support messages whose
  `read_at` value is empty.

**Response:** `200 OK`

```json
{
  "data": {
    "provider_platform": {
      "name": "MDI",
      "integration_key": "md_integrations"
    },
    "ids": {
      "patient_id": "patient-uuid",
      "tenant_id": "tenant-uuid",
      "tenant_integration_id": "tenant-integration-uuid",
      "provider_patient_id": "mdi-patient-uuid"
    },
    "summary": {
      "total_messages": 2,
      "unread_count": 1,
      "latest_message_id": "message-uuid",
      "latest_message_at": "2026-06-11T10:00:00.000000Z"
    },
    "pagination": {
      "meta": null,
      "links": null
    },
    "messages": [
      {
        "id": "message-uuid",
        "patient_id": "mdi-patient-uuid",
        "channel": "patient",
        "text": "Please confirm this dose.",
        "sender_role": "clinician",
        "sender_type": "App\\Models\\Clinician",
        "sender_id": "clinician-uuid",
        "sender_name": "Ada Lovelace",
        "direction": "inbound",
        "read_at": null,
        "created_at": "2026-06-11T10:00:00.000000Z",
        "updated_at": "2026-06-11T10:00:00.000000Z",
        "files": [],
        "is_unread": true
      }
    ]
  }
}
```

---

### Get MDI Message Status

Fetches only the summary needed for a chat badge/icon. The frontend should poll
this endpoint periodically when the app is active.

```http
GET /functions/v1/messenger-api/mdi-patient-chat/status
Authorization: Bearer <patient_access_token>
apikey: <supabase-anon-key>
```

**Recommended polling:**

- Poll every 30-60 seconds while the patient is signed in.
- Poll the full message list every 15-30 seconds while the chat view is open.
- Do not mark messages as read from status polling; mark them read only when the
  chat thread is visible.

**Response:** `200 OK`

```json
{
  "data": {
    "provider_platform": {
      "name": "MDI",
      "integration_key": "md_integrations"
    },
    "ids": {
      "patient_id": "patient-uuid",
      "tenant_id": "tenant-uuid",
      "tenant_integration_id": "tenant-integration-uuid",
      "provider_patient_id": "mdi-patient-uuid"
    },
    "summary": {
      "total_messages": 2,
      "unread_count": 1,
      "latest_message_id": "message-uuid",
      "latest_message_at": "2026-06-11T10:00:00.000000Z"
    }
  }
}
```

---

### Send MDI Patient Message

Sends a patient-channel message to MDI.

```http
POST /functions/v1/messenger-api/mdi-patient-chat
Authorization: Bearer <patient_access_token>
Content-Type: application/json
apikey: <supabase-anon-key>

{
  "message": "Hello team, I have a question about my treatment."
}
```

**Request Body:**

| Field                  | Type   | Required | Description                              |
| ---------------------- | ------ | -------- | ---------------------------------------- |
| `message`              | string | Yes      | Message text to send                     |
| `reference_message_id` | string | No       | Optional MDI message id being referenced |

`text` is also accepted as an alias for `message`.

**Behavior:**

- Sends MDI request body with `channel = "patient"` and `text = message`.
- Calls MDI endpoint:
  `POST /v1/partner/patients/{providerPatientId}/messages?channel=patient`.

**Response:** `200 OK`

```json
{
  "message": "Message sent successfully",
  "data": {
    "provider_platform": {
      "name": "MDI",
      "integration_key": "md_integrations"
    },
    "ids": {
      "patient_id": "patient-uuid",
      "tenant_id": "tenant-uuid",
      "tenant_integration_id": "tenant-integration-uuid",
      "provider_patient_id": "mdi-patient-uuid"
    },
    "message": {
      "id": "message-uuid",
      "direction": "outbound",
      "sender_role": "patient"
    }
  }
}
```

---

### Mark MDI Message Read

Marks an MDI message as read.

```http
POST /functions/v1/messenger-api/mdi-patient-chat/{message_id}/read
Authorization: Bearer <patient_access_token>
apikey: <supabase-anon-key>
```

**Behavior:**

- Calls MDI endpoint:
  `POST /v1/partner/patients/{providerPatientId}/messages/{messageId}/read?channel=patient`.
- The frontend should call this only for visible inbound messages.

**Response:** `200 OK`

```json
{
  "message": "Message marked as read",
  "data": {
    "message": {
      "id": "message-uuid",
      "read_at": "2026-06-11 10:05:00",
      "is_unread": false
    }
  }
}
```

---

## Data Model

The Messenger API reads from:

- `patients` (authenticated patient resolution and `access_status`)
- `subscriptions` (patient plan contexts)
- `orders` (most recent provider-linked order inside each plan)
- `order_provider_platform_links` (internal order to provider case/order
  mapping)
- `patient_provider_platform_links` (patient/provider linkage and provider IDs)
- `tenant_integrations` (provider integration config and credentials)
- `tenant_integration_auth_tokens` (cached Telegra/MDI provider tokens)

The API does not persist chat records in local tables; message and thread state
are sourced from the provider platform.

---

## Error Handling

Errors follow a consistent structure:

```json
{
  "error": {
    "code": "UNAUTHORIZED",
    "message": "Authorization header required"
  }
}
```

**Common Error Codes:**

| HTTP Status | Code                               | Description                                                    |
| ----------- | ---------------------------------- | -------------------------------------------------------------- |
| 400         | `INVALID_CHAT_TYPE`                | `chatType`/`channelType` is not `clinical` or `support`        |
| 400         | `INVALID_JSON`                     | Request body is not valid JSON                                 |
| 400         | `INVALID_MULTIPART`                | File upload request is not valid multipart/form-data           |
| 400         | `MISSING_FIELDS`                   | Required fields are missing                                    |
| 400         | `INVALID_ATTACHMENTS`              | Attachment references must include uploaded file IDs           |
| 400         | `INVALID_FILE`                     | Telegra file payload is malformed or missing required fields   |
| 400         | `INVALID_FILE_BASE64`              | Telegra file base64 is invalid or includes a MIME header       |
| 400         | `INVALID_FILE_TYPE`                | Uploaded file type/category is not supported by MDI            |
| 400         | `FILE_TOO_LARGE`                   | Uploaded file exceeds the provider size limit                  |
| 400         | `FILE_DOWNLOAD_NOT_SUPPORTED`      | Selected provider chat does not support file download proxying |
| 400         | `INVALID_MESSAGE_FILE_COMBINATION` | Telegra send request included both `message` and `file`        |
| 401         | `UNAUTHORIZED`                     | Missing, invalid, or expired bearer token                      |
| 403         | `ACCOUNT_INACTIVE`                 | Patient account is not active                                  |
| 404         | `NOT_FOUND`                        | Patient profile not found                                      |
| 404         | `TELEGRA_NOT_CONFIGURED`           | No enabled Telegra integration found for patient               |
| 404         | `TELEGRA_PATIENT_ID_MISSING`       | Patient has no linked Telegra patient ID                       |
| 404         | `MDI_NOT_CONFIGURED`               | No enabled MDI integration found for patient                   |
| 404         | `MDI_PATIENT_ID_MISSING`           | Patient has no linked MDI patient ID                           |
| 400         | `INVALID_CHAT_CONTEXT`             | Provider chat context id is malformed                          |
| 400         | `ATTACHMENTS_NOT_SUPPORTED`        | Selected provider chat does not support outbound attachments   |
| 400         | `READ_RECEIPTS_NOT_SUPPORTED`      | Selected provider chat does not support read receipts          |
| 404         | `PROVIDER_CHAT_UNAVAILABLE`        | Plan/order/provider context is no longer chat-available        |
| 404         | `PROVIDER_CHAT_UNSUPPORTED`        | Selected provider integration has no chat proxy implementation |
| 500         | `FETCH_ERROR`                      | Internal read error while resolving patient/provider data      |
| 500         | `TELEGRA_CONFIG_MISSING`           | Telegra URL/access token not configured                        |
| 500         | `MDI_CONFIG_MISSING`               | MDI backend URL or credentials are not configured              |
| 502         | `TELEGRA_REQUEST_FAILED`           | Telegra endpoint is unreachable                                |
| 502         | `TELEGRA_API_ERROR`                | Telegra returned a non-success response                        |
| 502         | `MDI_REQUEST_FAILED`               | MDI endpoint is unreachable                                    |
| 502         | `MDI_API_ERROR`                    | MDI returned a non-success response                            |
| 502         | `MDI_FILE_UPLOAD_RESPONSE_INVALID` | MDI file upload response did not include a file ID             |

---

## Rate Limiting

Requests are limited to **100 per minute** per client IP.

---

## Security Considerations

- All routes require authenticated patient access tokens.
- Patient must have `access_status = active`.
- Telegra credentials are read from tenant integration settings via service-role
  context.
- The backend authenticates with Telegra using `username/password` via
  `/auth/client` when available.
- A stored `access_token` is only used as a legacy fallback during the
  transition.
- MDI credentials are read from tenant integration settings via service-role
  context.
- The backend authenticates with MDI using `client_id` and `client_secret`,
  reusing cached access tokens when valid.
- Telegra routes only allow chat channels `clinical` and `support`.
- MDI patient messaging always uses `channel = "patient"`.
- MDI file uploads are proxied through Messenger API; frontend must not call MDI
  directly or handle MDI bearer tokens.

---

## Migration Notes

As of **March 25, 2026**, chat-thread endpoints moved from Patient API to
Messenger API.

- Previous: `GET/POST /functions/v1/patient-api/chat-threads`
- Current: `GET/POST /functions/v1/messenger-api/telegra-clinical-chat`

Legacy `patient-api` route now returns `410` with code `MOVED_TO_MESSENGER_API`.

---

## Changelog

| Version | Date     | Changes                                                                                                                                   |
| ------- | -------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| 1.3.0   | Jun 2026 | Added Telegra inline file sending, Telegra file download proxying, and Telegra attachment capability metadata                              |
| 1.2.0   | Jun 2026 | Added provider-agnostic plan chat contexts, context-scoped status/thread/send/read endpoints, and MDI file upload/attachment send support |
| 1.1.0   | Jun 2026 | Added MDI patient-channel message list, badge status polling, send, get, and mark-read endpoints                                          |
| 1.0.0   | Mar 2026 | Initial Messenger API documentation covering Telegra chat thread retrieval and message sending endpoints                                  |
