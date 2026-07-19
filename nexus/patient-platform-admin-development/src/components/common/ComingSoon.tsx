/**
 * ComingSoon — reusable placeholder for capabilities that are planned but whose
 * core functionality is not built yet. Use for whole pages or inline sections.
 */
import { ReactNode } from "react";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { Clock } from "lucide-react";

interface ComingSoonProps {
  title: string;
  description?: ReactNode;
  /** Optional bullet list of what it will do, to set expectations. */
  bullets?: string[];
  className?: string;
}

export function ComingSoonBadge() {
  return (
    <Badge variant="secondary" className="gap-1 text-xs">
      <Clock className="h-3 w-3" /> Coming soon
    </Badge>
  );
}

export function ComingSoon({ title, description, bullets, className }: ComingSoonProps) {
  return (
    <Card className={className}>
      <CardContent className="flex flex-col items-center gap-3 py-10 text-center">
        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-muted">
          <Clock className="h-6 w-6 text-muted-foreground" />
        </div>
        <div className="flex items-center gap-2">
          <h3 className="text-lg font-semibold">{title}</h3>
          <ComingSoonBadge />
        </div>
        {description && (
          <p className="max-w-md text-sm text-muted-foreground">{description}</p>
        )}
        {bullets && bullets.length > 0 && (
          <ul className="mt-1 space-y-1 text-left text-sm text-muted-foreground">
            {bullets.map((b) => (
              <li key={b} className="flex items-start gap-2">
                <span className="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-muted-foreground" />
                {b}
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  );
}
