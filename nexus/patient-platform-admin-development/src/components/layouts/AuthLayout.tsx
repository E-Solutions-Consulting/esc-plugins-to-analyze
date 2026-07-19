import { ReactNode } from "react";
import { APP_NAME } from "@/lib/constants";
import { useDocumentTitle } from "@/hooks/useDocumentTitle";
import { dateTime } from "@/lib/dayjs";

interface AuthLayoutProps {
  children: ReactNode;
  title: string;
  description?: string;
}

export function AuthLayout({ children, title, description }: AuthLayoutProps) {
  useDocumentTitle(title);

  return (
    <div className="min-h-screen flex">
      {/* Left side - Branding */}
      <div className="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-primary to-primary/80 p-12 flex-col justify-between">
        <div>
          <h1 className="text-3xl font-bold text-primary-foreground">
            {APP_NAME}
          </h1>
          <p className="text-primary-foreground/80 mt-2">
            Multi-Tenant Admin Platform
          </p>
        </div>
        <div className="space-y-6">
          <blockquote className="text-xl text-primary-foreground/90 italic">
            "Empowering healthcare providers with modern patient care
            management."
          </blockquote>
          <div className="flex items-center gap-4">
            <div className="h-12 w-12 rounded-full bg-primary-foreground/20 flex items-center justify-center">
              <span className="text-primary-foreground font-semibold">AC</span>
            </div>
            <div>
              <p className="text-primary-foreground font-medium">
                Allia Care Platform
              </p>
              <p className="text-primary-foreground/70 text-sm">
                Enterprise Healthcare Solution
              </p>
            </div>
          </div>
        </div>
        <p className="text-primary-foreground/60 text-sm">
          © {dateTime().year()} {APP_NAME}. All rights reserved.
        </p>
      </div>

      {/* Right side - Form */}
      <div className="flex-1 flex items-center justify-center p-8">
        <div className="w-full max-w-md space-y-8">
          <div className="text-center lg:text-left">
            <h2 className="text-2xl font-bold tracking-tight">{title}</h2>
            {description && (
              <p className="text-muted-foreground mt-2">{description}</p>
            )}
          </div>
          {children}
        </div>
      </div>
    </div>
  );
}
