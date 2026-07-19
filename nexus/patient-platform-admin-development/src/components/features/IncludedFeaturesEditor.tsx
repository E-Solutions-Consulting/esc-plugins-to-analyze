import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { ArrowDown, ArrowUp, Check, Plus, Trash2 } from "lucide-react";

interface IncludedFeaturesEditorProps {
  /** Current bullets. */
  value: string[];
  onChange: (next: string[]) => void;
  disabled?: boolean;
}

/**
 * Inline editor for a product's "What's Included" bullets (products.included_features).
 * A flat, ordered list of short strings — add / edit / reorder / remove. The array is
 * saved with the rest of the product form, so this is a controlled component (no queries).
 */
export function IncludedFeaturesEditor({
  value,
  onChange,
  disabled,
}: IncludedFeaturesEditorProps) {
  const updateAt = (index: number, next: string) => {
    const copy = [...value];
    copy[index] = next;
    onChange(copy);
  };

  const removeAt = (index: number) => {
    onChange(value.filter((_, i) => i !== index));
  };

  const move = (index: number, delta: number) => {
    const target = index + delta;
    if (target < 0 || target >= value.length) return;
    const copy = [...value];
    [copy[index], copy[target]] = [copy[target], copy[index]];
    onChange(copy);
  };

  const add = () => {
    onChange([...value, ""]);
  };

  return (
    <div className="space-y-2">
      {value.length === 0 ? (
        <p className="text-sm text-muted-foreground">
          No inclusions added. These bullets appear under “What’s Included” in the
          patient checkout summary.
        </p>
      ) : (
        <div className="space-y-2">
          {value.map((feature, index) => (
            <div key={index} className="flex items-center gap-2">
              <Check className="h-4 w-4 shrink-0 text-emerald-600" />
              <Input
                value={feature}
                disabled={disabled}
                maxLength={120}
                placeholder="e.g. Provider consultation & medical review"
                onChange={(event) => updateAt(index, event.target.value)}
              />
              <Button
                type="button"
                variant="ghost"
                size="icon"
                disabled={disabled || index === 0}
                onClick={() => move(index, -1)}
                aria-label="Move up"
              >
                <ArrowUp className="h-4 w-4" />
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                disabled={disabled || index === value.length - 1}
                onClick={() => move(index, 1)}
                aria-label="Move down"
              >
                <ArrowDown className="h-4 w-4" />
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                disabled={disabled}
                onClick={() => removeAt(index)}
                aria-label="Remove"
                className="text-destructive hover:text-destructive hover:bg-destructive/10"
              >
                <Trash2 className="h-4 w-4" />
              </Button>
            </div>
          ))}
        </div>
      )}

      <Button
        type="button"
        variant="outline"
        size="sm"
        disabled={disabled}
        onClick={add}
      >
        <Plus className="mr-2 h-4 w-4" />
        Add inclusion
      </Button>
    </div>
  );
}
