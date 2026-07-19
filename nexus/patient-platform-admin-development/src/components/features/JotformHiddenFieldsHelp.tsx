import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import { CircleHelp } from "lucide-react";

interface JotformHiddenFieldsHelpProps {
  questionnaireType?: "patient_questionnaire" | "medical_questionnaire";
}

export function JotformHiddenFieldsHelp({
  questionnaireType,
}: JotformHiddenFieldsHelpProps) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <span
          aria-label="Jotform hidden field requirements"
          className="inline-flex cursor-help items-center align-middle text-muted-foreground"
          role="img"
          tabIndex={0}
        >
          <CircleHelp className="h-3.5 w-3.5" />
        </span>
      </TooltipTrigger>
      <TooltipContent>
        <div className="max-w-sm space-y-2 text-sm">
          <p>
            Every linked Jotform must include hidden fields named{" "}
            <code className="rounded bg-muted px-1 py-0.5 text-xs font-mono">
              patient_platform_order_id
            </code>
            ,{" "}
            <code className="rounded bg-muted px-1 py-0.5 text-xs font-mono">
              provider_key
            </code>
            , and{" "}
            <code className="rounded bg-muted px-1 py-0.5 text-xs font-mono">
              questionnaire_type
            </code>
            .
          </p>
          <p>
            Set{" "}
            <code className="rounded bg-muted px-1 py-0.5 text-xs font-mono">
              questionnaire_type
            </code>{" "}
            to{" "}
            <code className="rounded bg-muted px-1 py-0.5 text-xs font-mono">
              {questionnaireType ?? "patient_questionnaire or medical_questionnaire"}
            </code>{" "}
            as a fixed value for that form.
          </p>
        </div>
      </TooltipContent>
    </Tooltip>
  );
}
