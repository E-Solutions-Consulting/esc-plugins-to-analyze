# Project Knowledge Base Instructions

> **Add this content to the project's knowledge base (Settings → Manage Knowledge) to ensure all AI prompts follow these guidelines.**

---

## Core Instructions for AI Agents

When working on this project, you MUST follow the Foundation Canvas guidelines documented in the `/docs` folder. Before implementing any feature, read the relevant documentation files.

### Mandatory Documentation Reference

| Task Type | Required Reading |
|-----------|------------------|
| Any new feature | `docs/Architecture.md`, `docs/Product.md` |
| UI/Component work | `docs/Frontend.md`, `docs/Design.md` |
| API/Database work | `docs/Backend.md`, `docs/Architecture.md` |
| AI features | `docs/AI.md`, `docs/Backend.md` |
| Styling/Theming | `docs/Design.md` |

---

## Key Guidelines Summary

### 1. Architecture (from `docs/Architecture.md`)
- Follow the branch strategy: `main` → `staging` → `dev` → `feature/*`
- Design for Supabase → GCP migration portability
- Use environment variables for all configuration
- Implement stateless, horizontally scalable services
- API-first design with authentication on all endpoints

### 2. Frontend (from `docs/Frontend.md`)
- Use the established folder structure: `components/ui/`, `components/features/`, `hooks/`, `lib/`, `pages/`
- All components must be TypeScript with proper typing
- Use React Query for server state, Zustand for client state
- Follow component patterns: Container/Presenter, Compound Components
- Implement error boundaries and loading states
- Mobile-first responsive design

### 3. Backend (from `docs/Backend.md`)
- All Edge Functions must be stateless
- Use the database abstraction layer pattern
- Implement Row Level Security (RLS) on all tables
- Follow the error handling standards with proper error codes
- Use the provided authentication patterns
- All API responses must use the standard response format

### 4. Design System (from `docs/Design.md`)
- **NEVER hardcode colors** - use CSS variables/design tokens
- Follow the established spacing scale (4px base unit)
- Use the typography scale defined in tokens
- All components must meet WCAG 2.1 AA accessibility
- Apply the color system with semantic naming (primary, secondary, etc.)
- Use shadcn/ui as the component foundation

### 5. AI Integration (from `docs/AI.md`)
- Use provider-agnostic patterns for all AI features
- Implement proper error handling and fallbacks
- Track usage and implement rate limiting
- Store all API keys in environment variables
- Follow the structured prompt patterns
- Use N8N for complex AI workflows

### 6. Product/Features (from `docs/Product.md`)
- Request clear acceptance criteria before implementation
- Break features into atomic, testable units
- Consider the user journey and edge cases
- Validate against the PRD template structure

---

## Pre-Implementation Checklist

Before writing any code, verify:

- [ ] Read relevant documentation files in `/docs`
- [ ] Feature aligns with Architecture.md patterns
- [ ] Component follows Frontend.md structure
- [ ] Styling uses Design.md tokens (no hardcoded values)
- [ ] API follows Backend.md patterns
- [ ] Security considerations addressed
- [ ] Accessibility requirements met
- [ ] Error handling implemented
- [ ] TypeScript types defined

---

## File Reading Commands

When starting work, read the relevant docs:

```
# For full-stack features
Read: docs/Architecture.md, docs/Frontend.md, docs/Backend.md

# For UI-only work
Read: docs/Frontend.md, docs/Design.md

# For backend-only work
Read: docs/Backend.md, docs/Architecture.md

# For AI features
Read: docs/AI.md, docs/Backend.md
```

---

## Response Format

When implementing features, structure your work as:

1. **Reference Documentation**: Cite which docs/guidelines you're following
2. **Implementation Plan**: Brief outline of approach
3. **Code Changes**: Following the patterns in documentation
4. **Verification**: How to test the implementation

---

## Enforcement

If a prompt requests something that violates these guidelines:
1. Explain which guideline would be violated
2. Propose an alternative approach that complies
3. Only proceed with the compliant approach

**These guidelines are non-negotiable for project consistency and quality.**
