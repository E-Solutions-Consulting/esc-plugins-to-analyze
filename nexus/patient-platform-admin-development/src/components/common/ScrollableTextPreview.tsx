import { cn } from '@/lib/utils';

interface ScrollableTextPreviewProps {
  value?: string | null;
  emptyText?: string;
  className?: string;
  maxHeightClassName?: string;
}

export function ScrollableTextPreview({
  value,
  emptyText = '—',
  className,
  maxHeightClassName = 'max-h-40',
}: ScrollableTextPreviewProps) {
  const text = value?.trim();

  return (
    <div
      className={cn(
        'min-h-0 overflow-y-auto overscroll-contain whitespace-pre-wrap break-words rounded-md bg-background/60 px-3 py-2 text-sm font-medium leading-6 text-foreground shadow-inner',
        maxHeightClassName,
        !text && 'text-muted-foreground',
        className,
      )}
    >
      {text || emptyText}
    </div>
  );
}
