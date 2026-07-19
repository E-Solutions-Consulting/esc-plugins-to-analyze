import * as React from "react";

type TestIdSourceProps = {
  id?: string;
  name?: string;
  title?: string;
  placeholder?: string;
  value?: string | number | readonly string[];
  "aria-label"?: string;
  "data-testid"?: string;
};

const cleanSegment = (value: unknown): string | undefined => {
  if (typeof value !== "string") return undefined;
  const cleaned = value
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 60);
  return cleaned || undefined;
};

const INTERACTIVE_SELECTOR = [
  "button",
  "a[href]",
  "input",
  "textarea",
  "select",
  '[role="button"]',
  '[role="link"]',
  '[role="checkbox"]',
  '[role="radio"]',
  '[role="switch"]',
  '[role="tab"]',
  '[role="menuitem"]',
].join(", ");

const getElementType = (element: Element): string => {
  const role = element.getAttribute("role");
  if (role) return cleanSegment(role) ?? "element";

  const tagName = element.tagName.toLowerCase();
  if (tagName === "input") {
    const inputType = (element as HTMLInputElement).type || "text";
    return (
      cleanSegment(inputType === "text" ? "input" : `input-${inputType}`) ??
      "input"
    );
  }

  return cleanSegment(tagName) ?? "element";
};

const getElementLabel = (element: Element): string | undefined => {
  const htmlElement = element as HTMLElement;
  return (
    cleanSegment(element.getAttribute("id")) ??
    cleanSegment(element.getAttribute("name")) ??
    cleanSegment(element.getAttribute("aria-label")) ??
    cleanSegment(htmlElement.title) ??
    cleanSegment((htmlElement as HTMLInputElement).placeholder) ??
    cleanSegment(element.textContent)
  );
};

const collectExistingTestIds = (): Map<string, number> => {
  const existingCounts = new Map<string, number>();
  document.querySelectorAll("[data-testid]").forEach((node) => {
    const value = node.getAttribute("data-testid");
    if (!value) return;
    existingCounts.set(value, (existingCounts.get(value) ?? 0) + 1);
  });
  return existingCounts;
};

const assignMissingTestIds = (
  root: ParentNode,
  existingCounts: Map<string, number>,
) => {
  const targets = new Set<Element>();
  if (root instanceof Element && root.matches(INTERACTIVE_SELECTOR)) {
    targets.add(root);
  }

  if ("querySelectorAll" in root) {
    root
      .querySelectorAll(INTERACTIVE_SELECTOR)
      .forEach((node) => targets.add(node));
  }

  targets.forEach((element) => {
    const existingId = element.getAttribute("data-testid");
    if (existingId) {
      existingCounts.set(existingId, (existingCounts.get(existingId) ?? 0) + 1);
      return;
    }

    const baseId = `${getElementType(element)}-${getElementLabel(element) ?? "auto"}`;
    const currentCount = existingCounts.get(baseId) ?? 0;
    const resolvedId =
      currentCount === 0 ? baseId : `${baseId}-${currentCount + 1}`;

    element.setAttribute("data-testid", resolvedId);
    existingCounts.set(baseId, currentCount + 1);
  });
};

const collectText = (node: React.ReactNode): string => {
  if (typeof node === "string" || typeof node === "number") return String(node);
  if (Array.isArray(node)) return node.map(collectText).join(" ");
  if (React.isValidElement<{ children?: React.ReactNode }>(node)) {
    return collectText(node.props.children);
  }
  return "";
};

export const getNodeText = (node: React.ReactNode): string | undefined =>
  cleanSegment(collectText(node));

export const useAutoTestId = (
  prefix: string,
  props: TestIdSourceProps,
  extraCandidate?: string,
): string => {
  const reactId = React.useId();
  const fallbackId = reactId.replace(/:/g, "") || "auto";

  const candidates = [
    props.id,
    props.name,
    props["aria-label"],
    props.title,
    props.placeholder,
    props.value,
    extraCandidate,
    fallbackId,
  ];

  const candidate = candidates.map(cleanSegment).find(Boolean) ?? "auto";
  const shouldAddUniqueSuffix =
    !cleanSegment(props.id) && !cleanSegment(props.name);
  const suffix = shouldAddUniqueSuffix ? `-${fallbackId}` : "";

  return props["data-testid"] ?? `${prefix}-${candidate}${suffix}`;
};

export const useEnsureTestIds = (): void => {
  React.useEffect(() => {
    const existingCounts = collectExistingTestIds();
    assignMissingTestIds(document, existingCounts);

    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
          if (node instanceof Element) {
            assignMissingTestIds(node, existingCounts);
          }
        });
      });
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true,
    });

    return () => observer.disconnect();
  }, []);
};
