/** Left "Build" palette of addable node types, grouped. */
import { Button } from "@/components/ui/button";
import { NODE_META, PALETTE_GROUPS } from "./node-meta";
import type { CommsNodeType } from "@/lib/comms-automations/types";

interface NodePaletteProps {
  onAdd: (type: CommsNodeType) => void;
  disabled?: boolean;
}

export function NodePalette({ onAdd, disabled }: NodePaletteProps) {
  const addable = Object.values(NODE_META).filter((m) => m.addable);
  return (
    <div className="space-y-5 p-4">
      <h2 className="text-sm font-semibold">Build</h2>
      {PALETTE_GROUPS.map((group) => {
        const items = addable.filter((m) => m.group === group.key);
        if (!items.length) return null;
        return (
          <div key={group.key} className="space-y-2">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
              {group.label}
            </p>
            {items.map((m) => (
              <Button
                key={m.type}
                variant="outline"
                className="w-full justify-start"
                disabled={disabled}
                onClick={() => onAdd(m.type)}
              >
                <m.icon className={`h-4 w-4 mr-2 ${m.color}`} />
                <span className="flex-1 text-left">{m.label}</span>
              </Button>
            ))}
          </div>
        );
      })}
    </div>
  );
}
