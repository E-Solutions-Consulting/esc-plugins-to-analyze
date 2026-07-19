const EMPTY_RICH_TEXT_PATTERNS = [
  /^<br\s*\/?>(?:\s|&nbsp;)*$/i,
  /^<div><br\s*\/?>\s*<\/div>$/i,
  /^<p><br\s*\/?>\s*<\/p>$/i,
];

export function normalizeRichTextHtml(value?: string | null): string {
  const normalized = (value ?? '')
    .replace(/\u200B/g, '')
    .replace(/&nbsp;/g, ' ')
    .trim();

  if (!normalized) {
    return '';
  }

  if (EMPTY_RICH_TEXT_PATTERNS.some((pattern) => pattern.test(normalized))) {
    return '';
  }

  return normalized;
}

export function toNullableRichTextHtml(value?: string | null): string | null {
  const normalized = normalizeRichTextHtml(value);
  return normalized.length > 0 ? normalized : null;
}

export function richTextToPlainText(value?: string | null): string {
  return normalizeRichTextHtml(value)
    .replace(/<[^>]+>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}
