import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { supabase } from "@/integrations/supabase/client";
import {
  ArrowDown,
  ArrowUp,
  ImageIcon,
  Loader2,
  Plus,
  Trash2,
  Upload,
} from "lucide-react";
import { useRef, useState } from "react";
import { toast } from "sonner";

interface GalleryImagesEditorProps {
  /** Ordered image URLs. The first is the hero. */
  value: string[];
  onChange: (next: string[]) => void;
  /** Storage bucket for uploads — same one product images use. */
  bucket?: string;
  /** Sub-folder in the bucket, typically the tenant id. */
  folder: string;
  disabled?: boolean;
}

/**
 * Editor for a product page's gallery (`metadata.pdp.images`).
 *
 * A gallery is an ordered list of image URLs. Two ways to add one:
 *  - upload a file (lands in Supabase storage, we store its public URL), or
 *  - paste an external URL — the imported galleries reference brellohealth.com
 *    directly, so both kinds of URL have to coexist in the same list.
 *
 * Controlled component: it owns no query. The parent form holds the array and
 * saves it with the rest of the PDP content, so this only edits `value`.
 */
export function GalleryImagesEditor({
  value,
  onChange,
  bucket = "product-images",
  folder,
  disabled,
}: GalleryImagesEditorProps) {
  const [isUploading, setIsUploading] = useState(false);
  const [newUrl, setNewUrl] = useState("");
  const fileInputRef = useRef<HTMLInputElement>(null);

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

  const addUrl = () => {
    const trimmed = newUrl.trim();
    if (!trimmed) return;
    onChange([...value, trimmed]);
    setNewUrl("");
  };

  const handleFileSelect = async (
    event: React.ChangeEvent<HTMLInputElement>,
  ) => {
    const file = event.target.files?.[0];
    if (!file) return;

    if (!file.type.startsWith("image/")) {
      toast.error("Please select an image file");
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      toast.error("Image must be less than 5MB");
      return;
    }

    setIsUploading(true);
    try {
      const fileExt = file.name.split(".").pop();
      const fileName = `${folder}/${crypto.randomUUID()}.${fileExt}`;

      const { error: uploadError } = await supabase.storage
        .from(bucket)
        .upload(fileName, file, { cacheControl: "3600", upsert: false });
      if (uploadError) throw uploadError;

      const {
        data: { publicUrl },
      } = supabase.storage.from(bucket).getPublicUrl(fileName);

      onChange([...value, publicUrl]);
      toast.success("Image uploaded");
    } catch (error) {
      console.error("Upload error:", error);
      toast.error(
        error instanceof Error ? error.message : "Failed to upload image",
      );
    } finally {
      setIsUploading(false);
      if (fileInputRef.current) fileInputRef.current.value = "";
    }
  };

  return (
    <div className="space-y-3">
      {value.length === 0 ? (
        <p className="text-sm text-muted-foreground">
          No gallery images. Upload a file or paste an image URL to add one — the
          first image is used as the hero.
        </p>
      ) : (
        <div className="space-y-2">
          {value.map((url, index) => (
            <div
              key={index}
              className="flex items-center gap-3 rounded-md border bg-muted/10 p-2"
            >
              <div className="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded border bg-white">
                {url.trim() ? (
                  <img
                    src={url}
                    alt={`Gallery image ${index + 1}`}
                    className="h-full w-full object-contain p-1"
                    onError={(e) => {
                      (e.currentTarget as HTMLImageElement).style.visibility =
                        "hidden";
                    }}
                  />
                ) : (
                  <ImageIcon className="h-5 w-5 text-muted-foreground" />
                )}
              </div>
              <div className="flex-1">
                <Input
                  value={url}
                  disabled={disabled}
                  placeholder="https://…"
                  onChange={(event) => updateAt(index, event.target.value)}
                />
                {index === 0 && (
                  <p className="mt-1 text-xs text-muted-foreground">
                    Hero image (shown first).
                  </p>
                )}
              </div>
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
                className="text-destructive hover:bg-destructive/10 hover:text-destructive"
              >
                <Trash2 className="h-4 w-4" />
              </Button>
            </div>
          ))}
        </div>
      )}

      <div className="flex flex-wrap items-end gap-2">
        <div className="flex-1 min-w-[220px] space-y-1">
          <Input
            value={newUrl}
            disabled={disabled}
            placeholder="Paste an image URL and press Add"
            onChange={(event) => setNewUrl(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === "Enter") {
                event.preventDefault();
                addUrl();
              }
            }}
          />
        </div>
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={disabled || !newUrl.trim()}
          onClick={addUrl}
        >
          <Plus className="mr-2 h-4 w-4" />
          Add URL
        </Button>
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={disabled || isUploading}
          onClick={() => fileInputRef.current?.click()}
        >
          {isUploading ? (
            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
          ) : (
            <Upload className="mr-2 h-4 w-4" />
          )}
          Upload
        </Button>
      </div>

      <Input
        ref={fileInputRef}
        type="file"
        accept="image/*"
        onChange={handleFileSelect}
        disabled={disabled || isUploading}
        className="hidden"
      />
    </div>
  );
}
