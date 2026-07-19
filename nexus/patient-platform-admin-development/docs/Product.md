# Product Development Guide

> **Purpose**: Guidelines for product managers and ideation teams on how to effectively use the Foundation Canvas system, create PRDs, and leverage AI-assisted development.

---

## Table of Contents

1. [Introduction](#introduction)
2. [Understanding the System](#understanding-the-system)
3. [Feature Ideation Process](#feature-ideation-process)
4. [Writing Effective PRDs](#writing-effective-prds)
5. [Working with AI Agents](#working-with-ai-agents)
6. [Feature Scoping & Planning](#feature-scoping--planning)
7. [Technical Feasibility Questions](#technical-feasibility-questions)
8. [Communication with Engineering](#communication-with-engineering)
9. [Quality Assurance Collaboration](#quality-assurance-collaboration)
10. [Product Templates](#product-templates)

---

## Introduction

### What is Foundation Canvas?

Foundation Canvas is a comprehensive development system designed to accelerate product development through AI-assisted coding with **Lovable, Cursor, Codex, and Claude Code**. This system provides:

- **Architecture guidelines** for scalable applications
- **Backend patterns** for robust server-side logic
- **Frontend standards** for consistent user experiences
- **AI integration patterns** for intelligent features
- **Design systems** for beautiful interfaces

### Your Role as Product

As a Product Manager or ideation team member, your role is to:

1. **Define the "what" and "why"** of features
2. **Communicate user needs** clearly
3. **Prioritize features** based on business value
4. **Collaborate with engineering** on feasibility
5. **Validate solutions** against user requirements
6. **Guide AI agents** with clear instructions

---

## Understanding the System

### Architecture Overview

Before creating PRDs, understand the system architecture:

```
┌─────────────────────────────────────┐
│         Your Feature Idea           │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│    Architecture Constraints         │
│  • Multi-env (dev/staging/prod)     │
│  • Supabase → GCP migration ready   │
│  • Mobile support via Despia        │
└──────────────┬──────────────────────┘
               │
       ┌───────┴──────────┐
       │                  │
       ▼                  ▼
┌─────────────┐    ┌──────────────┐
│  Frontend   │    │   Backend    │
│  (React)    │    │  (Supabase)  │
└─────────────┘    └──────────────┘
```

### Key Questions to Ask Yourself

Before writing a PRD, ask:

1. **Is this a frontend feature, backend feature, or both?**
   - Frontend: UI changes, user interactions
   - Backend: Data processing, business logic, integrations
   - Both: Most features require coordination

2. **Does this require data storage?**
   - If yes, what data model is needed?
   - How does it relate to existing data?

3. **Are there external integrations?**
   - APIs, webhooks, third-party services
   - AI services (OpenAI, voice, etc.)

4. **What are the user permissions?**
   - Who can access this feature?
   - Are there role-based restrictions?

5. **Does this work on mobile?**
   - If yes, note mobile-specific considerations

---

## Feature Ideation Process

### Step 1: User Problem Definition

Start with the user problem, not the solution.

**Template:**
```markdown
## Problem Statement
As a [type of user], I want to [accomplish something], so that [I get some benefit].

## Current Pain Points
- What problems do users face today?
- What inefficiencies exist?
- What feedback have we received?

## Success Metrics
- How will we measure if this solves the problem?
- What KPIs will improve?
```

**Example:**
```markdown
## Problem Statement
As a project manager, I want to quickly see the status of all my projects, so that I can identify blockers and take action without manually checking each one.

## Current Pain Points
- Users must click into each project to see status
- No at-a-glance overview of project health
- Difficult to spot trends across projects

## Success Metrics
- 50% reduction in time to identify blocked projects
- Increased daily active users on dashboard
- Positive feedback on new overview feature
```

### Step 2: Solution Exploration

Now explore potential solutions:

1. **Brainstorm** multiple approaches
2. **Evaluate** technical feasibility (consult Engineering)
3. **Consider** design implications (consult Design)
4. **Assess** impact vs. effort

**Questions to guide solution exploration:**
- What's the simplest solution that solves the problem?
- Can we leverage existing components/patterns?
- Are there analogous features in other products?
- What technical constraints exist?

### Step 3: Validation

Before committing to a PRD:

1. **User validation**: Talk to 3-5 users
2. **Technical validation**: Sync with Engineering
3. **Design validation**: Review with Design
4. **Business validation**: Align with leadership

---

## Writing Effective PRDs

### PRD Structure

```markdown
# [Feature Name]

## Overview
**Status:** Draft | In Review | Approved
**Owner:** [Your Name]
**Engineering Lead:** [Name]
**Design Lead:** [Name]
**Target Release:** [Date]

## Problem Statement
[Why are we building this?]

## Goals
- Primary goal
- Secondary goal

## Non-Goals
[What we're explicitly NOT doing]

## User Stories
1. As a [user type], I want [action], so that [benefit]
2. ...

## Requirements

### Functional Requirements
- [ ] Requirement 1
- [ ] Requirement 2

### Non-Functional Requirements
- [ ] Performance: [specific criteria]
- [ ] Security: [specific criteria]
- [ ] Accessibility: WCAG 2.1 AA

## User Experience

### User Flow
[Describe the step-by-step user journey]

### Wireframes/Mockups
[Link to Figma/design files]

### Edge Cases
- What if [scenario]?
- What happens when [error condition]?

## Technical Considerations

### Data Model
[What data needs to be stored?]

### API Endpoints
[What endpoints are needed?]

### Integrations
[External services, AI, webhooks]

### Mobile Considerations
[Does this work on mobile? Special considerations?]

## Success Metrics
[How will we measure success?]

## Open Questions
- [ ] Question 1
- [ ] Question 2

## Appendix
[Additional context, research, references]
```

### Example PRD

```markdown
# Project Status Dashboard

## Overview
**Status:** Approved
**Owner:** Jane Doe (Product)
**Engineering Lead:** John Smith
**Design Lead:** Sarah Lee
**Target Release:** Q2 2024

## Problem Statement
Project managers currently spend 30+ minutes daily checking individual projects for status updates. This manual process is time-consuming and prone to missing critical blockers.

## Goals
- Enable at-a-glance view of all project statuses
- Surface blocked projects immediately
- Reduce time spent on status checking by 50%

## Non-Goals
- Deep analytics (phase 2)
- Project planning features (separate initiative)
- External stakeholder views (out of scope)

## User Stories
1. As a project manager, I want to see all my projects in one view, so that I can quickly assess overall health
2. As a project manager, I want to see which projects are blocked, so that I can prioritize my attention
3. As a project manager, I want to filter projects by status, so that I can focus on specific areas

## Requirements

### Functional Requirements
- [ ] Display all user's projects in a grid/list view
- [ ] Show project name, owner, status, and last updated
- [ ] Color-code projects by status (green/yellow/red)
- [ ] Filter by status (active, blocked, completed)
- [ ] Click project to navigate to detail view
- [ ] Auto-refresh every 60 seconds

### Non-Functional Requirements
- [ ] Performance: Load within 2 seconds for 100+ projects
- [ ] Security: Users only see their own projects (RLS enforced)
- [ ] Accessibility: WCAG 2.1 AA, keyboard navigation

## User Experience

### User Flow
1. User navigates to /app/dashboard
2. System loads all projects for user
3. Projects displayed in grid (default view)
4. User can toggle list/grid view
5. User can filter by status
6. User clicks project → navigates to detail page

### Wireframes/Mockups
[Link to Figma: https://figma.com/...]

### Edge Cases
- What if user has 0 projects? → Show empty state with "Create Project" CTA
- What if project data fails to load? → Show error message with retry button
- What if user has 1000+ projects? → Implement pagination (100 per page)

## Technical Considerations

### Data Model
Uses existing `projects` table. No schema changes needed.

### API Endpoints
- GET `/api/projects?userId={id}` (existing endpoint)

### Integrations
- None required

### Mobile Considerations
- Responsive grid: 1 column on mobile, 2 on tablet, 3+ on desktop
- Touch-friendly tap targets (min 44x44px)

## Success Metrics
- 50% reduction in avg time spent checking project status (tracked via analytics)
- 80% of project managers use dashboard daily (30-day retention)
- NPS score of 8+ for this feature

## Open Questions
- [x] Should we show archived projects? → No, separate view
- [ ] Should we allow bulk status updates? → Deferred to phase 2

## Appendix
- User research notes: [Link]
- Competitive analysis: [Link]
```

---

## Working with AI Agents

### Preparing Prompts for AI Agents

When working with Lovable, Cursor, Codex, or Claude Code, structure your prompts to reference system documentation:

**Template:**
```
Context: I'm building [feature name] for [type of application].

Reference documentation:
- Architecture: [link to Architecture.md]
- Backend patterns: [link to Backend.md]
- Frontend patterns: [link to Frontend.md]
- Design system: [link to Design.md]

Requirements:
[Copy requirements from PRD]

Constraints:
- Must follow architecture patterns in Architecture.md
- Must use existing database abstraction in Backend.md
- Must use shadcn/ui components per Frontend.md
- Must support mobile (responsive design)

Please implement this feature following the above guidelines.
```

**Example:**
```
Context: I'm building a "Project Status Dashboard" for our project management app.

Reference documentation:
- Architecture: /docs/Architecture.md
- Backend: /docs/Backend.md
- Frontend: /docs/Frontend.md
- Design: /docs/Design.md

Requirements:
1. Display all user's projects in a grid view
2. Show project name, status, last updated
3. Color-code by status (green/yellow/red)
4. Filter by status
5. Auto-refresh every 60 seconds

Constraints:
- Use existing projectQueries from /src/db/queries/projects.ts
- Use shadcn/ui Card component for project display
- Implement responsive grid (1 col mobile, 2 tablet, 3+ desktop)
- Follow color system from Design.md

Please implement this feature.
```

### Iterating with AI Agents

After initial implementation, refine with targeted prompts:

**Examples:**
```
"This looks good, but the project cards are too large on mobile.
Please adjust to be more compact following the spacing system in Design.md."

"The auto-refresh is working, but it's disrupting the user when they're
interacting with filters. Please debounce the refresh when user is actively
using the UI."

"Add error handling following the patterns in Backend.md. Show a toast
notification if the fetch fails, and provide a retry button."
```

### AI Agent Handoff Checklist

Before considering a feature "done" from AI agents:

- [ ] Feature meets all functional requirements from PRD
- [ ] Code follows Architecture.md patterns
- [ ] Backend uses abstraction layers (Backend.md)
- [ ] Frontend uses design system components (Design.md, Frontend.md)
- [ ] Responsive on mobile, tablet, desktop
- [ ] Accessible (ARIA labels, keyboard navigation)
- [ ] Error states handled gracefully
- [ ] Loading states shown
- [ ] Edge cases addressed
- [ ] No console errors

---

## Feature Scoping & Planning

### Sizing Features

Use t-shirt sizes to estimate complexity:

**XS (Extra Small):** 1-2 days
- Simple UI changes
- Minor copy updates
- Configuration changes

**S (Small):** 3-5 days
- New component using existing patterns
- Simple CRUD feature
- Basic form with validation

**M (Medium):** 1-2 weeks
- Complex UI with multiple components
- Backend integration with external API
- New database models

**L (Large):** 2-4 weeks
- Major feature with frontend + backend
- Multiple new components
- Complex business logic

**XL (Extra Large):** 1-2 months
- Requires architectural changes
- Multiple interconnected features
- Significant design work

### Breaking Down Large Features

If a feature is XL, break it into smaller phases:

**Example: "Advanced Project Analytics"**

Phase 1 (M): Basic dashboard with key metrics
- Display project count, completion rate
- Simple bar charts

Phase 2 (M): Filtering and time-based views
- Filter by date range
- Compare periods

Phase 3 (L): Advanced visualizations and exports
- Complex charts (burndown, velocity)
- Export to PDF

### Dependencies

Always identify dependencies:

**Technical dependencies:**
- "This requires the new auth system (in progress)"
- "Depends on API rate limiting (backlog)"

**Design dependencies:**
- "Needs design review for mobile layout"
- "Waiting on brand refresh (Q2)"

**Business dependencies:**
- "Requires legal approval for data retention"
- "Blocked on partnership agreement"

---

## Technical Feasibility Questions

### Before Writing PRD

Ask Engineering these questions:

1. **"Is this technically feasible with our current stack?"**
   - Sometimes features require tech not in Foundation Canvas

2. **"Are there existing patterns we can reuse?"**
   - Avoid reinventing the wheel

3. **"What's the rough complexity estimate?"**
   - XS, S, M, L, XL

4. **"Are there technical risks or unknowns?"**
   - New integrations, performance concerns

5. **"What's the data model impact?"**
   - New tables, migrations, RLS policies

### During Implementation

Stay connected with Engineering:

**Weekly sync:**
- Progress update
- Blockers discussion
- Scope adjustments

**Ad-hoc questions:**
- Use async communication (Slack)
- Respect Engineering focus time
- Batch questions when possible

---

## Communication with Engineering

### Effective Product → Engineering Communication

**DO:**
- ✅ Provide clear, written requirements
- ✅ Reference user research and data
- ✅ Be open to technical constraints
- ✅ Ask clarifying questions
- ✅ Respect technical feasibility feedback
- ✅ Celebrate Engineering wins

**DON'T:**
- ❌ Dictate implementation details
- ❌ Change requirements mid-sprint
- ❌ Skip user research ("I think users want...")
- ❌ Ignore technical constraints
- ❌ Micromanage
- ❌ Rush without clear requirements

### Communication Templates

**Kicking off a feature:**
```
Hi [Engineering Lead],

I've written a PRD for [Feature Name]: [Link]

Key points:
- Problem: [1-2 sentences]
- Success metric: [Primary KPI]
- Target: [Date]

Can we sync this week to review feasibility and estimate complexity?

Thanks!
```

**Mid-implementation check-in:**
```
Hi [Engineer],

Quick check-in on [Feature Name]:
- How's progress?
- Any blockers?
- On track for [Date]?

Let me know if you need anything from Product side.
```

**Scope adjustment:**
```
Hi [Engineering Lead],

After user testing, we need to adjust [Feature Name].

Changes:
- Add: [New requirement]
- Remove: [Descoped item]

Net impact: [More work / Less work / Neutral]

Can we discuss impact on timeline?
```

---

## Quality Assurance Collaboration

### QA Automation in Foundation Canvas

Foundation Canvas includes automated QA via N8N + Appium + BrowserStack. As Product, you should:

1. **Define test scenarios** in PRD
2. **Specify edge cases** to test
3. **Provide expected behavior** for each scenario
4. **Review test results** after deployment

### Test Scenarios Template

```markdown
## Test Scenarios

### Happy Path
1. User logs in
2. Navigates to dashboard
3. Sees all projects
4. Filters by "Active"
5. Clicks project → detail page loads

### Edge Cases
1. User with 0 projects → Empty state shown
2. API fails → Error message + retry button
3. Slow network → Loading state shown
4. User permission revoked → Redirected to sign in

### Mobile-Specific
1. Grid collapses to 1 column on mobile
2. Touch targets are 44x44px minimum
3. Scroll performance is smooth
```

### Post-Deployment Validation

After feature ships to staging/prod:

- [ ] Manually test critical paths
- [ ] Review QA automation results
- [ ] Check analytics for errors
- [ ] Gather user feedback
- [ ] Monitor performance metrics

---

## Product Templates

### Feature Brief (Pre-PRD)

```markdown
# Feature Brief: [Name]

## Problem
[1-2 sentences: What problem are we solving?]

## Proposed Solution
[1-2 sentences: How might we solve it?]

## User Impact
- Who: [User segment]
- Value: [What value do they get?]

## Success Metric
[1 primary KPI]

## Effort Estimate
[XS / S / M / L / XL]

## Priority
[P0 / P1 / P2 / P3]

## Next Steps
- [ ] User research
- [ ] Design exploration
- [ ] Technical feasibility
- [ ] Write full PRD
```

### User Story Template

```markdown
As a [type of user],
I want to [action/feature],
So that [benefit/value].

**Acceptance Criteria:**
- [ ] [Specific, testable criterion]
- [ ] [Specific, testable criterion]
- [ ] [Specific, testable criterion]
```

### Feature Flag Template

```markdown
# Feature Flag: [Feature Name]

## Description
[What is this feature?]

## Flag Name
`feature_[name]`

## Default State
- Dev: `true`
- Staging: `true`
- Prod: `false`

## Rollout Plan
1. Week 1: Internal testing (dev/staging)
2. Week 2: 10% of users
3. Week 3: 50% of users
4. Week 4: 100% rollout

## Success Criteria for Full Rollout
- No errors above baseline
- Success metric at target
- NPS > 7

## Rollback Plan
[How to quickly disable if issues arise]
```

---

## Guiding Principles for Product

### 1. Start with the Problem, Not the Solution
❌ "We need a dashboard."
✅ "Project managers can't quickly see which projects are blocked."

### 2. Data-Driven Decisions
❌ "I think users want this."
✅ "User interviews show 8/10 users struggle with [problem]."

### 3. Iterative Development
❌ "Build the perfect solution in one go."
✅ "Ship MVP, gather feedback, iterate."

### 4. Clear Communication
❌ "Just add a button somewhere."
✅ "Add a 'Create Project' button in the top-right of the dashboard, consistent with other primary actions."

### 5. Respect Technical Constraints
❌ "I don't care about technical limitations."
✅ "Given the migration constraint, let's phase this feature."

### 6. Empower Engineering
❌ "Build it exactly like this."
✅ "Here's the problem and requirements. What's the best technical approach?"

### 7. Validate Early and Often
❌ "Ship it and see what happens."
✅ "Prototype → User test → Refine → Build."

---

## Questions to Ask the System

When planning features, consult the documentation:

**Architecture questions:**
- "How does multi-environment deployment work?" → [Architecture.md](./Architecture.md)
- "What's the database migration process?" → [Architecture.md](./Architecture.md)
- "How do we integrate mobile apps?" → [Architecture.md](./Architecture.md)

**Backend questions:**
- "How do I structure a new API endpoint?" → [Backend.md](./Backend.md)
- "What authentication patterns exist?" → [Backend.md](./Backend.md)
- "How do we handle webhooks?" → [Backend.md](./Backend.md)

**Frontend questions:**
- "What components are available?" → [Frontend.md](./Frontend.md)
- "How do we handle forms?" → [Frontend.md](./Frontend.md)
- "What's the responsive design approach?" → [Frontend.md](./Frontend.md)

**AI questions:**
- "Can we add voice features?" → [AI.md](./AI.md)
- "How do we integrate ML models?" → [AI.md](./AI.md)
- "What AI providers are supported?" → [AI.md](./AI.md)

**Design questions:**
- "What colors can we use?" → [Design.md](./Design.md)
- "What's the typography scale?" → [Design.md](./Design.md)
- "How do we customize the design?" → [Design.md](./Design.md)

---

## Checklist for Great PRDs

Before sharing your PRD:

- [ ] Problem statement is clear and user-focused
- [ ] User stories are specific and testable
- [ ] Success metrics are defined
- [ ] Technical considerations documented (even if high-level)
- [ ] Mobile considerations included (if applicable)
- [ ] Edge cases identified
- [ ] Wireframes or mockups attached
- [ ] Non-goals explicitly stated
- [ ] Dependencies identified
- [ ] Reviewed by Design
- [ ] Reviewed by Engineering
- [ ] Accessible to all stakeholders

---

## Summary

As a Product Manager using Foundation Canvas:

1. **Understand the system**: Read Architecture, Backend, Frontend, AI, and Design docs
2. **Define problems clearly**: Focus on user needs, not solutions
3. **Write detailed PRDs**: Use templates, include all sections
4. **Guide AI agents**: Reference system documentation in prompts
5. **Collaborate with Engineering**: Respect technical constraints, iterate
6. **Validate continuously**: User testing, analytics, feedback
7. **Ship iteratively**: MVP → feedback → iterate

**Remember:** Foundation Canvas accelerates development with AI agents. Your role is to provide clear direction, validate solutions, and ensure we're building the right things for users.

---

**Last Updated**: 2024-01-07
**Maintained By**: Allia Product & Engineering Team
**Version**: 1.0.0
