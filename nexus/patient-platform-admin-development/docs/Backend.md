# Backend Development Best Practices

> **Purpose**: Comprehensive guidelines for backend development, including code structure, authentication, database operations, Edge Functions, API design, and integration patterns.

---

## Table of Contents

1. [Backend Architecture Overview](#backend-architecture-overview)
2. [Project Structure](#project-structure)
3. [Authentication & Authorization](#authentication--authorization)
4. [Database Layer](#database-layer)
5. [Edge Functions / Cloud Functions](#edge-functions--cloud-functions)
6. [API Design Patterns](#api-design-patterns)
7. [Integration Patterns](#integration-patterns)
8. [Error Handling](#error-handling)
9. [Security Best Practices](#security-best-practices)
10. [Testing](#testing)
11. [Code Examples](#code-examples)

---

## Backend Architecture Overview

### Stack Components

```
┌───────────────────────────────────────┐
│         Client Layer                  │
│    (Browser, Mobile App)              │
└──────────────┬────────────────────────┘
               │
               │ HTTPS/WSS
               ▼
┌──────────────────────────────────────┐
│      API Gateway / Edge Router       │
│   (Supabase Edge / Cloud Run)        │
└──────────────┬───────────────────────┘
               │
       ┌───────┴──────────┐
       │                  │
       ▼                  ▼
┌─────────────┐    ┌──────────────┐
│Edge Functions│    │  Supabase   │
│ (Serverless) │    │   Services  │
└──────┬───────┘    └──────┬───────┘
       │                   │
       └─────────┬─────────┘
                 ▼
        ┌─────────────────┐
        │   PostgreSQL    │
        │   (Supabase/    │
        │   Cloud SQL)    │
        └─────────────────┘
```

### Core Principles

1. **Stateless Functions**: Every Edge Function should be stateless
2. **Database Abstraction**: All DB operations through abstraction layer
3. **Security First**: Authentication on every endpoint
4. **Error Resilience**: Graceful degradation and retry logic
5. **Performance**: Efficient queries, caching, connection pooling

---

## Project Structure

### Recommended Folder Structure

```
/
├── src/
│   ├── lib/                        # Core abstractions
│   │   ├── auth.ts                # Authentication layer
│   │   ├── db.ts                  # Database abstraction
│   │   ├── api.ts                 # API client
│   │   └── utils.ts               # Shared utilities
│   │
│   ├── db/                        # Database layer
│   │   ├── schema.ts              # Drizzle schema
│   │   ├── migrations/            # Migration files
│   │   ├── queries/               # Reusable queries
│   │   │   ├── users.ts
│   │   │   └── projects.ts
│   │   └── types.ts               # Database types
│   │
│   ├── services/                  # Business logic
│   │   ├── user-service.ts
│   │   ├── project-service.ts
│   │   └── notification-service.ts
│   │
│   └── integrations/              # External integrations
│       ├── email/
│       │   └── sendgrid.ts
│       ├── ai/
│       │   ├── openai.ts
│       │   └── anthropic.ts
│       └── webhooks/
│           └── webhook-handler.ts
│
├── supabase/
│   ├── functions/                 # Edge Functions
│   │   ├── create-project/
│   │   │   ├── index.ts
│   │   │   └── test.ts
│   │   ├── process-webhook/
│   │   │   ├── index.ts
│   │   │   └── handlers/
│   │   └── _shared/              # Shared code
│   │       ├── cors.ts
│   │       ├── auth.ts
│   │       └── validation.ts
│   │
│   ├── migrations/               # Database migrations
│   │   └── 20240107_initial.sql
│   │
│   └── config.toml               # Supabase config
│
└── tests/
    ├── unit/
    └── integration/
```

---

## Authentication & Authorization

### Authentication Abstraction Layer

**File**: `src/lib/auth.ts`

```typescript
import { createClient } from "@supabase/supabase-js";
import type { User, Session } from "@supabase/supabase-js";

// Supabase client (can be swapped for other providers)
const supabase = createClient(
  import.meta.env.VITE_SUPABASE_URL,
  import.meta.env.VITE_SUPABASE_ANON_KEY,
);

/**
 * Authentication Service
 * Abstraction layer for auth operations - can swap providers
 */
export const authService = {
  /**
   * Sign up a new user
   */
  async signUp(
    email: string,
    password: string,
    metadata?: Record<string, any>,
  ) {
    const { data, error } = await supabase.auth.signUp({
      email,
      password,
      options: {
        data: metadata,
      },
    });

    if (error) throw new AuthError(error.message, "SIGNUP_FAILED");
    return data;
  },

  /**
   * Sign in existing user
   */
  async signIn(email: string, password: string) {
    const { data, error } = await supabase.auth.signInWithPassword({
      email,
      password,
    });

    if (error) throw new AuthError(error.message, "SIGNIN_FAILED");
    return data;
  },

  /**
   * Sign out current user
   */
  async signOut() {
    const { error } = await supabase.auth.signOut();
    if (error) throw new AuthError(error.message, "SIGNOUT_FAILED");
  },

  /**
   * Get current session
   */
  async getSession(): Promise<Session | null> {
    const {
      data: { session },
    } = await supabase.auth.getSession();
    return session;
  },

  /**
   * Get current user
   */
  async getCurrentUser(): Promise<User | null> {
    const {
      data: { user },
    } = await supabase.auth.getUser();
    return user;
  },

  /**
   * Verify user token (for Edge Functions)
   */
  async verifyToken(token: string): Promise<User> {
    const {
      data: { user },
      error,
    } = await supabase.auth.getUser(token);
    if (error || !user) throw new AuthError("Invalid token", "INVALID_TOKEN");
    return user;
  },

  /**
   * Reset password
   */
  async resetPassword(email: string) {
    const { error } = await supabase.auth.resetPasswordForEmail(email);
    if (error) throw new AuthError(error.message, "RESET_FAILED");
  },

  /**
   * Update password
   */
  async updatePassword(newPassword: string) {
    const { error } = await supabase.auth.updateUser({
      password: newPassword,
    });
    if (error) throw new AuthError(error.message, "UPDATE_FAILED");
  },
};

/**
 * Custom Auth Error
 */
export class AuthError extends Error {
  constructor(
    message: string,
    public code: string,
  ) {
    super(message);
    this.name = "AuthError";
  }
}

/**
 * Auth middleware for Edge Functions
 */
export async function requireAuth(req: Request): Promise<User> {
  const authHeader = req.headers.get("Authorization");

  if (!authHeader?.startsWith("Bearer ")) {
    throw new AuthError("Missing authorization header", "UNAUTHORIZED");
  }

  const token = authHeader.replace("Bearer ", "");
  return await authService.verifyToken(token);
}
```

### Patient Sign-In Methods

The `patient-api` edge function exposes the patient-facing sign-in methods. All
are tenant-scoped and resolve to the same Supabase session tokens:

- **Email + password** — `POST /auth/signin`.
- **Passwordless email OTP** — `POST /auth/otp/request` + `/auth/otp/verify`.
  Single-use, hashed (`sha256`), 10-minute, rate-limited 6-digit code stored in
  `patient_auth_otps`; the session is minted server-side via
  `admin.generateLink({ type: 'magiclink' })` → `verifyOtp`.
- **Social login (Google; Apple later)** — browser OAuth → `POST
  /auth/oauth/resolve`. Provider-agnostic; maps the OAuth email to the patient in
  the active tenant; **blocks with `NO_ACCOUNT`** when there's no patient (no
  auto-create).
- **Passkeys / biometrics** — Supabase native WebAuthn (Beta); the resulting
  session is exchanged via the same `/auth/oauth/resolve`. UI + dashboard only.

Under PP-566 (Option 2) the **password is optional** — patients can finish
checkout and sign in later with OTP, Google, or a passkey. Full request/response
contracts: [PatientAPI.md → Authentication](./PatientAPI.md#authentication).
Dashboard/cloud setup: [AuthMethodsSetup.md](./AuthMethodsSetup.md).

### Authorization Patterns

#### Row-Level Security (RLS)

```sql
-- Enable RLS on table
ALTER TABLE projects ENABLE ROW LEVEL SECURITY;

-- Policy: Users can only see their own projects
CREATE POLICY "Users can view own projects"
  ON projects FOR SELECT
  USING (auth.uid() = owner_id);

-- Policy: Users can insert their own projects
CREATE POLICY "Users can create projects"
  ON projects FOR INSERT
  WITH CHECK (auth.uid() = owner_id);

-- Policy: Users can update their own projects
CREATE POLICY "Users can update own projects"
  ON projects FOR UPDATE
  USING (auth.uid() = owner_id);

-- Policy: Users can delete their own projects
CREATE POLICY "Users can delete own projects"
  ON projects FOR DELETE
  USING (auth.uid() = owner_id);
```

#### Role-Based Access Control (RBAC)

```typescript
// src/lib/rbac.ts
export enum Role {
  ADMIN = "admin",
  USER = "user",
  GUEST = "guest",
}

export enum Permission {
  READ_PROJECT = "read:project",
  WRITE_PROJECT = "write:project",
  DELETE_PROJECT = "delete:project",
  MANAGE_USERS = "manage:users",
}

const rolePermissions: Record<Role, Permission[]> = {
  [Role.ADMIN]: [
    Permission.READ_PROJECT,
    Permission.WRITE_PROJECT,
    Permission.DELETE_PROJECT,
    Permission.MANAGE_USERS,
  ],
  [Role.USER]: [Permission.READ_PROJECT, Permission.WRITE_PROJECT],
  [Role.GUEST]: [Permission.READ_PROJECT],
};

export function hasPermission(role: Role, permission: Permission): boolean {
  return rolePermissions[role]?.includes(permission) ?? false;
}

export async function requirePermission(
  user: User,
  permission: Permission,
): Promise<void> {
  const userRole = (user.user_metadata?.role as Role) || Role.GUEST;

  if (!hasPermission(userRole, permission)) {
    throw new Error(`Missing permission: ${permission}`);
  }
}
```

---

## Database Layer

### Database Abstraction

**File**: `src/lib/db.ts`

```typescript
import { drizzle } from "drizzle-orm/postgres-js";
import postgres from "postgres";
import * as schema from "../db/schema";

// Connection string from environment
const connectionString =
  import.meta.env.VITE_DATABASE_URL || import.meta.env.VITE_SUPABASE_URL;

// Create connection
const client = postgres(connectionString);

// Create Drizzle instance
export const db = drizzle(client, { schema });

/**
 * Database service with abstracted operations
 */
export const dbService = {
  /**
   * Get database instance
   */
  getDb() {
    return db;
  },

  /**
   * Execute raw SQL (use sparingly)
   */
  async executeRaw<T>(sql: string, params?: any[]): Promise<T> {
    return client.unsafe(sql, params) as Promise<T>;
  },

  /**
   * Health check
   */
  async healthCheck(): Promise<boolean> {
    try {
      await client`SELECT 1`;
      return true;
    } catch {
      return false;
    }
  },

  /**
   * Close connection
   */
  async close() {
    await client.end();
  },
};

// Export schema for queries
export { schema };
```

### Schema Definition (Drizzle ORM)

**File**: `src/db/schema.ts`

```typescript
import {
  pgTable,
  uuid,
  text,
  timestamp,
  boolean,
  jsonb,
  varchar,
  integer,
} from "drizzle-orm/pg-core";
import { relations } from "drizzle-orm";

/**
 * Users table
 */
export const users = pgTable("users", {
  id: uuid("id").primaryKey().defaultRandom(),
  email: varchar("email", { length: 255 }).notNull().unique(),
  name: text("name"),
  avatarUrl: text("avatar_url"),
  metadata: jsonb("metadata").$type<Record<string, any>>(),
  createdAt: timestamp("created_at").defaultNow().notNull(),
  updatedAt: timestamp("updated_at").defaultNow().notNull(),
});

/**
 * Projects table
 */
export const projects = pgTable("projects", {
  id: uuid("id").primaryKey().defaultRandom(),
  name: varchar("name", { length: 255 }).notNull(),
  description: text("description"),
  ownerId: uuid("owner_id")
    .references(() => users.id, {
      onDelete: "cascade",
    })
    .notNull(),
  isActive: boolean("is_active").default(true).notNull(),
  settings: jsonb("settings").$type<Record<string, any>>(),
  createdAt: timestamp("created_at").defaultNow().notNull(),
  updatedAt: timestamp("updated_at").defaultNow().notNull(),
});

/**
 * Tasks table
 */
export const tasks = pgTable("tasks", {
  id: uuid("id").primaryKey().defaultRandom(),
  title: varchar("title", { length: 255 }).notNull(),
  description: text("description"),
  projectId: uuid("project_id")
    .references(() => projects.id, {
      onDelete: "cascade",
    })
    .notNull(),
  assigneeId: uuid("assignee_id").references(() => users.id, {
    onDelete: "set null",
  }),
  status: varchar("status", { length: 50 }).default("todo").notNull(),
  priority: integer("priority").default(0).notNull(),
  dueDate: timestamp("due_date"),
  createdAt: timestamp("created_at").defaultNow().notNull(),
  updatedAt: timestamp("updated_at").defaultNow().notNull(),
});

/**
 * Relations
 */
export const usersRelations = relations(users, ({ many }) => ({
  projects: many(projects),
  tasks: many(tasks),
}));

export const projectsRelations = relations(projects, ({ one, many }) => ({
  owner: one(users, {
    fields: [projects.ownerId],
    references: [users.id],
  }),
  tasks: many(tasks),
}));

export const tasksRelations = relations(tasks, ({ one }) => ({
  project: one(projects, {
    fields: [tasks.projectId],
    references: [projects.id],
  }),
  assignee: one(users, {
    fields: [tasks.assigneeId],
    references: [users.id],
  }),
}));

/**
 * TypeScript types
 */
export type User = typeof users.$inferSelect;
export type NewUser = typeof users.$inferInsert;

export type Project = typeof projects.$inferSelect;
export type NewProject = typeof projects.$inferInsert;

export type Task = typeof tasks.$inferSelect;
export type NewTask = typeof tasks.$inferInsert;
```

### Query Patterns

**File**: `src/db/queries/projects.ts`

```typescript
import { eq, and, desc } from "drizzle-orm";
import { db, schema } from "../../lib/db";

/**
 * Project queries
 */
export const projectQueries = {
  /**
   * Get all projects for a user
   */
  async getAllForUser(userId: string) {
    return db.query.projects.findMany({
      where: eq(schema.projects.ownerId, userId),
      orderBy: [desc(schema.projects.createdAt)],
      with: {
        tasks: true,
      },
    });
  },

  /**
   * Get single project by ID
   */
  async getById(projectId: string, userId: string) {
    return db.query.projects.findFirst({
      where: and(
        eq(schema.projects.id, projectId),
        eq(schema.projects.ownerId, userId),
      ),
      with: {
        owner: true,
        tasks: {
          with: {
            assignee: true,
          },
        },
      },
    });
  },

  /**
   * Create new project
   */
  async create(data: {
    name: string;
    description?: string;
    ownerId: string;
    settings?: Record<string, any>;
  }) {
    const [project] = await db.insert(schema.projects).values(data).returning();
    return project;
  },

  /**
   * Update project
   */
  async update(
    projectId: string,
    userId: string,
    data: {
      name?: string;
      description?: string;
      isActive?: boolean;
      settings?: Record<string, any>;
    },
  ) {
    const now = dateTime().toDate();
    const [project] = await db
      .update(schema.projects)
      .set({ ...data, updatedAt: now })
      .where(
        and(
          eq(schema.projects.id, projectId),
          eq(schema.projects.ownerId, userId),
        ),
      )
      .returning();
    return project;
  },

  /**
   * Delete project
   */
  async delete(projectId: string, userId: string) {
    await db
      .delete(schema.projects)
      .where(
        and(
          eq(schema.projects.id, projectId),
          eq(schema.projects.ownerId, userId),
        ),
      );
  },

  /**
   * Search projects
   */
  async search(userId: string, searchTerm: string) {
    return db.query.projects.findMany({
      where: and(
        eq(schema.projects.ownerId, userId),
        // Note: For production, use full-text search
        // This is a simple example
      ),
      orderBy: [desc(schema.projects.createdAt)],
    });
  },
};
```

### Migrations

**File**: `supabase/migrations/20240107_initial.sql`

```sql
-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Create users table
CREATE TABLE users (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  email VARCHAR(255) NOT NULL UNIQUE,
  name TEXT,
  avatar_url TEXT,
  metadata JSONB,
  created_at TIMESTAMP DEFAULT NOW() NOT NULL,
  updated_at TIMESTAMP DEFAULT NOW() NOT NULL
);

-- Create projects table
CREATE TABLE projects (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  name VARCHAR(255) NOT NULL,
  description TEXT,
  owner_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  is_active BOOLEAN DEFAULT true NOT NULL,
  settings JSONB,
  created_at TIMESTAMP DEFAULT NOW() NOT NULL,
  updated_at TIMESTAMP DEFAULT NOW() NOT NULL
);

-- Create tasks table
CREATE TABLE tasks (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  title VARCHAR(255) NOT NULL,
  description TEXT,
  project_id UUID NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
  assignee_id UUID REFERENCES users(id) ON DELETE SET NULL,
  status VARCHAR(50) DEFAULT 'todo' NOT NULL,
  priority INTEGER DEFAULT 0 NOT NULL,
  due_date TIMESTAMP,
  created_at TIMESTAMP DEFAULT NOW() NOT NULL,
  updated_at TIMESTAMP DEFAULT NOW() NOT NULL
);

-- Create indexes
CREATE INDEX idx_projects_owner_id ON projects(owner_id);
CREATE INDEX idx_tasks_project_id ON tasks(project_id);
CREATE INDEX idx_tasks_assignee_id ON tasks(assignee_id);
CREATE INDEX idx_tasks_status ON tasks(status);

-- Enable Row Level Security
ALTER TABLE projects ENABLE ROW LEVEL SECURITY;
ALTER TABLE tasks ENABLE ROW LEVEL SECURITY;

-- RLS Policies for projects
CREATE POLICY "Users can view own projects"
  ON projects FOR SELECT
  USING (auth.uid() = owner_id);

CREATE POLICY "Users can create projects"
  ON projects FOR INSERT
  WITH CHECK (auth.uid() = owner_id);

CREATE POLICY "Users can update own projects"
  ON projects FOR UPDATE
  USING (auth.uid() = owner_id);

CREATE POLICY "Users can delete own projects"
  ON projects FOR DELETE
  USING (auth.uid() = owner_id);

-- RLS Policies for tasks
CREATE POLICY "Users can view tasks in own projects"
  ON tasks FOR SELECT
  USING (
    EXISTS (
      SELECT 1 FROM projects
      WHERE projects.id = tasks.project_id
      AND projects.owner_id = auth.uid()
    )
  );

CREATE POLICY "Users can create tasks in own projects"
  ON tasks FOR INSERT
  WITH CHECK (
    EXISTS (
      SELECT 1 FROM projects
      WHERE projects.id = tasks.project_id
      AND projects.owner_id = auth.uid()
    )
  );

-- Create updated_at trigger function
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = NOW();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Add triggers for updated_at
CREATE TRIGGER update_users_updated_at
  BEFORE UPDATE ON users
  FOR EACH ROW
  EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_projects_updated_at
  BEFORE UPDATE ON projects
  FOR EACH ROW
  EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_tasks_updated_at
  BEFORE UPDATE ON tasks
  FOR EACH ROW
  EXECUTE FUNCTION update_updated_at_column();
```

---

## Edge Functions / Cloud Functions

### Edge Function Structure

**File**: `supabase/functions/create-project/index.ts`

```typescript
import { serve } from "https://deno.land/std@0.168.0/http/server.ts";
import { createClient } from "https://esm.sh/@supabase/supabase-js@2";
import { corsHeaders } from "../_shared/cors.ts";
import { requireAuth } from "../_shared/auth.ts";
import { validate } from "../_shared/validation.ts";

/**
 * Request body schema
 */
interface CreateProjectRequest {
  name: string;
  description?: string;
  settings?: Record<string, any>;
}

/**
 * Edge Function handler
 */
serve(async (req) => {
  // Handle CORS preflight
  if (req.method === "OPTIONS") {
    return new Response("ok", { headers: corsHeaders });
  }

  try {
    // 1. Authenticate user
    const user = await requireAuth(req);

    // 2. Parse and validate request
    const body: CreateProjectRequest = await req.json();
    validate(body, {
      name: { type: "string", required: true, minLength: 1, maxLength: 255 },
      description: { type: "string", required: false },
      settings: { type: "object", required: false },
    });

    // 3. Create Supabase client
    const supabase = createClient(
      Deno.env.get("SUPABASE_URL")!,
      Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!,
    );

    // 4. Create project
    const { data: project, error } = await supabase
      .from("projects")
      .insert({
        name: body.name,
        description: body.description,
        owner_id: user.id,
        settings: body.settings || {},
      })
      .select()
      .single();

    if (error) {
      throw error;
    }

    // 5. Return success response
    return new Response(
      JSON.stringify({
        success: true,
        data: project,
      }),
      {
        headers: {
          ...corsHeaders,
          "Content-Type": "application/json",
        },
        status: 201,
      },
    );
  } catch (error) {
    console.error("Error creating project:", error);

    // Error response
    return new Response(
      JSON.stringify({
        success: false,
        error: {
          code: error.code || "INTERNAL_ERROR",
          message: error.message || "An error occurred",
        },
      }),
      {
        headers: {
          ...corsHeaders,
          "Content-Type": "application/json",
        },
        status: error.status || 500,
      },
    );
  }
});
```

### Shared Utilities

**File**: `supabase/functions/_shared/cors.ts`

```typescript
export function buildCorsHeaders(req: Request, options?: CorsOptions) {
  // Shared helper that reads CORS_ALLOWED_ORIGINS from Deno.env
  // and echoes the request Origin only when it matches a configured pattern.
}
```

For deployed Supabase Edge Functions, `CORS_ALLOWED_ORIGINS` must be provided as
a Supabase secret on the target project. Root app `.env.*` files are not loaded
into deployed edge-function `Deno.env`.

Whenever a new patient-app or admin-app domain is introduced, update
`CORS_ALLOWED_ORIGINS` for each affected project ref. For auth flows that still
rely on Supabase redirects, such as the admin forgot-password UI, also add the
new domain to Supabase Authentication Redirect URLs for that project.

Current example value:

```env
CORS_ALLOWED_ORIGINS="http://localhost:*,http://127.0.0.1:*,https://*.lovableproject.com,https://*.lovable.app"
```

This repo also maps the secret through `[edge_runtime.secrets]` in
`supabase/config.toml`, so Supabase Branching preview instances can consume it
from the preview secret workflow (`supabase/.env.preview` + `supabase/.env.keys`).

**File**: `supabase/functions/_shared/auth.ts`

```typescript
import { createClient } from "https://esm.sh/@supabase/supabase-js@2";

export async function requireAuth(req: Request) {
  const authHeader = req.headers.get("Authorization");

  if (!authHeader?.startsWith("Bearer ")) {
    throw new Error("Missing authorization header");
  }

  const token = authHeader.replace("Bearer ", "");

  const supabase = createClient(
    Deno.env.get("SUPABASE_URL")!,
    Deno.env.get("SUPABASE_ANON_KEY")!,
  );

  const {
    data: { user },
    error,
  } = await supabase.auth.getUser(token);

  if (error || !user) {
    throw new Error("Invalid token");
  }

  return user;
}
```

**File**: `supabase/functions/_shared/validation.ts`

```typescript
interface ValidationRule {
  type: "string" | "number" | "boolean" | "object" | "array";
  required?: boolean;
  minLength?: number;
  maxLength?: number;
  min?: number;
  max?: number;
  pattern?: RegExp;
}

type ValidationSchema = Record<string, ValidationRule>;

export function validate(data: any, schema: ValidationSchema): void {
  for (const [field, rule] of Object.entries(schema)) {
    const value = data[field];

    // Check required
    if (rule.required && (value === undefined || value === null)) {
      throw new Error(`Field '${field}' is required`);
    }

    // Skip if not required and not provided
    if (!rule.required && (value === undefined || value === null)) {
      continue;
    }

    // Check type
    const actualType = Array.isArray(value) ? "array" : typeof value;
    if (actualType !== rule.type) {
      throw new Error(`Field '${field}' must be of type ${rule.type}`);
    }

    // String validations
    if (rule.type === "string") {
      if (rule.minLength && value.length < rule.minLength) {
        throw new Error(
          `Field '${field}' must be at least ${rule.minLength} characters`,
        );
      }
      if (rule.maxLength && value.length > rule.maxLength) {
        throw new Error(
          `Field '${field}' must be at most ${rule.maxLength} characters`,
        );
      }
      if (rule.pattern && !rule.pattern.test(value)) {
        throw new Error(`Field '${field}' has invalid format`);
      }
    }

    // Number validations
    if (rule.type === "number") {
      if (rule.min !== undefined && value < rule.min) {
        throw new Error(`Field '${field}' must be at least ${rule.min}`);
      }
      if (rule.max !== undefined && value > rule.max) {
        throw new Error(`Field '${field}' must be at most ${rule.max}`);
      }
    }
  }
}
```

### Edge Function Inventory

Edge Functions live in `supabase/functions/<name>/` and are registered in `supabase/config.toml` (most with `verify_jwt = false` — they perform their own auth/tenant resolution). Each function should have a matching `docs/*API.md`. Notable functions:

| Function | Purpose | Doc |
|---|---|---|
| `tenant-info` | Public tenant metadata/branding/feature-flags for the patient UI | [TenantAPI.md](./TenantAPI.md) |
| `patient-api` | Patient-facing API (auth/signup, products, terms) | [PatientAPI.md](./PatientAPI.md) |
| `plan-api` | Checkout, payment intents, subscriptions | [PlanAPI.md](./PlanAPI.md) |
| `order-lifecycle` | Order state machine + fulfilment orchestration | [OrderLifecycleAPI.md](./OrderLifecycleAPI.md) |
| `analytics-api` | Product Usage Tracking ingestion (`/config`, `/collect`); anon-friendly, batch, PII-guarded | [AnalyticsAPI.md](./AnalyticsAPI.md) |
| `rtdh-webhook` | RTDH normalized cross-system event ingestion | [RTDHWebhookAPI.md](./RTDHWebhookAPI.md) |

> The analytics tables (`tenant_analytics_settings`, `analytics_devices`, `analytics_sessions`, `analytics_events`, `analytics_event_types`) are written **only** by `analytics-api` via the service role; tenant admins have read-only RLS on their own tenant's rows. See [AnalyticsTracking.md §4](./AnalyticsTracking.md#4-data-model-changes-supabase).

---

## API Design Patterns

### REST API Standards

#### Endpoint Naming Conventions

```
Resource-oriented URLs:
✅ GET    /api/projects              (List all projects)
✅ GET    /api/projects/:id          (Get single project)
✅ POST   /api/projects              (Create project)
✅ PUT    /api/projects/:id          (Replace project)
✅ PATCH  /api/projects/:id          (Update project)
✅ DELETE /api/projects/:id          (Delete project)

Nested resources:
✅ GET    /api/projects/:id/tasks    (List tasks in project)
✅ POST   /api/projects/:id/tasks    (Create task in project)

Actions on resources:
✅ POST   /api/projects/:id/archive  (Archive project)
✅ POST   /api/projects/:id/restore  (Restore project)

❌ /api/getProjects                  (Wrong: verb in URL)
❌ /api/project-list                 (Wrong: not RESTful)
❌ /api/projects/deleteProject       (Wrong: redundant verb)
```

#### Response Format

```typescript
// Success response
{
  "success": true,
  "data": {
    "id": "123",
    "name": "My Project",
    "createdAt": "2024-01-07T12:00:00Z"
  },
  "metadata": {
    "timestamp": "2024-01-07T12:00:00Z",
    "version": "1.0"
  }
}

// Error response
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Invalid input data",
    "details": {
      "field": "name",
      "issue": "Name is required"
    },
    "timestamp": "2024-01-07T12:00:00Z"
  }
}

// List response (with pagination)
{
  "success": true,
  "data": [/* items */],
  "pagination": {
    "page": 1,
    "pageSize": 20,
    "total": 100,
    "totalPages": 5,
    "hasNext": true,
    "hasPrev": false
  }
}
```

#### HTTP Status Codes

```typescript
// Success
200 OK              // Successful GET, PUT, PATCH, DELETE
201 Created         // Successful POST
204 No Content      // Successful DELETE with no response body

// Client Errors
400 Bad Request     // Invalid request data
401 Unauthorized    // Missing or invalid authentication
403 Forbidden       // Authenticated but not authorized
404 Not Found       // Resource doesn't exist
422 Unprocessable   // Validation errors

// Server Errors
500 Internal Error  // Unexpected server error
503 Service Unavail // Temporary unavailable
```

---

## Integration Patterns

### Webhook Handler

**File**: `supabase/functions/webhook-handler/index.ts`

```typescript
import { serve } from "https://deno.land/std@0.168.0/http/server.ts";
import { createHmac } from "https://deno.land/std@0.168.0/node/crypto.ts";

/**
 * Verify webhook signature
 */
function verifyWebhookSignature(
  payload: string,
  signature: string,
  secret: string,
): boolean {
  const hmac = createHmac("sha256", secret);
  hmac.update(payload);
  const expectedSignature = hmac.digest("hex");
  return signature === expectedSignature;
}

/**
 * Webhook event handlers
 */
const handlers = {
  "user.created": async (data: any) => {
    console.log("User created:", data);
    // Send welcome email, create default settings, etc.
  },

  "project.created": async (data: any) => {
    console.log("Project created:", data);
    // Initialize project resources, send notifications, etc.
  },

  "payment.succeeded": async (data: any) => {
    console.log("Payment succeeded:", data);
    // Update subscription, send receipt, etc.
  },
};

serve(async (req) => {
  try {
    // 1. Get signature from header
    const signature = req.headers.get("X-Webhook-Signature");
    if (!signature) {
      return new Response("Missing signature", { status: 401 });
    }

    // 2. Get raw body
    const rawBody = await req.text();

    // 3. Verify signature
    const secret = Deno.env.get("WEBHOOK_SECRET")!;
    if (!verifyWebhookSignature(rawBody, signature, secret)) {
      return new Response("Invalid signature", { status: 401 });
    }

    // 4. Parse event
    const event = JSON.parse(rawBody);
    const { type, data } = event;

    // 5. Route to handler
    const handler = handlers[type as keyof typeof handlers];
    if (!handler) {
      console.warn(`No handler for event type: ${type}`);
      return new Response(JSON.stringify({ received: true }), {
        headers: { "Content-Type": "application/json" },
      });
    }

    // 6. Execute handler (async, don't wait)
    handler(data).catch((error) => {
      console.error(`Error handling ${type}:`, error);
    });

    // 7. Return 200 quickly
    return new Response(JSON.stringify({ received: true }), {
      headers: { "Content-Type": "application/json" },
    });
  } catch (error) {
    console.error("Webhook error:", error);
    return new Response("Internal error", { status: 500 });
  }
});
```

### External API Integration

**File**: `src/integrations/email/sendgrid.ts`

```typescript
interface EmailOptions {
  to: string;
  from: string;
  subject: string;
  text?: string;
  html?: string;
}

export class SendGridService {
  private apiKey: string;
  private baseUrl = "https://api.sendgrid.com/v3";

  constructor() {
    this.apiKey = import.meta.env.VITE_SENDGRID_API_KEY;
    if (!this.apiKey) {
      throw new Error("SendGrid API key not configured");
    }
  }

  async sendEmail(options: EmailOptions): Promise<void> {
    const response = await fetch(`${this.baseUrl}/mail/send`, {
      method: "POST",
      headers: {
        Authorization: `Bearer ${this.apiKey}`,
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        personalizations: [
          {
            to: [{ email: options.to }],
          },
        ],
        from: { email: options.from },
        subject: options.subject,
        content: [
          {
            type: "text/plain",
            value: options.text || "",
          },
          {
            type: "text/html",
            value: options.html || "",
          },
        ],
      }),
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(`SendGrid error: ${error.message}`);
    }
  }

  async sendTemplateEmail(
    to: string,
    templateId: string,
    dynamicData: Record<string, any>,
  ): Promise<void> {
    const response = await fetch(`${this.baseUrl}/mail/send`, {
      method: "POST",
      headers: {
        Authorization: `Bearer ${this.apiKey}`,
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        personalizations: [
          {
            to: [{ email: to }],
            dynamic_template_data: dynamicData,
          },
        ],
        from: { email: import.meta.env.VITE_FROM_EMAIL },
        template_id: templateId,
      }),
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(`SendGrid error: ${error.message}`);
    }
  }
}
```

---

## Error Handling

### Standard Error Classes

**File**: `src/lib/errors.ts`

```typescript
/**
 * Base application error
 */
export class AppError extends Error {
  constructor(
    message: string,
    public code: string,
    public statusCode: number = 500,
    public details?: any,
  ) {
    super(message);
    this.name = "AppError";
  }

  toJSON() {
    return {
      code: this.code,
      message: this.message,
      details: this.details,
    };
  }
}

/**
 * Validation error
 */
export class ValidationError extends AppError {
  constructor(message: string, details?: any) {
    super(message, "VALIDATION_ERROR", 422, details);
    this.name = "ValidationError";
  }
}

/**
 * Authentication error
 */
export class AuthenticationError extends AppError {
  constructor(message: string = "Authentication required") {
    super(message, "AUTHENTICATION_ERROR", 401);
    this.name = "AuthenticationError";
  }
}

/**
 * Authorization error
 */
export class AuthorizationError extends AppError {
  constructor(message: string = "Insufficient permissions") {
    super(message, "AUTHORIZATION_ERROR", 403);
    this.name = "AuthorizationError";
  }
}

/**
 * Not found error
 */
export class NotFoundError extends AppError {
  constructor(resource: string, id?: string) {
    const message = id
      ? `${resource} with id ${id} not found`
      : `${resource} not found`;
    super(message, "NOT_FOUND", 404);
    this.name = "NotFoundError";
  }
}

/**
 * Conflict error
 */
export class ConflictError extends AppError {
  constructor(message: string) {
    super(message, "CONFLICT", 409);
    this.name = "ConflictError";
  }
}
```

### Error Handler Middleware

```typescript
// supabase/functions/_shared/error-handler.ts
export function handleError(error: unknown): Response {
  console.error("Error:", error);

  if (error instanceof AppError) {
    return new Response(
      JSON.stringify({
        success: false,
        error: error.toJSON(),
      }),
      {
        status: error.statusCode,
        headers: { "Content-Type": "application/json" },
      },
    );
  }

  // Unknown error
  return new Response(
    JSON.stringify({
      success: false,
      error: {
        code: "INTERNAL_ERROR",
        message: "An unexpected error occurred",
      },
    }),
    {
      status: 500,
      headers: { "Content-Type": "application/json" },
    },
  );
}
```

---

## Security Best Practices

### 1. Input Validation

- ✅ Validate all user input
- ✅ Use schema validation (Zod, Yup, etc.)
- ✅ Sanitize HTML inputs
- ✅ Validate file uploads

### 2. Authentication

- ✅ Use secure tokens (JWT with short expiry)
- ✅ Implement refresh token rotation
- ✅ Hash passwords with bcrypt (handled by Supabase)
- ✅ Use HTTPS only

### 3. Authorization

- ✅ Implement Row-Level Security
- ✅ Check permissions on every request
- ✅ Use principle of least privilege
- ✅ Validate resource ownership

### 4. Data Protection

- ✅ Encrypt sensitive data at rest
- ✅ Use prepared statements (prevent SQL injection)
- ✅ Implement rate limiting
- ✅ Log security events

### 5. API Security

- ✅ Use API keys for external services
- ✅ Implement CORS properly
- ✅ Validate webhook signatures
- ✅ Use environment variables for secrets

---

## Testing

### Unit Tests

```typescript
// tests/unit/project-service.test.ts
import { describe, it, expect, beforeEach } from "vitest";
import { projectQueries } from "../../src/db/queries/projects";

describe("Project Service", () => {
  beforeEach(async () => {
    // Clear test database
    await clearTestDatabase();
  });

  it("should create a project", async () => {
    const project = await projectQueries.create({
      name: "Test Project",
      description: "A test project",
      ownerId: "user-123",
    });

    expect(project.name).toBe("Test Project");
    expect(project.ownerId).toBe("user-123");
  });

  it("should get all projects for user", async () => {
    await projectQueries.create({
      name: "Project 1",
      ownerId: "user-123",
    });
    await projectQueries.create({
      name: "Project 2",
      ownerId: "user-123",
    });

    const projects = await projectQueries.getAllForUser("user-123");
    expect(projects).toHaveLength(2);
  });
});
```

### Integration Tests

```typescript
// tests/integration/api.test.ts
import { describe, it, expect } from "vitest";
import { createClient } from "@supabase/supabase-js";

describe("API Integration", () => {
  const supabase = createClient(
    process.env.TEST_SUPABASE_URL!,
    process.env.TEST_SUPABASE_KEY!,
  );

  it("should create project via API", async () => {
    // Sign in test user
    const {
      data: { session },
    } = await supabase.auth.signInWithPassword({
      email: "test@example.com",
      password: "password123",
    });

    // Call Edge Function
    const { data, error } = await supabase.functions.invoke("create-project", {
      body: {
        name: "Test Project",
        description: "Created via API",
      },
      headers: {
        Authorization: `Bearer ${session?.access_token}`,
      },
    });

    expect(error).toBeNull();
    expect(data.success).toBe(true);
    expect(data.data.name).toBe("Test Project");
  });
});
```

---

## Code Examples

### Complete Edge Function with Best Practices

```typescript
// supabase/functions/update-task/index.ts
import { serve } from "https://deno.land/std@0.168.0/http/server.ts";
import { createClient } from "https://esm.sh/@supabase/supabase-js@2";
import { corsHeaders } from "../_shared/cors.ts";
import { requireAuth } from "../_shared/auth.ts";
import { validate } from "../_shared/validation.ts";
import { handleError } from "../_shared/error-handler.ts";

interface UpdateTaskRequest {
  title?: string;
  description?: string;
  status?: string;
  priority?: number;
  dueDate?: string;
}

serve(async (req) => {
  if (req.method === "OPTIONS") {
    return new Response("ok", { headers: corsHeaders });
  }

  try {
    // 1. Authenticate
    const user = await requireAuth(req);

    // 2. Get task ID from URL
    const url = new URL(req.url);
    const taskId = url.pathname.split("/").pop();
    if (!taskId) {
      throw new Error("Task ID is required");
    }

    // 3. Parse and validate request
    const body: UpdateTaskRequest = await req.json();
    validate(body, {
      title: { type: "string", required: false, minLength: 1, maxLength: 255 },
      description: { type: "string", required: false },
      status: { type: "string", required: false },
      priority: { type: "number", required: false, min: 0, max: 10 },
      dueDate: { type: "string", required: false },
    });

    // 4. Create Supabase client
    const supabase = createClient(
      Deno.env.get("SUPABASE_URL")!,
      Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!,
    );

    // 5. Verify user owns the project (through task)
    const { data: task } = await supabase
      .from("tasks")
      .select("*, project:projects(owner_id)")
      .eq("id", taskId)
      .single();

    if (!task || task.project.owner_id !== user.id) {
      throw new Error("Task not found or access denied");
    }

    // 6. Update task
    const { data: updatedTask, error } = await supabase
      .from("tasks")
      .update(body)
      .eq("id", taskId)
      .select()
      .single();

    if (error) throw error;

    // 7. Return success
    return new Response(
      JSON.stringify({
        success: true,
        data: updatedTask,
      }),
      {
        headers: {
          ...corsHeaders,
          "Content-Type": "application/json",
        },
        status: 200,
      },
    );
  } catch (error) {
    return handleError(error);
  }
});
```

---

## Guardrails for AI Agents

When generating backend code, AI agents MUST:

### ✅ ALWAYS

- Use abstraction layers in `/lib` for database and auth
- Create migrations for all schema changes
- Validate all inputs using schema validation
- Implement authentication on all endpoints
- Use environment variables for configuration
- Write portable, provider-agnostic SQL
- Handle errors with proper status codes
- Log errors for debugging
- Return consistent response formats

### ❌ NEVER

- Hardcode credentials or secrets
- Use provider-specific features without abstraction
- Skip input validation
- Expose sensitive data in responses
- Use synchronous blocking operations
- Return stack traces in production errors
- Skip authentication checks
- Write SQL that only works on Supabase

### Code Review Checklist

- [ ] Authentication implemented and tested?
- [ ] Input validation present?
- [ ] Database operations use abstraction layer?
- [ ] Error handling implemented?
- [ ] Environment variables used for config?
- [ ] RLS policies enabled and tested?
- [ ] API follows REST conventions?
- [ ] Code is portable to GCP?

---

**Last Updated**: 2024-01-07
**Maintained By**: Allia Engineering Team
**Version**: 1.0.0
