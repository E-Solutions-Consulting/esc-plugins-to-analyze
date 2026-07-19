import { useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { normalizeRichTextHtml } from '@/lib/html-content';

interface HtmlEditorProps {
  id?: string;
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  disabled?: boolean;
  className?: string;
  minHeightClassName?: string;
  editorClassName?: string;
}

const INLINE_FORMATTING_TAGS = new Set([
  'A',
  'B',
  'EM',
  'FONT',
  'I',
  'MARK',
  'S',
  'SMALL',
  'SPAN',
  'STRIKE',
  'STRONG',
  'SUB',
  'SUP',
  'U',
]);

const BLOCK_FORMATTING_TAGS = new Set([
  'ADDRESS',
  'BLOCKQUOTE',
  'DIV',
  'H1',
  'H2',
  'H3',
  'H4',
  'H5',
  'H6',
  'PRE',
]);

function unwrapElement(element: HTMLElement) {
  const parent = element.parentNode;
  if (!parent) return;

  while (element.firstChild) {
    parent.insertBefore(element.firstChild, element);
  }

  parent.removeChild(element);
}

function clearEditorFormatting(html: string): string {
  if (typeof document === 'undefined') {
    return normalizeRichTextHtml(html);
  }

  const container = document.createElement('div');
  container.innerHTML = html;

  const elements = Array.from(container.querySelectorAll<HTMLElement>('*'));

  for (const element of elements) {
    element.removeAttribute('style');
    element.removeAttribute('class');
    element.removeAttribute('id');
    element.removeAttribute('dir');

    if (INLINE_FORMATTING_TAGS.has(element.tagName)) {
      unwrapElement(element);
      continue;
    }

    if (BLOCK_FORMATTING_TAGS.has(element.tagName)) {
      const paragraph = document.createElement('p');
      while (element.firstChild) {
        paragraph.appendChild(element.firstChild);
      }
      element.replaceWith(paragraph);
    }
  }

  const paragraphs = Array.from(container.querySelectorAll('p'));
  for (const paragraph of paragraphs) {
    if (
      paragraph.parentElement?.tagName === 'LI' &&
      paragraph.attributes.length === 0 &&
      paragraph.childNodes.length === 1 &&
      paragraph.firstChild?.nodeType === Node.TEXT_NODE
    ) {
      unwrapElement(paragraph);
    }
  }

  return normalizeRichTextHtml(container.innerHTML);
}

export function HtmlEditor({
  id,
  value,
  onChange,
  placeholder = 'Start typing...',
  disabled = false,
  className,
  minHeightClassName = 'min-h-44',
  editorClassName,
}: HtmlEditorProps) {
  const editorRef = useRef<HTMLDivElement | null>(null);
  const selectionRef = useRef<Range | null>(null);
  const [isFocused, setIsFocused] = useState(false);
  const [isSourceMode, setIsSourceMode] = useState(false);
  const [sourceValue, setSourceValue] = useState('');
  const normalizedValue = useMemo(() => normalizeRichTextHtml(value), [value]);

  useEffect(() => {
    if (isSourceMode) {
      return;
    }

    const editor = editorRef.current;
    if (!editor) return;

    const current = normalizeRichTextHtml(editor.innerHTML);
    if (current !== normalizedValue && document.activeElement !== editor) {
      editor.innerHTML = normalizedValue;
    }
  }, [isSourceMode, normalizedValue]);

  useEffect(() => {
    if (!isSourceMode) {
      setSourceValue(normalizedValue);
    }
  }, [isSourceMode, normalizedValue]);

  const saveSelection = () => {
    const editor = editorRef.current;
    const selection = window.getSelection();

    if (!editor || !selection || selection.rangeCount === 0) {
      selectionRef.current = null;
      return;
    }

    const range = selection.getRangeAt(0);
    if (!editor.contains(range.commonAncestorContainer)) {
      return;
    }

    selectionRef.current = range.cloneRange();
  };

  const restoreSelection = () => {
    const selection = window.getSelection();
    if (!selectionRef.current || !selection) {
      return;
    }

    selection.removeAllRanges();
    selection.addRange(selectionRef.current);
  };

  const emitChange = () => {
    const editor = editorRef.current;
    if (!editor) return;

    const nextValue = normalizeRichTextHtml(editor.innerHTML);
    onChange(nextValue);
    if (!isSourceMode) {
      setSourceValue(nextValue);
    }
  };

  const runCommand = (command: string, commandValue?: string) => {
    if (disabled || isSourceMode) return;
    const editor = editorRef.current;
    if (!editor) return;

    editor.focus();
    restoreSelection();
    document.execCommand(command, false, commandValue);
    saveSelection();
    emitChange();
  };

  const applyBlockFormat = (tagName: 'P' | 'H1' | 'H2' | 'H3' | 'BLOCKQUOTE') => {
    runCommand('formatBlock', tagName);
  };

  const handleInsertLink = () => {
    if (disabled || isSourceMode) return;
    const url = window.prompt('Enter link URL', 'https://');
    if (!url) return;

    const normalizedUrl = url.trim();
    if (!normalizedUrl) return;

    runCommand('createLink', normalizedUrl);
  };

  const handleTextColorChange = (nextColor: string) => {
    if (disabled || isSourceMode) return;

    const editor = editorRef.current;
    if (!editor) return;

    editor.focus();
    restoreSelection();
    document.execCommand('styleWithCSS', false, 'true');
    document.execCommand('foreColor', false, nextColor);
    saveSelection();
    emitChange();
  };

  const toggleSourceMode = () => {
    if (disabled) return;

    if (isSourceMode) {
      const nextValue = normalizeRichTextHtml(sourceValue);
      setSourceValue(nextValue);
      onChange(nextValue);
      setIsSourceMode(false);
      return;
    }

    setSourceValue(normalizedValue);
    setIsSourceMode(true);
  };

  const handleClearFormatting = () => {
    if (disabled || isSourceMode) return;

    const editor = editorRef.current;
    if (!editor) return;

    const nextValue = clearEditorFormatting(editor.innerHTML);
    editor.innerHTML = nextValue;
    onChange(nextValue);
    setSourceValue(nextValue);
    editor.focus();
    saveSelection();
  };

  const isEmpty = normalizedValue.length === 0;

  return (
    <div className={cn('space-y-2', className)}>
      <div className="flex flex-wrap gap-2">
        <div className="flex flex-wrap gap-2 rounded-md border border-border p-2">
          <Button type="button" size="sm" variant="outline" onMouseDown={(e) => e.preventDefault()} onClick={() => applyBlockFormat('P')} disabled={disabled || isSourceMode}>
            Paragraph
          </Button>
          <Button type="button" size="sm" variant="outline" onMouseDown={(e) => e.preventDefault()} onClick={() => applyBlockFormat('H1')} disabled={disabled || isSourceMode}>
            H1
          </Button>
          <Button type="button" size="sm" variant="outline" onMouseDown={(e) => e.preventDefault()} onClick={() => applyBlockFormat('H2')} disabled={disabled || isSourceMode}>
            H2
          </Button>
          <Button type="button" size="sm" variant="outline" onMouseDown={(e) => e.preventDefault()} onClick={() => applyBlockFormat('H3')} disabled={disabled || isSourceMode}>
            H3
          </Button>
          <Button type="button" size="sm" variant="outline" onMouseDown={(e) => e.preventDefault()} onClick={() => applyBlockFormat('BLOCKQUOTE')} disabled={disabled || isSourceMode}>
            Quote
          </Button>
        </div>

        <div className="flex flex-wrap gap-2 rounded-md border border-border p-2">
          <Button type="button" size="sm" variant="outline" onMouseDown={(e) => e.preventDefault()} onClick={() => runCommand('bold')} disabled={disabled || isSourceMode}>
            Bold
          </Button>
          <Button type="button" size="sm" variant="outline" onMouseDown={(e) => e.preventDefault()} onClick={() => runCommand('italic')} disabled={disabled || isSourceMode}>
            Italic
          </Button>
          <Button type="button" size="sm" variant="outline" onMouseDown={(e) => e.preventDefault()} onClick={() => runCommand('underline')} disabled={disabled || isSourceMode}>
            Underline
          </Button>
          <div className="flex items-center gap-2 rounded-md border border-input bg-background px-3 py-1">
            <label htmlFor={id ? `${id}-text-color` : undefined} className="text-xs font-medium text-muted-foreground">
              Color
            </label>
            <input
              id={id ? `${id}-text-color` : undefined}
              type="color"
              defaultValue="#111827"
              className="h-8 w-8 cursor-pointer rounded border-0 bg-transparent p-0"
              onMouseDown={saveSelection}
              onChange={(e) => handleTextColorChange(e.target.value)}
              disabled={disabled || isSourceMode}
              aria-label="Text color"
            />
          </div>
        </div>

        <div className="flex flex-wrap gap-2 rounded-md border border-border p-2">
          <Button type="button" size="sm" variant="outline" onMouseDown={(e) => e.preventDefault()} onClick={() => runCommand('insertUnorderedList')} disabled={disabled || isSourceMode}>
            Bullets
          </Button>
          <Button type="button" size="sm" variant="outline" onMouseDown={(e) => e.preventDefault()} onClick={() => runCommand('insertOrderedList')} disabled={disabled || isSourceMode}>
            Numbered
          </Button>
          <Button type="button" size="sm" variant="outline" onMouseDown={(e) => e.preventDefault()} onClick={handleInsertLink} disabled={disabled || isSourceMode}>
            Link
          </Button>
          <Button type="button" size="sm" variant="outline" onMouseDown={(e) => e.preventDefault()} onClick={() => runCommand('unlink')} disabled={disabled || isSourceMode}>
            Unlink
          </Button>
          <Button type="button" size="sm" variant="outline" onMouseDown={(e) => e.preventDefault()} onClick={handleClearFormatting} disabled={disabled || isSourceMode}>
            Clear Format
          </Button>
          <Button type="button" size="sm" variant={isSourceMode ? 'default' : 'outline'} onMouseDown={(e) => e.preventDefault()} onClick={toggleSourceMode} disabled={disabled}>
            {isSourceMode ? 'Visual Editor' : 'View HTML'}
          </Button>
        </div>
      </div>

      <div className="relative">
        {isEmpty && !isFocused && !isSourceMode && (
          <p className="pointer-events-none absolute left-3 top-3 text-sm text-muted-foreground">
            {placeholder}
          </p>
        )}
        {isSourceMode ? (
          <textarea
            id={id}
            value={sourceValue}
            onChange={(e) => {
              const nextValue = e.target.value;
              setSourceValue(nextValue);
              onChange(normalizeRichTextHtml(nextValue));
            }}
            placeholder={placeholder}
            disabled={disabled}
            className={cn(
              'w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
              minHeightClassName,
              editorClassName,
              disabled && 'cursor-not-allowed opacity-60',
            )}
          />
        ) : (
          <div
            id={id}
            ref={editorRef}
            contentEditable={!disabled}
            suppressContentEditableWarning
            onInput={emitChange}
            onKeyUp={saveSelection}
            onMouseUp={saveSelection}
            onFocus={() => {
              setIsFocused(true);
              saveSelection();
            }}
            onBlur={() => {
              setIsFocused(false);
              saveSelection();
              emitChange();
            }}
            className={cn(
              'w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 [&_blockquote]:border-l-4 [&_blockquote]:border-border [&_blockquote]:pl-4 [&_blockquote]:italic [&_h1]:text-3xl [&_h1]:font-semibold [&_h2]:text-2xl [&_h2]:font-semibold [&_h3]:text-xl [&_h3]:font-semibold [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-2 [&_ul]:list-disc [&_ul]:pl-6',
              minHeightClassName,
              editorClassName,
              disabled && 'cursor-not-allowed opacity-60',
            )}
          />
        )}
      </div>
    </div>
  );
}
