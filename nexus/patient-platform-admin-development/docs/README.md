# Foundation Canvas Documentation

This folder contains the comprehensive development guidelines for this project. All AI agents and developers should follow these standards.

## Documentation Files

| Document | Purpose |
|----------|---------|
| [AI.md](./AI.md) | AI integration patterns, ML providers, agentic systems, voice AI, knowledge bases, N8N workflows |
| [Architecture.md](./Architecture.md) | Core architecture, environment/branch strategy, CI/CD, service patterns, deployment |
| [Backend.md](./Backend.md) | Backend structure, authentication, database, Edge Functions, API design, security |
| [Design.md](./Design.md) | Design tokens, color system, typography, spacing, component library, accessibility |
| [Frontend.md](./Frontend.md) | Component architecture, state management, routing, styling, forms, data fetching |
| [SystemArchitecture.md](./SystemArchitecture.md) | RTDH integration architecture, environment references, deployments, and operational documentation |
| [SequenceDiagrams.md](./SequenceDiagrams.md) | Sequence diagrams for patient signup and order flows across Patient UI, Supabase Edge Functions, Stripe, RTDH, provider platforms, and pharmacy systems |
| [ProviderPlatformBridgeAPI.md](./ProviderPlatformBridgeAPI.md) | Provider-platform bridge API for fetching external questionnaire schemas for an order |
| [HealthTrackingAPI.md](./HealthTrackingAPI.md) | Patient health tracking API for shot, weight, body measurement, mood, activity, symptom, and injection site workflows |
| [MedicationAPI.md](./MedicationAPI.md) | Patient medication API, product-medication eligibility, and medication capability keys such as `weight_tracker` and `body_measurement` |
| [QAAPI.md](./QAAPI.md) | QA-only edge functions for creating synthetic Stripe-backed orders and approving Telegra order prescriptions in non-live environments |
| [MessengerAPI.md](./MessengerAPI.md) | Messenger API for Telegra chat threads and MDI patient message sending |
| [RTDHWebhookAPI.md](./RTDHWebhookAPI.md) | RTDH webhook ingestion API for normalized cross-system order event payloads |
| [StripeIntegrationRequirements.md](./StripeIntegrationRequirements.md) | Stripe integration setup checklist: required keys, webhook endpoint, and required events |
| [AuthMethodsSetup.md](./AuthMethodsSetup.md) | Sign-in method setup (email/password, passwordless OTP, Google/Apple, passkeys/biometrics): dashboard + cloud config and how each flow resolves to a tenant session |
| [TelegraIntegrationRequirements.md](./TelegraIntegrationRequirements.md) | TelegraMD integration requirements: tenant config, product assignment, lifecycle prerequisites, and current implementation gaps |
| [Product.md](./Product.md) | PRD guidelines, feature ideation, AI agent collaboration, scoping, templates |
| [AnalyticsTracking.md](./AnalyticsTracking.md) | User & Product Usage analytics plan (distinct from the Business Analytics dashboard): client SDK (web + Despia mobile), `analytics-api` ingestion, per-tenant "Product Usage Tracking" settings, 30-day Supabase hot store, and BigQuery warehouse migration |
| [AnalyticsAPI.md](./AnalyticsAPI.md) | Analytics API (Product Usage Tracking) — backend event-ingestion Edge Function: `/config` and `/collect` endpoints, server-side PII/PHI guards, idempotent batch ingest, Supabase hot-store persistence |
| [SettingsIARedesign.md](./SettingsIARedesign.md) | Admin information-architecture redesign (tenant + platform): regrouped navigation, migration plan (Content/wrapper split), cutover to canonical routes, coming-soon treatment, product types, and webhooks |
| [OutboundWebhooksAPI.md](./OutboundWebhooksAPI.md) | Outbound webhooks: typed (lifecycle vs product_usage, never mixed) endpoints, data model, dispatcher edge function, HMAC signing, event catalog, and the RTDH/Pub-Sub delivery option |
| [ProviderRtdhSecret.md](./ProviderRtdhSecret.md) | Provider RTDH validation secret: set/rotate the inbound-webhook HMAC secret from Settings → Providers via RTDH's Secret Manager Interface, with Patient Platform managing the request and RTDH applying it to GCP Secret Manager |

## Quick Reference

### Technology Stack
- **Frontend**: React + TypeScript + Vite + Tailwind CSS + shadcn/ui
- **Backend**: Supabase (Edge Functions, PostgreSQL, Auth, Storage)
- **AI**: OpenAI, Anthropic, Google AI (provider-agnostic patterns)
- **Workflows**: N8N for automation and orchestration
- **Mobile**: Despia for native app wrapper

### Branch Strategy
```
main (prod)
├── staging
│   └── dev
│       └── feature/[feature-name]
```

### Key Principles
1. **Separation of Concerns** - Frontend, Backend, Data layers
2. **Provider Flexibility** - Abstract all provider-specific code
3. **Security First** - Zero-trust, RLS, API auth everywhere
4. **Type Safety** - TypeScript for all code
5. **Design Tokens** - No hardcoded styles, use CSS variables
6. **Accessibility** - WCAG 2.1 AA compliance minimum
