import { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { ArrowLeft } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

interface PageHeaderProps {
  title: string;
  description?: string;
  backUrl?: string;
  actions?: ReactNode;
}

export function PageHeader({ title, description, backUrl, actions }: PageHeaderProps) {
  const navigate = useNavigate();
  useDocumentTitle(title);

  return (
    <div className="flex items-start justify-between gap-4 mb-6">
      <div className="space-y-1">
        <div className="flex items-center gap-3">
          {backUrl && (
            <Button
              variant="ghost"
              size="icon"
              onClick={() => navigate(backUrl)}
              className="shrink-0"
            >
              <ArrowLeft className="h-4 w-4" />
            </Button>
          )}
          <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
        </div>
        {description && (
          <p className="text-muted-foreground">{description}</p>
        )}
      </div>
      {actions && <div className="flex items-center gap-2 shrink-0">{actions}</div>}
    </div>
  );
}
