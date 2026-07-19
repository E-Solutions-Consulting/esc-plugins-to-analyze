import { useEffect } from 'react';
import { APP_NAME } from '@/lib/constants';

export function formatDocumentTitle(pageTitle?: string): string {
  const normalizedPageTitle = pageTitle?.trim();
  return normalizedPageTitle ? `${APP_NAME} | ${normalizedPageTitle}` : APP_NAME;
}

export function useDocumentTitle(pageTitle?: string): void {
  useEffect(() => {
    document.title = formatDocumentTitle(pageTitle);
  }, [pageTitle]);
}
