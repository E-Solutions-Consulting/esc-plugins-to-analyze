/** Inserts {{placeholder}} tokens from the catalog into a message body/subject. */
import { Braces } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { PLACEHOLDER_GROUPS } from "@/lib/comms-automations/catalog";

interface PlaceholderPickerProps {
  onInsert: (token: string) => void;
}

export function PlaceholderPicker({ onInsert }: PlaceholderPickerProps) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline" size="sm" type="button">
          <Braces className="h-3.5 w-3.5 mr-1" /> Insert placeholder
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-56">
        <DropdownMenuLabel>Merge fields</DropdownMenuLabel>
        <DropdownMenuSeparator />
        {PLACEHOLDER_GROUPS.map((group) => (
          <DropdownMenuSub key={group.namespace}>
            <DropdownMenuSubTrigger>{group.label}</DropdownMenuSubTrigger>
            <DropdownMenuSubContent className="w-64">
              {group.fields.map((field) => (
                <DropdownMenuItem
                  key={field.key}
                  onClick={() => onInsert(`{{${field.key}}}`)}
                  className="flex flex-col items-start"
                >
                  <span className="text-sm">{field.label}</span>
                  <span className="text-xs text-muted-foreground font-mono">{`{{${field.key}}}`}</span>
                </DropdownMenuItem>
              ))}
            </DropdownMenuSubContent>
          </DropdownMenuSub>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
