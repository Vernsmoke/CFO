"use client";
import { useCallback, useEffect, useRef, useState, type RefObject } from "react";

const prefix = "cfo-draft:";
const lifetime = 2 * 60 * 60 * 1000;
export function clearDrafts() {
  window.dispatchEvent(new Event("cfo-session-cleared"));
  try {
    Object.keys(sessionStorage)
      .filter((key) => key.startsWith(prefix))
      .forEach((key) => sessionStorage.removeItem(key));
  } catch {
    /* Tab storage may be unavailable. */
  }
}
function snapshot(form: HTMLFormElement) {
  // Empty file inputs receive a fresh lastModified timestamp on each FormData read.
  return JSON.stringify(
    [...new FormData(form)]
      .filter(([name]) => !["confirmed", "reason", "confirmationReason"].includes(name))
      .map(([name, value]) => [
        name,
        typeof value === "string"
          ? value
          : value.name === "" && value.size === 0
            ? ""
            : `${value.name}:${value.size}:${value.lastModified}`,
      ]),
  );
}
export function useFormSafety(ref: RefObject<HTMLFormElement | null>, draftKey?: string) {
  const baseline = useRef<{
    form: HTMLFormElement;
    draftKey?: string;
    value: string;
  } | null>(null);
  const bypass = useRef(false);
  const savedTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const [leave, setLeave] = useState<(() => void) | null>(null);
  const [draftNotice, setDraftNotice] = useState("");
  useEffect(() => {
    const form = ref.current!;
    // Keep the original values when React re-runs effects after restoring a draft.
    if (baseline.current?.form !== form || baseline.current.draftKey !== draftKey)
      baseline.current = { form, draftKey, value: snapshot(form) };
    bypass.current = false;
    if (draftKey) {
      try {
        const stored = JSON.parse(sessionStorage.getItem(prefix + draftKey) ?? "null");
        if (stored?.expires > Date.now() && stored.fields && typeof stored.fields === "object") {
          for (const [name, value] of Object.entries(stored.fields)) {
            const field = form.elements.namedItem(name);
            if (
              (field instanceof HTMLInputElement ||
                field instanceof HTMLSelectElement ||
                field instanceof HTMLTextAreaElement) &&
              !["file", "password", "hidden"].includes(field.type) &&
              typeof value === "string"
            )
              field.value = value;
          }
          // Announce restored drafts outside the initialization effect.
          savedTimer.current = setTimeout(() => setDraftNotice("Draft restored."), 0);
        } else sessionStorage.removeItem(prefix + draftKey);
      } catch {
        /* A malformed or unavailable draft never blocks editing. */
      }
    }
    const dirty = () => !bypass.current && snapshot(form) !== baseline.current?.value;
    const unload = (event: BeforeUnloadEvent) => {
      if (dirty()) {
        event.preventDefault();
        event.returnValue = "";
      }
    };
    const click = (event: MouseEvent) => {
      if (
        event.defaultPrevented ||
        event.button !== 0 ||
        event.ctrlKey ||
        event.metaKey ||
        event.shiftKey ||
        event.altKey ||
        !dirty()
      )
        return;
      const summary = (event.target as Element).closest<HTMLElement>("summary");
      const details = summary?.closest("details");
      if (summary && details?.open && details.contains(form)) {
        event.preventDefault();
        event.stopImmediatePropagation();
        setLeave(() => () => summary.click());
        return;
      }
      const link = (event.target as Element).closest<HTMLAnchorElement>("a[href]");
      if (!link || link.target === "_blank" || link.hasAttribute("download")) return;
      const url = new URL(link.href);
      if (
        url.origin === location.origin &&
        url.pathname === location.pathname &&
        url.search === location.search
      )
        return;
      event.preventDefault();
      event.stopImmediatePropagation();
      setLeave(() => () => link.click());
    };
    const submit = (event: SubmitEvent) => {
      const target = event.target as HTMLFormElement;
      if (target.dataset.leave !== "true" || !dirty()) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      setLeave(() => () => target.requestSubmit());
    };
    type Navigation = EventTarget & { traverseTo: (key: string) => void };
    const navigation = (window as Window & { navigation?: Navigation }).navigation;
    const traverse = (event: Event) => {
      const e = event as Event & {
        navigationType: string;
        canIntercept: boolean;
        destination: { key: string };
      };
      if (e.navigationType !== "traverse" || !e.cancelable || !e.canIntercept || !dirty()) return;
      e.preventDefault();
      setLeave(() => () => navigation?.traverseTo(e.destination.key));
    };
    const clear = () => {
      bypass.current = true;
    };
    window.addEventListener("beforeunload", unload);
    window.addEventListener("cfo-session-cleared", clear);
    document.addEventListener("click", click, true);
    document.addEventListener("submit", submit, true);
    navigation?.addEventListener("navigate", traverse);
    return () => {
      window.removeEventListener("beforeunload", unload);
      window.removeEventListener("cfo-session-cleared", clear);
      document.removeEventListener("click", click, true);
      document.removeEventListener("submit", submit, true);
      navigation?.removeEventListener("navigate", traverse);
      if (savedTimer.current) clearTimeout(savedTimer.current);
    };
  }, [ref, draftKey]);
  const changed = () => {
    bypass.current = false;
    if (!draftKey || !ref.current) return;
    const fields = Object.fromEntries(
      [...new FormData(ref.current)].filter(([name, value]) => {
        const field = ref.current!.elements.namedItem(name);
        return (
          typeof value === "string" &&
          !["reason", "confirmed"].includes(name) &&
          (field instanceof HTMLInputElement ||
            field instanceof HTMLSelectElement ||
            field instanceof HTMLTextAreaElement) &&
          !["hidden", "password", "file"].includes(field.type)
        );
      }),
    );
    try {
      sessionStorage.setItem(
        prefix + draftKey,
        JSON.stringify({ fields, expires: Date.now() + lifetime }),
      );
      setDraftNotice("Draft saved in this tab.");
    } catch {
      setDraftNotice("Draft storage unavailable. Keep this page open until saved.");
    }
  };
  const saved = useCallback(() => {
    bypass.current = true;
    if (ref.current && baseline.current) baseline.current.value = snapshot(ref.current);
    if (draftKey) {
      try {
        sessionStorage.removeItem(prefix + draftKey);
      } catch {
        /* Storage may be unavailable. */
      }
    }
    setDraftNotice("");
  }, [ref, draftKey]);
  return {
    changed,
    saved,
    draftNotice,
    leave,
    keep: () => setLeave(null),
    discard: () => {
      const action = leave;
      setLeave(null);
      bypass.current = true;
      saved();
      action?.();
    },
  };
}
