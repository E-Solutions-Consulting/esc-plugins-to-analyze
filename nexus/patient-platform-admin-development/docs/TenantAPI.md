# Tenant Info API

Public API endpoint for retrieving tenant metadata and branding information.
This endpoint is designed for external applications (e.g., Patient UI) that need
to display tenant-specific branding without authentication.

Related sequence diagram:
[Patient Sign Up Flow](./SequenceDiagrams.md#patient-sign-up-flow).

## Base URL

```
VITE_SUPABASE_URL/functions/v1/tenant-info
```

Email template webhook:

```
VITE_SUPABASE_URL/functions/v1/tenant-email-template
```

## Authentication

**None required** - This is a public endpoint that does not require
authentication.

## Endpoints

### GET /tenant-info

Retrieve public information about a tenant including name, logo, and branding
colors.

#### Request Parameters

Tenant identification can be provided via **query parameters** or **headers**:

| Parameter   | Type   | Location                              | Description                     |
| ----------- | ------ | ------------------------------------- | ------------------------------- |
| `slug`      | string | Query param or `x-tenant-slug` header | Tenant's unique slug identifier |
| `tenant_id` | string | Query param or `x-tenant-id` header   | Tenant's UUID                   |

> **Note:** At least one identifier (`slug` or `tenant_id`) must be provided.

#### Response Schema

```typescript
interface TenantInfoResponse {
  id: string; // UUID of the tenant
  name: string; // Display name of the tenant
  slug: string; // URL-friendly identifier
  logo_url: string | null; // URL to tenant's logo image
  primary_color: string | null; // Primary brand color (hex)
  secondary_color: string | null; // Secondary brand color (hex)
  accent_color: string | null; // Accent brand color (hex)
  feature_flags: Record<string, boolean>; // Map of feature flag keys to their enabled status
  integrations: {
    intercom?: {
      app_id: string;
      help_center_url?: string;
    };
  };
}
```

#### Success Response

**Status Code:** `200 OK`

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "name": "Acme Health",
  "slug": "acme-health",
  "logo_url": "https://example.com/storage/v1/object/public/tenant-logos/acme-logo.png",
  "primary_color": "#2563eb",
  "secondary_color": "#1e40af",
  "accent_color": "#f59e0b",
  "feature_flags": {
    "enable_subscriptions": true,
    "enable_ai_companion_mode": false,
    "education_content": true,
    "show_medication_details": true
  },
  "integrations": {
    "intercom": {
      "app_id": "app_def456",
      "help_center_url": "https://intercom.help/acme-health"
    }
  }
}
```

#### Error Responses

**Missing Identifier (400 Bad Request)**

```json
{
  "error": "Missing tenant identifier",
  "message": "Provide 'slug' or 'tenant_id' as query parameter or header"
}
```

**Tenant Not Found (404 Not Found)**

```json
{
  "error": "Tenant not found"
}
```

**Server Error (500 Internal Server Error)**

```json
{
  "error": "Internal server error"
}
```

### GET /tenant-email-template

Retrieve the tenant-wide HTML wrapper used for outbound transactional emails.

#### Request Parameters

Tenant identification can be provided via **query parameters** or **headers**:

| Parameter   | Type   | Location                              | Description                     |
| ----------- | ------ | ------------------------------------- | ------------------------------- |
| `slug`      | string | Query param or `x-tenant-slug` header | Tenant's unique slug identifier |
| `tenant_id` | string | Query param or `x-tenant-id` header   | Tenant's UUID                   |

> **Note:** At least one identifier (`slug` or `tenant_id`) must be provided.

#### Response Schema

```typescript
interface TenantEmailTemplateResponse {
  tenant_id: string;
  slug: string;
  email_template_html: string | null;
  web_app_base_url: string | null;
}
```

`email_template_html` is sourced from
`tenant_settings.metadata.email_template_html`. It may be `null` when the tenant
has not configured a custom template.

`web_app_base_url` is sourced from
`tenant_settings.metadata.mobile_apps.web_app.base_url`. It may be `null` when
the tenant has not configured a web app base URL.

#### Success Response

**Status Code:** `200 OK`

```json
{
  "tenant_id": "550e8400-e29b-41d4-a716-446655440000",
  "slug": "acme-health",
  "email_template_html": "<div>{{EMAIL_TITLE}}</div><main>{{EMAIL_CONTENT}}</main>",
  "web_app_base_url": "https://app.acme-health.com/"
}
```

#### Error Responses

This endpoint returns the same missing-identifier, not-found,
method-not-allowed, and server-error response shapes as `GET /tenant-info`.

## Usage Examples

### Using Query Parameters

```bash
# By slug
curl "VITE_SUPABASE_URL/functions/v1/tenant-info?slug=acme-health"

