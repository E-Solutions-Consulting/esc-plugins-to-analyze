import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import { TriangleAlert } from "lucide-react";

export function JotformSetupWarning() {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <span
          aria-label="Jotform setup is missing"
          className="inline-flex cursor-help items-center text-amber-600"
          role="img"
          tabIndex={0}
        >
          <TriangleAlert className="h-4 w-4" />
        </span>
      </TooltipTrigger>
      <TooltipContent>
        <p className="max-w-xs">
          Jotform setup is missing. Configure the API key, API URL, and Team
          Workspace ID in Settings &gt; Integrations &gt; Forms.
        </p>
      </TooltipContent>
    </Tooltip>
  );
}
