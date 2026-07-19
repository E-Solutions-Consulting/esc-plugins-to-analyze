# Frontend Development Best Practices

> **Purpose**: Comprehensive guidelines for frontend development including component architecture, state management, routing, styling, and design system integration.

---

## Table of Contents

1. [Frontend Architecture Overview](#frontend-architecture-overview)
2. [Project Structure](#project-structure)
3. [Component Patterns](#component-patterns)
4. [State Management](#state-management)
5. [Routing & Navigation](#routing--navigation)
6. [Design System Integration](#design-system-integration)
7. [Forms & Validation](#forms--validation)
8. [Data Fetching](#data-fetching)
9. [Performance Optimization](#performance-optimization)
10. [Accessibility](#accessibility)
11. [Testing](#testing)
12. [Code Examples](#code-examples)

---

## Frontend Architecture Overview

### Tech Stack

```
┌────────────────────────────────────┐
│         React + TypeScript         │
└────────────────┬───────────────────┘
                 │
        ┌────────┴────────┐
        │                 │
    ┌───▼────┐      ┌────▼────┐
    │  Vite  │      │ Tailwind│
    └───┬────┘      └────┬────┘
        │                │
        └────────┬───────┘
                 │
        ┌────────▼────────┐
        │  shadcn/ui      │
        │  Components     │
        └─────────────────┘
```

### Core Principles

1. **Component-Based**: Everything is a component
2. **Type Safety**: TypeScript for all code
3. **Accessibility First**: WCAG 2.1 AA compliance
4. **Performance**: Lazy loading, code splitting, optimization
5. **Responsive**: Mobile-first design approach
6. **Reusability**: DRY principle, composable components

---

## Project Structure

### Folder Organization

```
src/
├── components/                # React components
│   ├── ui/                   # Base UI components (shadcn/ui)
│   │   ├── button.tsx
│   │   ├── input.tsx
│   │   ├── card.tsx
│   │   └── ...
│   │
│   ├── features/             # Feature-specific components
│   │   ├── projects/
│   │   │   ├── ProjectCard.tsx
│   │   │   ├── ProjectList.tsx
│   │   │   └── CreateProjectForm.tsx
│   │   └── tasks/
│   │       ├── TaskItem.tsx
│   │       └── TaskBoard.tsx
│   │
│   └── layouts/              # Layout components
│       ├── AppLayout.tsx
│       ├── AuthLayout.tsx
│       └── PublicLayout.tsx
│
├── pages/                    # Route pages
│   ├── auth/
│   │   ├── SignIn.tsx
│   │   ├── SignUp.tsx
│   │   └── ResetPassword.tsx
│   │
│   ├── app/                  # Protected app pages
│   │   ├── Dashboard.tsx
│   │   ├── Projects.tsx
│   │   └── Settings.tsx
│   │
│   └── public/               # Public pages
│       ├── Home.tsx
│       └── About.tsx
│
├── hooks/                    # Custom React hooks
│   ├── useAuth.ts
│   ├── useProjects.ts
│   ├── useTasks.ts
│   └── useDebounce.ts
│
├── lib/                      # Core utilities
│   ├── auth.ts
│   ├── db.ts
│   ├── api.ts
│   └── utils.ts
│
├── types/                    # TypeScript types
│   ├── models.ts
│   ├── api.ts
│   └── components.ts
│
├── config/                   # Configuration
│   ├── env.ts
│   ├── routes.ts
│   └── constants.ts
│
├── styles/                   # Global styles
│   ├── index.css
│   └── themes.css
│
└── App.tsx                   # Root component
```

---

## Component Patterns

### Component Types

#### 1. Presentational Components (Pure UI)

```typescript
// src/components/features/projects/ProjectCard.tsx
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { dateTime } from '@/lib/dayjs';

interface ProjectCardProps {
  project: {
    id: string;
    name: string;
    description: string;
    createdAt: string;
  };
  onEdit: (id: string) => void;
  onDelete: (id: string) => void;
}

export function ProjectCard({ project, onEdit, onDelete }: ProjectCardProps) {
  return (
    <Card className="hover:shadow-lg transition-shadow">
      <CardHeader>
        <CardTitle>{project.name}</CardTitle>
        <CardDescription>
          Created {dateTime(project.createdAt).format('MM/DD/YYYY')}
        </CardDescription>
      </CardHeader>
      <CardContent>
        <p className="text-sm text-muted-foreground mb-4">
          {project.description}
        </p>
        <div className="flex gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => onEdit(project.id)}
          >
            Edit
          </Button>
          <Button
            variant="destructive"
            size="sm"
            onClick={() => onDelete(project.id)}
          >
            Delete
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}
```

#### 2. Container Components (Logic + Data)

```typescript
// src/components/features/projects/ProjectList.tsx
import { useProjects } from '@/hooks/useProjects';
import { ProjectCard } from './ProjectCard';
import { Skeleton } from '@/components/ui/skeleton';
import { Alert, AlertDescription } from '@/components/ui/alert';

export function ProjectList() {
  const {
    projects,
    isLoading,
    error,
    deleteProject
  } = useProjects();

  if (isLoading) {
    return (
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {[1, 2, 3].map((i) => (
          <Skeleton key={i} className="h-48" />
        ))}
      </div>
    );
  }

  if (error) {
    return (
      <Alert variant="destructive">
        <AlertDescription>
          Failed to load projects. Please try again.
        </AlertDescription>
      </Alert>
    );
  }

  if (projects.length === 0) {
    return (
      <div className="text-center py-12">
        <p className="text-muted-foreground">No projects yet.</p>
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      {projects.map((project) => (
        <ProjectCard
          key={project.id}
          project={project}
          onEdit={(id) => console.log('Edit', id)}
          onDelete={deleteProject}
        />
      ))}
    </div>
  );
}
```

#### 3. Layout Components

```typescript
// src/components/layouts/AppLayout.tsx
import { Outlet } from 'react-router-dom';
import { AppSidebar } from './AppSidebar';
import { AppHeader } from './AppHeader';

export function AppLayout() {
  return (
    <div className="flex h-screen overflow-hidden">
      {/* Sidebar */}
      <AppSidebar />

      {/* Main content */}
      <div className="flex-1 flex flex-col overflow-hidden">
        <AppHeader />

        <main className="flex-1 overflow-y-auto p-6">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
```

### Component Best Practices

#### ✅ DO

```typescript
// ✅ Small, focused components
function UserAvatar({ user }: { user: User }) {
  return <img src={user.avatarUrl} alt={user.name} />;
}

// ✅ Prop destructuring
function Button({ label, onClick, disabled }: ButtonProps) {
  // ...
}

// ✅ Default props via destructuring
function Card({ title, description = '' }: CardProps) {
  // ...
}

// ✅ TypeScript interfaces
interface UserCardProps {
  user: User;
  onEdit: (id: string) => void;
}

// ✅ Composition over prop drilling
function UserProfile() {
  return (
    <Card>
      <Card.Header>
        <UserAvatar />
      </Card.Header>
      <Card.Body>
        <UserDetails />
      </Card.Body>
    </Card>
  );
}
```

#### ❌ DON'T

{% raw %}
```typescript
// ❌ Giant monolithic components
function Dashboard() {
  // 500 lines of code...
}

// ❌ Prop drilling through many levels
<A userData={user}>
  <B userData={user}>
    <C userData={user}>
      <D userData={user} />
    </C>
  </B>
</A>

// ❌ Inline styles (use Tailwind classes)
<div style={{ backgroundColor: 'red' }}>

// ❌ Any type
function Component({ data }: { data: any }) {

// ❌ Direct state mutation
setUser(user.name = 'New Name');
```
{% endraw %}

### Date & Time Handling

- Always use the shared Day.js helper from `src/lib/dayjs.ts` (`import { dateTime } from '@/lib/dayjs';`).
- Do not instantiate native Date objects directly in components; use the shared `dateTime` helper for formatting and calculations.
- Default to UTC when persisting or comparing timestamps; convert to the user's timezone only for display.
- Prefer format tokens that match UX copy (e.g., `MMM D, YYYY`, `MMM D, YYYY h:mm A`).
- Keep parsing/formatting logic in helpers when reused; keep components declarative.

```typescript
// ✅ Displaying a timestamp
const createdLabel = dateTime(order.created_at)
  .tz()
  .format("MMM D, YYYY h:mm A");

// ✅ Sorting by time
const sorted = orders
  .slice()
  .sort(
    (a, b) =>
      dateTime(a.created_at).valueOf() - dateTime(b.created_at).valueOf(),
  );

// ✅ Building ISO strings for APIs
const nowIso = dateTime().utc().toISOString();
```

---

## State Management

### Local State (useState)

```typescript
// For component-specific state
function Counter() {
  const [count, setCount] = useState(0);

  return (
    <div>
      <p>Count: {count}</p>
      <button onClick={() => setCount(count + 1)}>Increment</button>
    </div>
  );
}
```

### Auth Store (Zustand)

```typescript
// src/stores/authStore.ts
import { create } from 'zustand';

interface AuthStoreType {
  user: AdminUser | null;
  session: Session | null;
  tenants: TenantMembership[];
  currentTenantId: string | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  signIn: (email: string, password: string) => Promise<void>;
  signUp: (email: string, password: string, fullName: string) => Promise<void>;
  signOut: () => Promise<void>;
  switchTenant: (tenantId: string) => void;
}

export const useAuthStore = create<AuthStoreType>(() => ({
  user: null,
  session: null,
  tenants: [],
  currentTenantId: null,
  isLoading: true,
  isAuthenticated: false,
  signIn: async () => {},
  signUp: async () => {},
  signOut: async () => {},
  switchTenant: () => {},
}));

export function useAuth() {
  return useAuthStore();
}
```

### Custom Hooks for State Logic

```typescript
// src/hooks/useProjects.ts
import { useState, useEffect } from "react";
import { projectQueries } from "@/db/queries/projects";
import { useAuth } from "@/stores/authStore";

export function useProjects() {
  const { user } = useAuth();
  const [projects, setProjects] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);

  useEffect(() => {
    if (!user) return;

    loadProjects();
  }, [user]);

  const loadProjects = async () => {
    try {
      setIsLoading(true);
      const data = await projectQueries.getAllForUser(user!.id);
      setProjects(data);
    } catch (err) {
      setError(err as Error);
    } finally {
      setIsLoading(false);
    }
  };

  const createProject = async (data: {
    name: string;
    description?: string;
  }) => {
    const project = await projectQueries.create({
      ...data,
      ownerId: user!.id,
    });
    setProjects([...projects, project]);
    return project;
  };

  const deleteProject = async (id: string) => {
    await projectQueries.delete(id, user!.id);
    setProjects(projects.filter((p) => p.id !== id));
  };

  return {
    projects,
    isLoading,
    error,
    createProject,
    deleteProject,
    refetch: loadProjects,
  };
}
```

---

## Routing & Navigation

### Route Configuration

```typescript
// src/config/routes.ts
export const routes = {
  home: "/",
  signIn: "/auth/sign-in",
  signUp: "/auth/sign-up",
  dashboard: "/app/dashboard",
  projects: "/app/projects",
  project: (id: string) => `/app/projects/${id}`,
  settings: "/app/settings",
};
```

### Router Setup

```typescript
// src/App.tsx
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthInitializer } from './components/auth/AuthInitializer';
import { ProtectedRoute } from './components/auth/ProtectedRoute';

// Layouts
import { PublicLayout } from './components/layouts/PublicLayout';
import { AuthLayout } from './components/layouts/AuthLayout';
import { AppLayout } from './components/layouts/AppLayout';

// Pages
import { Home } from './pages/public/Home';
import { SignIn } from './pages/auth/SignIn';
import { SignUp } from './pages/auth/SignUp';
import { Dashboard } from './pages/app/Dashboard';
import { Projects } from './pages/app/Projects';

function App() {
  return (
    <AuthInitializer>
      <BrowserRouter>
        <Routes>
          {/* Public routes */}
          <Route element={<PublicLayout />}>
            <Route path="/" element={<Home />} />
          </Route>

          {/* Auth routes */}
          <Route element={<AuthLayout />}>
            <Route path="/auth/sign-in" element={<SignIn />} />
            <Route path="/auth/sign-up" element={<SignUp />} />
          </Route>

          {/* Protected routes */}
          <Route element={<ProtectedRoute />}>
            <Route element={<AppLayout />}>
              <Route path="/app/dashboard" element={<Dashboard />} />
              <Route path="/app/projects" element={<Projects />} />
            </Route>
          </Route>

          {/* 404 */}
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthInitializer>
  );
}

export default App;
```

### Protected Route Component

```typescript
// src/components/auth/ProtectedRoute.tsx
import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '@/stores/authStore';
import { Skeleton } from '@/components/ui/skeleton';

export function ProtectedRoute() {
  const { isAuthenticated, isLoading } = useAuth();

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-screen">
        <Skeleton className="w-64 h-64" />
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/auth/sign-in" replace />;
  }

  return <Outlet />;
}
```

---

## Design System Integration

### Theme Configuration

```css
/* src/styles/index.css */
@tailwind base;
@tailwind components;
@tailwind utilities;

@layer base {
  :root {
    /* Light theme */
    --background: 0 0% 100%;
    --foreground: 222.2 84% 4.9%;
    --card: 0 0% 100%;
    --card-foreground: 222.2 84% 4.9%;
    --primary: 226 70% 55%;
    --primary-foreground: 210 40% 98%;
    --secondary: 210 40% 96.1%;
    --secondary-foreground: 222.2 47.4% 11.2%;
    --muted: 210 40% 96.1%;
    --muted-foreground: 215.4 16.3% 46.9%;
    --accent: 210 40% 96.1%;
    --accent-foreground: 222.2 47.4% 11.2%;
    --destructive: 0 84.2% 60.2%;
    --destructive-foreground: 210 40% 98%;
    --border: 214.3 31.8% 91.4%;
    --input: 214.3 31.8% 91.4%;
    --ring: 226 70% 55%;
    --radius: 0.5rem;
  }

  .dark {
    /* Dark theme */
    --background: 222.2 84% 4.9%;
    --foreground: 210 40% 98%;
    --card: 222.2 84% 4.9%;
    --card-foreground: 210 40% 98%;
    --primary: 226 70% 55%;
    --primary-foreground: 222.2 47.4% 11.2%;
    --secondary: 217.2 32.6% 17.5%;
    --secondary-foreground: 210 40% 98%;
    --muted: 217.2 32.6% 17.5%;
    --muted-foreground: 215 20.2% 65.1%;
    --accent: 217.2 32.6% 17.5%;
    --accent-foreground: 210 40% 98%;
    --destructive: 0 62.8% 30.6%;
    --destructive-foreground: 210 40% 98%;
    --border: 217.2 32.6% 17.5%;
    --input: 217.2 32.6% 17.5%;
    --ring: 226 70% 55%;
  }
}

@layer base {
  * {
    @apply border-border;
  }
  body {
    @apply bg-background text-foreground;
    font-feature-settings:
      "rlig" 1,
      "calt" 1;
  }
}
```

### Theme Toggle

```typescript
// src/components/ThemeToggle.tsx
import { Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTheme } from '@/hooks/useTheme';

export function ThemeToggle() {
  const { theme, setTheme } = useTheme();

  return (
    <Button
      variant="outline"
      size="icon"
      onClick={() => setTheme(theme === 'light' ? 'dark' : 'light')}
    >
      <Sun className="h-5 w-5 rotate-0 scale-100 transition-all dark:-rotate-90 dark:scale-0" />
      <Moon className="absolute h-5 w-5 rotate-90 scale-0 transition-all dark:rotate-0 dark:scale-100" />
      <span className="sr-only">Toggle theme</span>
    </Button>
  );
}

// src/hooks/useTheme.ts
import { useEffect, useState } from 'react';

export function useTheme() {
  const [theme, setThemeState] = useState<'light' | 'dark'>(() => {
    if (typeof window !== 'undefined') {
      return (localStorage.getItem('theme') as 'light' | 'dark') || 'light';
    }
    return 'light';
  });

  useEffect(() => {
    const root = window.document.documentElement;
    root.classList.remove('light', 'dark');
    root.classList.add(theme);
    localStorage.setItem('theme', theme);
  }, [theme]);

  return { theme, setTheme: setThemeState };
}
```

### Responsive Design

```typescript
// Mobile-first approach with Tailwind breakpoints
<div className="
  grid
  grid-cols-1           /* Mobile: 1 column */
  md:grid-cols-2        /* Tablet: 2 columns */
  lg:grid-cols-3        /* Desktop: 3 columns */
  xl:grid-cols-4        /* Large: 4 columns */
  gap-4
">
  {items.map(item => <Card key={item.id} {...item} />)}
</div>

// Responsive text
<h1 className="text-2xl md:text-3xl lg:text-4xl font-bold">
  Heading
</h1>

// Responsive padding
<div className="p-4 md:p-6 lg:p-8">
  Content
</div>
```

---

## Forms & Validation

### Form Component with react-hook-form + zod

```typescript
// src/components/features/projects/CreateProjectForm.tsx
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';

// Validation schema
const projectSchema = z.object({
  name: z.string()
    .min(1, 'Name is required')
    .max(255, 'Name must be less than 255 characters'),
  description: z.string()
    .max(1000, 'Description must be less than 1000 characters')
    .optional()
});

type ProjectFormData = z.infer<typeof projectSchema>;

interface CreateProjectFormProps {
  onSubmit: (data: ProjectFormData) => Promise<void>;
  onCancel: () => void;
}

export function CreateProjectForm({ onSubmit, onCancel }: CreateProjectFormProps) {
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting }
  } = useForm<ProjectFormData>({
    resolver: zodResolver(projectSchema)
  });

  const [error, setError] = useState<string | null>(null);

  const handleFormSubmit = async (data: ProjectFormData) => {
    try {
      setError(null);
      await onSubmit(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    }
  };

  return (
    <form onSubmit={handleSubmit(handleFormSubmit)} className="space-y-4">
      {error && (
        <Alert variant="destructive">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      <div className="space-y-2">
        <Label htmlFor="name">Project Name</Label>
        <Input
          id="name"
          {...register('name')}
          placeholder="Enter project name"
          disabled={isSubmitting}
        />
        {errors.name && (
          <p className="text-sm text-destructive">{errors.name.message}</p>
        )}
      </div>

      <div className="space-y-2">
        <Label htmlFor="description">Description (Optional)</Label>
        <Textarea
          id="description"
          {...register('description')}
          placeholder="Enter project description"
          rows={4}
          disabled={isSubmitting}
        />
        {errors.description && (
          <p className="text-sm text-destructive">{errors.description.message}</p>
        )}
      </div>

      <div className="flex gap-2 justify-end">
        <Button
          type="button"
          variant="outline"
          onClick={onCancel}
          disabled={isSubmitting}
        >
          Cancel
        </Button>
        <Button type="submit" disabled={isSubmitting}>
          {isSubmitting ? 'Creating...' : 'Create Project'}
        </Button>
      </div>
    </form>
  );
}
```

---

## Data Fetching

### Using React Query (TanStack Query)

```typescript
// src/hooks/useProjectsQuery.ts
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { projectQueries } from "@/db/queries/projects";
import { useAuth } from "@/stores/authStore";

export function useProjectsQuery() {
  const { user } = useAuth();
  const queryClient = useQueryClient();

  // Fetch projects
  const {
    data: projects,
    isLoading,
    error,
  } = useQuery({
    queryKey: ["projects", user?.id],
    queryFn: () => projectQueries.getAllForUser(user!.id),
    enabled: !!user,
    staleTime: 5 * 60 * 1000, // 5 minutes
  });

  // Create project mutation
  const createMutation = useMutation({
    mutationFn: (data: { name: string; description?: string }) =>
      projectQueries.create({ ...data, ownerId: user!.id }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["projects", user?.id] });
    },
  });

  // Delete project mutation
  const deleteMutation = useMutation({
    mutationFn: (projectId: string) =>
      projectQueries.delete(projectId, user!.id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["projects", user?.id] });
    },
  });

  return {
    projects: projects || [],
    isLoading,
    error,
    createProject: createMutation.mutateAsync,
    deleteProject: deleteMutation.mutateAsync,
  };
}
```

---

## Performance Optimization

### Code Splitting & Lazy Loading

```typescript
// Lazy load pages
import { lazy, Suspense } from 'react';
import { Skeleton } from '@/components/ui/skeleton';

const Dashboard = lazy(() => import('./pages/app/Dashboard'));
const Projects = lazy(() => import('./pages/app/Projects'));

function App() {
  return (
    <Suspense fallback={<Skeleton className="w-full h-screen" />}>
      <Routes>
        <Route path="/app/dashboard" element={<Dashboard />} />
        <Route path="/app/projects" element={<Projects />} />
      </Routes>
    </Suspense>
  );
}
```

### Memoization

```typescript
import { memo, useMemo, useCallback } from 'react';

// Memo component
export const ProjectCard = memo(({ project, onEdit, onDelete }) => {
  return (
    // Component JSX
  );
});

// useMemo for expensive calculations
function ProjectList({ projects }: { projects: Project[] }) {
  const sortedProjects = useMemo(() => {
    return projects.sort((a, b) =>
      dateTime(b.createdAt).valueOf() - dateTime(a.createdAt).valueOf()
    );
  }, [projects]);

  return (
    // JSX
  );
}

// useCallback for function references
function Parent() {
  const handleEdit = useCallback((id: string) => {
    console.log('Edit', id);
  }, []);

  return <Child onEdit={handleEdit} />;
}
```

### Image Optimization

```typescript
// Lazy load images
<img
  src={project.imageUrl}
  alt={project.name}
  loading="lazy"
  className="w-full h-48 object-cover"
/>

// Responsive images
<picture>
  <source
    media="(min-width: 1024px)"
    srcSet={`${image}-large.webp`}
    type="image/webp"
  />
  <source
    media="(min-width: 768px)"
    srcSet={`${image}-medium.webp`}
    type="image/webp"
  />
  <img
    src={`${image}-small.webp`}
    alt="Project"
    loading="lazy"
  />
</picture>
```

---

## Accessibility

### ARIA Labels & Semantic HTML

```typescript
// ✅ Good accessibility
<nav aria-label="Main navigation">
  <ul>
    <li><a href="/home">Home</a></li>
    <li><a href="/about">About</a></li>
  </ul>
</nav>

<button
  aria-label="Close modal"
  onClick={handleClose}
>
  <X className="h-4 w-4" />
</button>

<img
  src={user.avatar}
  alt={`${user.name}'s avatar`}
/>

// ❌ Bad accessibility
<div onClick={handleClick}>Click me</div>  // Should be <button>
<img src={logo} />  // Missing alt text
<button><X /></button>  // No label for icon-only button
```

### Keyboard Navigation

```typescript
function Dialog({ onClose }: { onClose: () => void }) {
  const dialogRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        onClose();
      }
    };

    // Focus trap
    const focusableElements = dialogRef.current?.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [onClose]);

  return (
    <div
      ref={dialogRef}
      role="dialog"
      aria-modal="true"
      aria-labelledby="dialog-title"
    >
      {/* Dialog content */}
    </div>
  );
}
```

---

## Testing

### Component Tests (Vitest + React Testing Library)

```typescript
// src/components/features/projects/ProjectCard.test.tsx
import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { ProjectCard } from './ProjectCard';

describe('ProjectCard', () => {
  const mockProject = {
    id: '123',
    name: 'Test Project',
    description: 'A test project',
    createdAt: '2024-01-07T12:00:00Z'
  };

  it('renders project details', () => {
    render(
      <ProjectCard
        project={mockProject}
        onEdit={() => {}}
        onDelete={() => {}}
      />
    );

    expect(screen.getByText('Test Project')).toBeInTheDocument();
    expect(screen.getByText('A test project')).toBeInTheDocument();
  });

  it('calls onEdit when edit button is clicked', () => {
    const handleEdit = vi.fn();
    render(
      <ProjectCard
        project={mockProject}
        onEdit={handleEdit}
        onDelete={() => {}}
      />
    );

    fireEvent.click(screen.getByText('Edit'));
    expect(handleEdit).toHaveBeenCalledWith('123');
  });
});
```

---

## Guardrails for AI Agents

### ✅ ALWAYS

- Use TypeScript with strict types
- Follow component structure (presentational vs container)
- Use shadcn/ui components from `/components/ui`
- Implement responsive design (mobile-first)
- Add proper ARIA labels for accessibility
- Validate forms with zod + react-hook-form
- Use Tailwind classes (no inline styles)
- Implement error boundaries
- Add loading states
- Handle empty states

### ❌ NEVER

- Use `any` type
- Modify shadcn/ui base components
- Use inline styles
- Skip accessibility attributes
- Hard-code API URLs (use environment variables)
- Create giant monolithic components
- Prop drill through many levels
- Skip error handling
- Forget loading states
- Ignore mobile responsiveness

### Code Review Checklist

- [ ] TypeScript types defined?
- [ ] Component is properly sized (< 200 lines)?
- [ ] Accessibility attributes present?
- [ ] Responsive design implemented?
- [ ] Error states handled?
- [ ] Loading states shown?
- [ ] Form validation present?
- [ ] Follows design system?
- [ ] No inline styles?
- [ ] Performance optimized?

---

**Last Updated**: 2024-01-07
**Maintained By**: Allia Engineering Team
**Version**: 1.0.0
