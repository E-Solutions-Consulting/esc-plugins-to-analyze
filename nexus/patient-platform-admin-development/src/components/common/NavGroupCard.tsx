import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import type { NavGroup } from "@/lib/nav-config";
import { Link } from "react-router-dom";

interface NavGroupCardProps {
  group: NavGroup;
  /** Route prefix the group's item slugs are appended to. */
  basePath: string;
}

/**
 * A settings/overview "menu card": a titled group header over a list of links.
 * Shared by the tenant settings and platform admin overview pages so the two
 * stay visually in step.
 */
export function NavGroupCard({ group, basePath }: NavGroupCardProps) {
  return (
    <Card className="overflow-hidden">
      <CardHeader className="border-b bg-accent/60 px-5 py-3.5">
        <CardTitle className="flex items-center gap-2.5 text-xs font-semibold uppercase tracking-[0.14em] text-primary">
          <span aria-hidden className="h-4 w-1 rounded-full bg-primary" />
          {group.label}
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-1 p-3">
        {group.items.map((item) => (
          <Link
            key={item.slug}
            to={`${basePath}/${item.slug}`}
            className="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-accent"
          >
            <item.icon className="h-4 w-4 text-muted-foreground" />
            {item.title}
          </Link>
        ))}
      </CardContent>
    </Card>
  );
}
