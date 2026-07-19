/** Shared presentational helpers for the settings-v2 MOCKUP pages. */
import { ChangeEvent, ReactNode, useRef, useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { ImageIcon, Info, Upload, X } from "lucide-react";

/** Banner shown on every mockup page to make the non-functional intent obvious. */
export function MockupNotice({ children }: { children?: ReactNode }) {
  return (
    <div className="mb-6 flex items-start gap-3 rounded-lg border border-dashed border-amber-400/60 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
      <Info className="h-4 w-4 mt-0.5 shrink-0" />
      <p>
        {children ?? (
          <>
            This is a non-functional design mockup. Controls are placeholders;
            no data is saved. See <code>docs/SettingsIARedesign.md</code>.
          </>
        )}
      </p>
    </div>
  );
}

/** A "moved from X" pill to make relocations explicit during review. */
export function MovedFrom({ from }: { from: string }) {
  return (
    <Badge variant="outline" className="text-xs font-normal">
      moved from {from}
    </Badge>
  );
}

export function NewBadge() {
  return (
    <Badge className="text-xs bg-emerald-600 hover:bg-emerald-600">New</Badge>
  );
}

interface SectionCardProps {
  title: ReactNode;
  description?: ReactNode;
  actions?: ReactNode;
  children: ReactNode;
}

export function SectionCard({
  title,
  description,
  actions,
  children,
}: SectionCardProps) {
  return (
    <Card>
      <CardHeader>
        <div className="flex items-start justify-between gap-4">
          <div className="space-y-1">
            <CardTitle className="text-lg flex items-center gap-2">
              {title}
            </CardTitle>
            {description && <CardDescription>{description}</CardDescription>}
          </div>
          {actions && <div className="shrink-0">{actions}</div>}
        </div>
      </CardHeader>
      <CardContent className="space-y-4">{children}</CardContent>
    </Card>
  );
}

interface MockImageUploadProps {
  label: string;
  description?: ReactNode;
  defaultValue?: string;
  previewClassName?: string;
  onChange?: (url: string) => void;
}

export function MockImageUpload({
  label,
  description,
  defaultValue = "/placeholder.svg",
  previewClassName = "h-32 w-32",
  onChange,
}: MockImageUploadProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [previewUrl, setPreviewUrl] = useState(defaultValue);

  const handleFileChange = (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;
    const nextUrl = URL.createObjectURL(file);
    setPreviewUrl(nextUrl);
    onChange?.(nextUrl);
  };

  return (
    <div className="space-y-2">
      <div className="space-y-1">
        <p className="text-sm font-medium">{label}</p>
        {description && (
          <p className="text-xs text-muted-foreground">{description}</p>
        )}
      </div>
      <div className="flex flex-wrap items-center gap-4">
        <div
          className={`${previewClassName} flex items-center justify-center overflow-hidden rounded-lg border bg-muted`}
        >
          {previewUrl ? (
            <img
              src={previewUrl}
              alt={`${label} preview`}
              className="h-full w-full object-contain p-3"
            />
          ) : (
            <ImageIcon className="h-6 w-6 text-muted-foreground" />
          )}
        </div>
        <div className="flex flex-wrap gap-2">
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => inputRef.current?.click()}
          >
            <Upload className="h-4 w-4 mr-1" /> Upload
          </Button>
          {previewUrl && (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="text-destructive"
              onClick={() => {
                setPreviewUrl("");
                onChange?.("");
              }}
            >
              <X className="h-4 w-4 mr-1" /> Remove
            </Button>
          )}
        </div>
      </div>
      <input
        ref={inputRef}
        type="file"
        accept="image/*"
        className="hidden"
        onChange={handleFileChange}
      />
    </div>
  );
}
