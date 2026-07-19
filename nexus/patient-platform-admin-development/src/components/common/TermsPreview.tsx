import { normalizeRichTextHtml } from '@/lib/html-content';
import { cn } from '@/lib/utils';

interface TermsPreviewProps {
  content?: string | null;
  emptyText?: string;
  className?: string;
  maxHeightClassName?: string;
}

function plainTextToHtml(value: string): string {
  const escapeHtml = (text: string) =>
    text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');

  return value
    .split(/\n{2,}/)
    .map((paragraph) => paragraph.trim())
    .filter(Boolean)
    .map((paragraph) => `<p>${escapeHtml(paragraph).replace(/\n/g, '<br>')}</p>`)
    .join('');
}

export function TermsPreview({
  content,
  emptyText = '—',
  className,
  maxHeightClassName = 'max-h-80',
}: TermsPreviewProps) {
  const normalizedContent = normalizeRichTextHtml(content);
  const previewHtml = normalizedContent.includes('<')
    ? normalizedContent
    : plainTextToHtml(normalizedContent);

  if (!previewHtml) {
    return (
      <div
        className={cn(
          'rounded-md border bg-background p-4 text-sm text-muted-foreground',
          className,
        )}
      >
        {emptyText}
      </div>
    );
  }

  return (
    <div
      className={cn(
        'min-h-0 max-w-full overflow-y-auto overscroll-contain rounded-md border bg-background p-4 text-foreground shadow-inner',
        maxHeightClassName,
        className,
      )}
    >
      <div
        className="prose prose-sm max-w-none break-words text-foreground [&_a]:break-words [&_ol]:my-3 [&_ul]:my-3"
        dangerouslySetInnerHTML={{ __html: previewHtml }}
      />
    </div>
  );
}
