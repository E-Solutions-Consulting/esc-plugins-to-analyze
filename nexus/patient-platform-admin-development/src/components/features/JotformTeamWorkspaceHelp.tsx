import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import { CircleHelp } from "lucide-react";

export function JotformTeamWorkspaceHelp() {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <span
          aria-label="Jotform team workspace requirement"
          className="inline-flex cursor-help items-center align-middle text-muted-foreground"
          role="img"
          tabIndex={0}
        >
          <CircleHelp className="h-3.5 w-3.5" />
        </span>
      </TooltipTrigger>
      <TooltipContent>
        <p className="max-w-sm text-sm">
          Required to access Jotform form submissions. This value is sent to
          Jotform as the <code className="rounded bg-muted px-1 py-0.5 text-xs font-mono">jf-team-id</code> header.
        </p>
      </TooltipContent>
    </Tooltip>
  );
}