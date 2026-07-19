# Design System Best Practices

> **Purpose**: Comprehensive design guidelines including design tokens, component patterns, typography, spacing, color systems, and integration with designer workflows.

---

## Table of Contents

1. [Design System Overview](#design-system-overview)
2. [Design Tokens](#design-tokens)
3. [Color System](#color-system)
4. [Typography](#typography)
5. [Spacing & Layout](#spacing--layout)
6. [Component Library](#component-library)
7. [Design Kit Template](#design-kit-template)
8. [Designer Integration](#designer-integration)
9. [Responsive Design](#responsive-design)
10. [Accessibility](#accessibility)
11. [Design Guardrails](#design-guardrails)

---

## Design System Overview

### Foundation Stack

```
┌─────────────────────────────────────┐
│      Figma/Design Tool              │
│   (Source of Truth for Designers)   │
└──────────────┬──────────────────────┘
               │
               │ Export Design Tokens
               ▼
┌─────────────────────────────────────┐
│      Design Tokens (CSS Variables)  │
│   Colors, Typography, Spacing, etc. │
└──────────────┬──────────────────────┘
               │
               │ Applied to
               ▼
┌─────────────────────────────────────┐
│         Tailwind Config             │
│    (Extends with Design Tokens)     │
└──────────────┬──────────────────────┘
               │
               │ Used by
               ▼
┌─────────────────────────────────────┐
│      shadcn/ui Components           │
│   (Built with Tailwind + Tokens)    │
└─────────────────────────────────────┘
```

### Core Principles

1. **Consistency**: Single source of truth for design decisions
2. **Scalability**: Design tokens make changes propagate automatically
3. **Accessibility**: WCAG 2.1 AA compliance minimum
4. **Flexibility**: Easy to customize per brand
5. **Developer-Friendly**: Design tokens in code, not hardcoded values

---

## Design Tokens

### Token Structure

Design tokens are the foundational values that define your design system. They are stored as CSS custom properties.

```css
/* src/styles/tokens.css */

:root {
  /* === COLORS === */

  /* Brand Colors */
  --color-brand-primary: 226 70% 55%;
  --color-brand-secondary: 210 40% 96.1%;
  --color-brand-accent: 45 93% 58%;

  /* Semantic Colors */
  --color-success: 142 76% 36%;
  --color-warning: 38 92% 50%;
  --color-error: 0 84.2% 60.2%;
  --color-info: 199 89% 48%;

  /* Neutral Colors */
  --color-background: 0 0% 100%;
  --color-foreground: 222.2 84% 4.9%;
  --color-muted: 210 40% 96.1%;
  --color-muted-foreground: 215.4 16.3% 46.9%;
  --color-border: 214.3 31.8% 91.4%;

  /* === TYPOGRAPHY === */

  /* Font Families */
  --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  --font-mono: 'JetBrains Mono', 'Fira Code', 'Consolas', monospace;

  /* Font Sizes */
  --text-xs: 0.75rem;      /* 12px */
  --text-sm: 0.875rem;     /* 14px */
  --text-base: 1rem;       /* 16px */
  --text-lg: 1.125rem;     /* 18px */
  --text-xl: 1.25rem;      /* 20px */
  --text-2xl: 1.5rem;      /* 24px */
  --text-3xl: 1.875rem;    /* 30px */
  --text-4xl: 2.25rem;     /* 36px */
  --text-5xl: 3rem;        /* 48px */

  /* Font Weights */
  --font-normal: 400;
  --font-medium: 500;
  --font-semibold: 600;
  --font-bold: 700;

  /* Line Heights */
  --leading-none: 1;
  --leading-tight: 1.25;
  --leading-normal: 1.5;
  --leading-relaxed: 1.75;

  /* === SPACING === */
  --space-0: 0;
  --space-1: 0.25rem;      /* 4px */
  --space-2: 0.5rem;       /* 8px */
  --space-3: 0.75rem;      /* 12px */
  --space-4: 1rem;         /* 16px */
  --space-5: 1.25rem;      /* 20px */
  --space-6: 1.5rem;       /* 24px */
  --space-8: 2rem;         /* 32px */
  --space-10: 2.5rem;      /* 40px */
  --space-12: 3rem;        /* 48px */
  --space-16: 4rem;        /* 64px */

  /* === LAYOUT === */
  --radius-none: 0;
  --radius-sm: 0.125rem;   /* 2px */
  --radius-base: 0.25rem;  /* 4px */
  --radius-md: 0.375rem;   /* 6px */
  --radius-lg: 0.5rem;     /* 8px */
  --radius-xl: 0.75rem;    /* 12px */
  --radius-2xl: 1rem;      /* 16px */
  --radius-full: 9999px;

  /* Shadows */
  --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
  --shadow-base: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
  --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
  --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
  --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);

  /* === ANIMATION === */
  --transition-fast: 150ms;
  --transition-base: 200ms;
  --transition-slow: 300ms;

  --ease-in: cubic-bezier(0.4, 0, 1, 1);
  --ease-out: cubic-bezier(0, 0, 0.2, 1);
  --ease-in-out: cubic-bezier(0.4, 0, 0.2, 1);
}

/* Dark Theme Overrides */
.dark {
  --color-background: 222.2 84% 4.9%;
  --color-foreground: 210 40% 98%;
  --color-muted: 217.2 32.6% 17.5%;
  --color-muted-foreground: 215 20.2% 65.1%;
  --color-border: 217.2 32.6% 17.5%;
}
```

### Tailwind Integration

```javascript
// tailwind.config.ts
import type { Config } from 'tailwindcss';

export default {
  darkMode: ['class'],
  content: [
    './pages/**/*.{ts,tsx}',
    './components/**/*.{ts,tsx}',
    './app/**/*.{ts,tsx}',
    './src/**/*.{ts,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        border: 'hsl(var(--color-border))',
        background: 'hsl(var(--color-background))',
        foreground: 'hsl(var(--color-foreground))',
        primary: {
          DEFAULT: 'hsl(var(--color-brand-primary))',
        },
        secondary: {
          DEFAULT: 'hsl(var(--color-brand-secondary))',
        },
        success: 'hsl(var(--color-success))',
        warning: 'hsl(var(--color-warning))',
        error: 'hsl(var(--color-error))',
        info: 'hsl(var(--color-info))',
        muted: {
          DEFAULT: 'hsl(var(--color-muted))',
          foreground: 'hsl(var(--color-muted-foreground))',
        },
      },
      fontFamily: {
        sans: ['var(--font-sans)'],
        mono: ['var(--font-mono)'],
      },
      fontSize: {
        xs: 'var(--text-xs)',
        sm: 'var(--text-sm)',
        base: 'var(--text-base)',
        lg: 'var(--text-lg)',
        xl: 'var(--text-xl)',
        '2xl': 'var(--text-2xl)',
        '3xl': 'var(--text-3xl)',
        '4xl': 'var(--text-4xl)',
        '5xl': 'var(--text-5xl)',
      },
      spacing: {
        '0': 'var(--space-0)',
        '1': 'var(--space-1)',
        '2': 'var(--space-2)',
        '3': 'var(--space-3)',
        '4': 'var(--space-4)',
        '5': 'var(--space-5)',
        '6': 'var(--space-6)',
        '8': 'var(--space-8)',
        '10': 'var(--space-10)',
        '12': 'var(--space-12)',
        '16': 'var(--space-16)',
      },
      borderRadius: {
        none: 'var(--radius-none)',
        sm: 'var(--radius-sm)',
        DEFAULT: 'var(--radius-base)',
        md: 'var(--radius-md)',
        lg: 'var(--radius-lg)',
        xl: 'var(--radius-xl)',
        '2xl': 'var(--radius-2xl)',
        full: 'var(--radius-full)',
      },
      boxShadow: {
        sm: 'var(--shadow-sm)',
        DEFAULT: 'var(--shadow-base)',
        md: 'var(--shadow-md)',
        lg: 'var(--shadow-lg)',
        xl: 'var(--shadow-xl)',
      },
      transitionDuration: {
        fast: 'var(--transition-fast)',
        DEFAULT: 'var(--transition-base)',
        slow: 'var(--transition-slow)',
      },
      transitionTimingFunction: {
        'in': 'var(--ease-in)',
        'out': 'var(--ease-out)',
        'in-out': 'var(--ease-in-out)',
      },
    },
  },
  plugins: [require('tailwindcss-animate')],
} satisfies Config;
```

---

## Color System

### Color Palette Structure

```typescript
// src/config/colors.ts
export const colorPalette = {
  // Primary brand color
  primary: {
    50: 'hsl(226, 70%, 95%)',
    100: 'hsl(226, 70%, 90%)',
    200: 'hsl(226, 70%, 80%)',
    300: 'hsl(226, 70%, 70%)',
    400: 'hsl(226, 70%, 60%)',
    500: 'hsl(226, 70%, 55%)',  // Default
    600: 'hsl(226, 70%, 50%)',
    700: 'hsl(226, 70%, 45%)',
    800: 'hsl(226, 70%, 35%)',
    900: 'hsl(226, 70%, 25%)',
  },

  // Neutrals (grays)
  neutral: {
    50: 'hsl(210, 40%, 98%)',
    100: 'hsl(210, 40%, 96%)',
    200: 'hsl(214, 32%, 91%)',
    300: 'hsl(213, 27%, 84%)',
    400: 'hsl(215, 20%, 65%)',
    500: 'hsl(215, 16%, 47%)',
    600: 'hsl(215, 19%, 35%)',
    700: 'hsl(215, 25%, 27%)',
    800: 'hsl(217, 33%, 17%)',
    900: 'hsl(222, 47%, 11%)',
  },

  // Semantic colors
  semantic: {
    success: 'hsl(142, 76%, 36%)',
    warning: 'hsl(38, 92%, 50%)',
    error: 'hsl(0, 84%, 60%)',
    info: 'hsl(199, 89%, 48%)',
  },
};
```

### Color Usage Guidelines

{% raw %}
```tsx
// ✅ Good: Using semantic color names
<Button variant="primary">Submit</Button>
<Alert variant="error">Error message</Alert>
<Badge variant="success">Active</Badge>

// ✅ Good: Using Tailwind color utilities
<div className="bg-primary text-white">Primary background</div>
<p className="text-muted-foreground">Muted text</p>

// ❌ Bad: Hardcoding colors
<div style={{ backgroundColor: '#4f46e5' }}>Don't do this</div>
<p style={{ color: 'blue' }}>Avoid inline colors</p>
```
{% endraw %}

### Color Contrast Checker

```typescript
// src/lib/color-utils.ts

/**
 * Calculate relative luminance of a color
 */
function getLuminance(r: number, g: number, b: number): number {
  const [rs, gs, bs] = [r, g, b].map((c) => {
    c = c / 255;
    return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
  });
  return 0.2126 * rs + 0.7152 * gs + 0.0722 * bs;
}

/**
 * Calculate contrast ratio between two colors
 */
export function getContrastRatio(
  color1: [number, number, number],
  color2: [number, number, number]
): number {
  const lum1 = getLuminance(...color1);
  const lum2 = getLuminance(...color2);
  const lighter = Math.max(lum1, lum2);
  const darker = Math.min(lum1, lum2);
  return (lighter + 0.05) / (darker + 0.05);
}

/**
 * Check if color combination meets WCAG AA standard
 */
export function meetsWCAG_AA(
  foreground: [number, number, number],
  background: [number, number, number],
  isLargeText: boolean = false
): boolean {
  const ratio = getContrastRatio(foreground, background);
  return isLargeText ? ratio >= 3 : ratio >= 4.5;
}
```

---

## Typography

### Type Scale

```css
/* Heading Styles */
.heading-1 {
  font-size: var(--text-5xl);
  font-weight: var(--font-bold);
  line-height: var(--leading-tight);
  letter-spacing: -0.02em;
}

.heading-2 {
  font-size: var(--text-4xl);
  font-weight: var(--font-bold);
  line-height: var(--leading-tight);
  letter-spacing: -0.01em;
}

.heading-3 {
  font-size: var(--text-3xl);
  font-weight: var(--font-semibold);
  line-height: var(--leading-tight);
}

.heading-4 {
  font-size: var(--text-2xl);
  font-weight: var(--font-semibold);
  line-height: var(--leading-normal);
}

.heading-5 {
  font-size: var(--text-xl);
  font-weight: var(--font-medium);
  line-height: var(--leading-normal);
}

.heading-6 {
  font-size: var(--text-lg);
  font-weight: var(--font-medium);
  line-height: var(--leading-normal);
}

/* Body Styles */
.body-large {
  font-size: var(--text-lg);
  line-height: var(--leading-relaxed);
}

.body-base {
  font-size: var(--text-base);
  line-height: var(--leading-normal);
}

.body-small {
  font-size: var(--text-sm);
  line-height: var(--leading-normal);
}

.body-xs {
  font-size: var(--text-xs);
  line-height: var(--leading-normal);
}
```

### Typography Components

```tsx
// src/components/ui/typography.tsx
import { cn } from '@/lib/utils';

interface HeadingProps extends React.HTMLAttributes<HTMLHeadingElement> {
  level: 1 | 2 | 3 | 4 | 5 | 6;
}

export function Heading({ level, className, children, ...props }: HeadingProps) {
  const Tag = `h${level}` as keyof JSX.IntrinsicElements;

  const styles = {
    1: 'text-5xl font-bold tracking-tight',
    2: 'text-4xl font-bold tracking-tight',
    3: 'text-3xl font-semibold',
    4: 'text-2xl font-semibold',
    5: 'text-xl font-medium',
    6: 'text-lg font-medium',
  };

  return (
    <Tag className={cn(styles[level], className)} {...props}>
      {children}
    </Tag>
  );
}

interface TextProps extends React.HTMLAttributes<HTMLParagraphElement> {
  variant?: 'large' | 'base' | 'small' | 'xs';
  muted?: boolean;
}

export function Text({
  variant = 'base',
  muted = false,
  className,
  children,
  ...props
}: TextProps) {
  const styles = {
    large: 'text-lg leading-relaxed',
    base: 'text-base leading-normal',
    small: 'text-sm leading-normal',
    xs: 'text-xs leading-normal',
  };

  return (
    <p
      className={cn(
        styles[variant],
        muted && 'text-muted-foreground',
        className
      )}
      {...props}
    >
      {children}
    </p>
  );
}
```

---

## Spacing & Layout

### Spacing System

```typescript
// Spacing scale (in rem)
export const spacing = {
  0: '0',
  1: '0.25rem',   // 4px
  2: '0.5rem',    // 8px
  3: '0.75rem',   // 12px
  4: '1rem',      // 16px
  5: '1.25rem',   // 20px
  6: '1.5rem',    // 24px
  8: '2rem',      // 32px
  10: '2.5rem',   // 40px
  12: '3rem',     // 48px
  16: '4rem',     // 64px
  20: '5rem',     // 80px
  24: '6rem',     // 96px
};
```

### Layout Patterns

```tsx
// Container
<div className="container mx-auto px-4 sm:px-6 lg:px-8 max-w-7xl">
  {/* Content */}
</div>

// Two-column layout
<div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
  <div>Column 1</div>
  <div>Column 2</div>
</div>

// Card grid
<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
  {items.map(item => <Card key={item.id} {...item} />)}
</div>

// Stack (vertical spacing)
<div className="space-y-4">
  <div>Item 1</div>
  <div>Item 2</div>
  <div>Item 3</div>
</div>

// Flex layout
<div className="flex items-center justify-between gap-4">
  <div>Left</div>
  <div>Right</div>
</div>
```

---

## Component Library

### Component Variants

```typescript
// src/components/ui/button.tsx
import { cva, type VariantProps } from 'class-variance-authority';

const buttonVariants = cva(
  'inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 disabled:pointer-events-none disabled:opacity-50',
  {
    variants: {
      variant: {
        default: 'bg-primary text-white hover:bg-primary/90',
        secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
        outline: 'border border-input bg-background hover:bg-accent',
        ghost: 'hover:bg-accent hover:text-accent-foreground',
        destructive: 'bg-error text-white hover:bg-error/90',
      },
      size: {
        sm: 'h-9 px-3 text-xs',
        default: 'h-10 px-4 py-2',
        lg: 'h-11 px-8 text-base',
        icon: 'h-10 w-10',
      },
    },
    defaultVariants: {
      variant: 'default',
      size: 'default',
    },
  }
);

export interface ButtonProps
  extends React.ButtonHTMLAttributes<HTMLButtonElement>,
    VariantProps<typeof buttonVariants> {
  asChild?: boolean;
}

export const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant, size, ...props }, ref) => {
    return (
      <button
        className={cn(buttonVariants({ variant, size, className }))}
        ref={ref}
        {...props}
      />
    );
  }
);
```

---

## Design Kit Template

### Figma Structure

```
📁 Foundation Canvas Design Kit
├── 📄 Cover (Overview & Instructions)
├── 📄 Design Tokens
│   ├── Colors
│   ├── Typography
│   ├── Spacing
│   ├── Border Radius
│   └── Shadows
├── 📄 Components
│   ├── Buttons
│   ├── Inputs
│   ├── Cards
│   ├── Modals
│   ├── Navigation
│   └── Forms
├── 📄 Patterns
│   ├── Layouts
│   ├── Data Display
│   └── Feedback
└── 📄 Examples
    ├── Dashboard
    ├── Forms
    └── Detail Pages
```

### Design Token Export

```json
{
  "colors": {
    "primary": {
      "value": "hsl(226, 70%, 55%)",
      "type": "color"
    },
    "background": {
      "value": "hsl(0, 0%, 100%)",
      "type": "color"
    }
  },
  "typography": {
    "fontFamily": {
      "sans": {
        "value": "Inter, sans-serif",
        "type": "fontFamily"
      }
    },
    "fontSize": {
      "base": {
        "value": "16px",
        "type": "fontSize"
      }
    }
  },
  "spacing": {
    "4": {
      "value": "16px",
      "type": "spacing"
    }
  }
}
```

### Replacing Design Tokens

To customize the design for a new project:

1. **Update `src/styles/tokens.css`**:
   ```css
   :root {
     /* Replace primary color */
     --color-brand-primary: 210 100% 50%;  /* New blue */

     /* Replace font */
     --font-sans: 'Roboto', sans-serif;
   }
   ```

2. **Update Tailwind config if needed**

3. **Rebuild styles**:
   ```bash
   npm run build:css
   ```

---

## Designer Integration

### Handoff Process

1. **Designer creates/updates Figma file**
2. **Export design tokens** (using Figma plugin)
3. **Update `tokens.css`** with new values
4. **Review components** for any structural changes
5. **Test in development** environment
6. **Deploy** to staging for design review

### Figma Plugin Recommendations

- **Tokens Studio**: Export design tokens as JSON
- **Figma to Code**: Generate component code
- **Stark**: Accessibility checking
- **Contrast**: Color contrast validation

### Design Review Checklist

- [ ] All colors use design tokens
- [ ] Typography follows the type scale
- [ ] Spacing uses the 4px/8px grid
- [ ] Components are accessible (WCAG AA)
- [ ] Designs work on mobile, tablet, desktop
- [ ] Dark mode is defined
- [ ] Interactive states defined (hover, focus, active, disabled)

---

## Responsive Design

### Breakpoints

```typescript
export const breakpoints = {
  sm: '640px',   // Small devices (phones)
  md: '768px',   // Medium devices (tablets)
  lg: '1024px',  // Large devices (laptops)
  xl: '1280px',  // Extra large devices (desktops)
  '2xl': '1536px', // 2X large devices (large desktops)
};
```

### Responsive Patterns

```tsx
// Responsive grid
<div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">

// Responsive text
<h1 className="text-2xl sm:text-3xl lg:text-4xl xl:text-5xl">

// Responsive padding
<div className="p-4 md:p-6 lg:p-8 xl:p-12">

// Responsive visibility
<div className="hidden md:block">Desktop only</div>
<div className="block md:hidden">Mobile only</div>

// Responsive flex direction
<div className="flex flex-col lg:flex-row">
```

---

## Accessibility

### ARIA Labels

```tsx
// ✅ Good accessibility
<button aria-label="Close dialog">
  <X className="h-4 w-4" />
</button>

<nav aria-label="Main navigation">
  {/* Navigation items */}
</nav>

<img src={logo} alt="Company logo" />

// Form labels
<label htmlFor="email">Email</label>
<input id="email" type="email" />
```

### Keyboard Navigation

```tsx
// Tab index
<div tabIndex={0} onKeyDown={handleKeyDown}>
  Focusable div
</div>

// Skip to main content
<a href="#main-content" className="sr-only focus:not-sr-only">
  Skip to main content
</a>
```

### Screen Reader Support

```tsx
// Visually hidden but screen reader accessible
<span className="sr-only">Loading...</span>

// Live regions for dynamic content
<div role="status" aria-live="polite" aria-atomic="true">
  {statusMessage}
</div>
```

---

## Design Guardrails

### ✅ ALWAYS
- Use design tokens from `tokens.css`
- Follow the spacing system (4px/8px grid)
- Use semantic color names (primary, success, error)
- Implement responsive design (mobile-first)
- Add ARIA labels for accessibility
- Use shadcn/ui components as base
- Test color contrast (WCAG AA minimum)
- Support dark mode
- Follow typography scale
- Use Tailwind utilities (no inline styles)

### ❌ NEVER
- Hardcode colors, fonts, or spacing
- Use inline styles
- Skip responsive breakpoints
- Ignore accessibility
- Modify shadcn/ui base components without reason
- Use arbitrary values excessively
- Create one-off components (reuse existing)
- Skip contrast checks
- Use non-semantic HTML
- Forget keyboard navigation

### Component Checklist
- [ ] Uses design tokens?
- [ ] Responsive on all breakpoints?
- [ ] Accessible (ARIA, keyboard)?
- [ ] Supports dark mode?
- [ ] Follows spacing system?
- [ ] Uses semantic HTML?
- [ ] Color contrast passes WCAG AA?
- [ ] Has hover/focus/active states?
- [ ] Handles loading/error states?
- [ ] Documented in Storybook/design system?

---

## Code Examples

### Complete Component with Design System

```tsx
// src/components/features/ProductCard.tsx
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ShoppingCart } from 'lucide-react';

interface ProductCardProps {
  product: {
    id: string;
    name: string;
    description: string;
    price: number;
    category: string;
    inStock: boolean;
  };
  onAddToCart: (id: string) => void;
}

export function ProductCard({ product, onAddToCart }: ProductCardProps) {
  return (
    <Card className="h-full flex flex-col hover:shadow-lg transition-shadow">
      <CardHeader>
        <div className="flex items-start justify-between gap-2">
          <CardTitle className="text-xl">{product.name}</CardTitle>
          <Badge variant={product.inStock ? 'success' : 'secondary'}>
            {product.inStock ? 'In Stock' : 'Out of Stock'}
          </Badge>
        </div>
        <CardDescription className="text-sm text-muted-foreground">
          {product.category}
        </CardDescription>
      </CardHeader>

      <CardContent className="flex-1 flex flex-col justify-between">
        <p className="text-sm mb-4 line-clamp-3">{product.description}</p>

        <div className="flex items-center justify-between">
          <span className="text-2xl font-bold">
            ${product.price.toFixed(2)}
          </span>

          <Button
            onClick={() => onAddToCart(product.id)}
            disabled={!product.inStock}
            aria-label={`Add ${product.name} to cart`}
          >
            <ShoppingCart className="h-4 w-4 mr-2" />
            Add to Cart
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}
```

---

**Last Updated**: 2024-01-07
**Maintained By**: Allia Engineering Team
**Version**: 1.0.0