# By tenant ID
curl "VITE_SUPABASE_URL/functions/v1/tenant-info?tenant_id=550e8400-e29b-41d4-a716-446655440000"

# Email template by slug
curl "VITE_SUPABASE_URL/functions/v1/tenant-email-template?slug=acme-health"
```

### Using Headers

```bash
curl "VITE_SUPABASE_URL/functions/v1/tenant-info" \
  -H "x-tenant-slug: acme-health"
```

### JavaScript/TypeScript

```typescript
// Fetch tenant info by slug
async function getTenantInfo(slug: string) {
  const response = await fetch(
    `VITE_SUPABASE_URL/functions/v1/tenant-info?slug=${
      encodeURIComponent(slug)
    }`,
  );

  if (!response.ok) {
    throw new Error("Failed to fetch tenant info");
  }

  return response.json();
}

// Usage
const tenant = await getTenantInfo("acme-health");
console.log(tenant.name); // "Acme Health"
console.log(tenant.logo_url); // Logo URL or null
```

### React Example

{% raw %}

```tsx
import { useEffect, useState } from "react";

interface TenantInfo {
  id: string;
  name: string;
  slug: string;
  logo_url: string | null;
  primary_color: string | null;
  secondary_color: string | null;
  accent_color: string | null;
  feature_flags: Record<string, boolean>;
  integrations: {
    intercom?: {
      app_id: string;
      help_center_url?: string;
    };
  };
}

function useTenantInfo(slug: string) {
  const [tenant, setTenant] = useState<TenantInfo | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    async function fetchTenant() {
      try {
        const response = await fetch(
          `VITE_SUPABASE_URL/functions/v1/tenant-info?slug=${slug}`,
        );

        if (!response.ok) {
          throw new Error("Tenant not found");
        }

        const data = await response.json();
        setTenant(data);
      } catch (err) {
        setError(err instanceof Error ? err.message : "Unknown error");
      } finally {
        setLoading(false);
      }
    }

    fetchTenant();
  }, [slug]);

  return { tenant, loading, error };
}

// Usage in component
function TenantHeader({ slug }: { slug: string }) {
  const { tenant, loading, error } = useTenantInfo(slug);

  if (loading) return <div>Loading...</div>;
  if (error) return <div>Error: {error}</div>;
  if (!tenant) return null;

  return (
    <header
      style={{ "--primary": tenant.primary_color } as React.CSSProperties}
    >
      {tenant.logo_url && <img src={tenant.logo_url} alt={tenant.name} />}
      <h1>{tenant.name}</h1>
    </header>
  );
}
```

{% endraw %}

## CORS

This endpoint supports Cross-Origin Resource Sharing (CORS) for browser-based
applications:

- **Allowed Origins:** values matched by `CORS_ALLOWED_ORIGINS` in the shared
  edge-function CORS helper. For deployed environments, this must be configured
  as a Supabase secret for the target project ref.
- **Current Example Value:**
  `http://localhost:*,http://127.0.0.1:*,https://*.lovableproject.com,https://*.lovable.app`
- **Allowed Methods:** `GET`, `OPTIONS`
- **Allowed Headers:** `authorization`, `x-client-info`, `apikey`,
  `content-type`, `x-tenant-slug`, `x-tenant-id`

## Notes

- Only **active** tenants are returned. Inactive, suspended, or pending tenants
  will return a 404 error.
- Branding colors may be `null` if not configured for the tenant.
- The logo URL points to a publicly accessible storage bucket.
- Feature flags return only **active** flags. The value represents the effective
  state for the tenant (tenant override if set, otherwise the platform default).
- `feature_flags.enable_ai_companion_mode` is included after Platform Admin
  activates the flag; each tenant can then enable or disable it via tenant-level
  override.
- `feature_flags.education_content` controls patient education content visibility
  in Patient UI. It is created as an active flag with a disabled platform default
  and an enabled tenant override for the `allia` tenant when present.
- `integrations.intercom` is returned only when the tenant has an enabled
  Intercom integration with `app_id` configured.
- `integrations.intercom.help_center_url` is returned only when the tenant's
  Intercom integration includes a non-empty `help_center_url` setting.

## Related Documentation

- [Patient API](./PatientAPI.md) - API for patient-facing product and category
  data
- [Architecture](./Architecture.md) - System architecture overview
