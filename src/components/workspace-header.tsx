"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { ArrowUpRight, ChevronRight, Search, X } from "lucide-react";
import { entries, roleLabels } from "./navigation";
import type { Role } from "@/lib/types";

export function WorkspaceHeader({ role }: { role: Role }) {
  const pathname = usePathname();
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const dialog = useRef<HTMLDialogElement>(null);
  const trigger = useRef<HTMLButtonElement>(null);
  const available = entries.filter((entry) => entry.roles.includes(role));
  const current = available.find((entry) => pathname === `/dashboard${entry.path}`);
  const matches = available.filter((entry) =>
    entry.title.toLowerCase().includes(query.trim().toLowerCase()),
  );

  useEffect(() => {
    const shortcut = (event: KeyboardEvent) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") {
        if (document.querySelector('dialog[open], [aria-modal="true"]')) return;
        event.preventDefault();
        setQuery("");
        setOpen(true);
      }
    };
    document.addEventListener("keydown", shortcut);
    return () => document.removeEventListener("keydown", shortcut);
  }, []);

  useEffect(() => {
    if (!open) return;
    const element = dialog.current!;
    const previousOverflow = document.body.style.overflow;
    element.showModal();
    element.querySelector<HTMLInputElement>('input[type="search"]')?.focus();
    document.body.style.overflow = "hidden";
    return () => {
      element.close();
      document.body.style.overflow = previousOverflow;
      trigger.current?.focus();
    };
  }, [open]);

  return (
    <div className="workspace-context">
      <nav className="workspace-breadcrumb" aria-label="Breadcrumb">
        <Link href="/dashboard">{roleLabels[role]}</Link>
        <ChevronRight size={14} aria-hidden="true" />
        <span aria-current="page">{current?.title ?? "Workspace"}</span>
      </nav>
      <button
        className="page-search-trigger"
        type="button"
        ref={trigger}
        aria-label="Jump to page"
        aria-haspopup="dialog"
        onClick={() => {
          setQuery("");
          setOpen(true);
        }}
      >
        <Search size={17} aria-hidden="true" />
        <span>Jump to page</span>
        <kbd>Ctrl / ⌘ K</kbd>
      </button>
      {open && (
        <dialog
          ref={dialog}
          className="page-search-dialog"
          aria-labelledby="page-search-title"
          onKeyDown={(event) => {
            if (event.key === "Escape") {
              event.preventDefault();
              setOpen(false);
            }
          }}
          onCancel={(event) => {
            event.preventDefault();
            setOpen(false);
          }}
        >
          <div className="page-search-heading">
            <div>
              <h2 id="page-search-title">Jump to a page</h2>
              <p>{roleLabels[role]}</p>
            </div>
            <button
              className="icon-button"
              type="button"
              aria-label="Close page search"
              onClick={() => setOpen(false)}
            >
              <X size={20} />
            </button>
          </div>
          <label className="page-search-input">
            <Search size={20} aria-hidden="true" />
            <input
              autoFocus
              type="search"
              aria-label="Find a page"
              placeholder="Find activities, submissions, reports…"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
            />
          </label>
          <p className="page-search-count" role="status">
            {matches.length} {matches.length === 1 ? "page" : "pages"} available
          </p>
          <nav className="page-search-results" aria-label="Page search results">
            {matches.map(({ title, path, icon: Icon }) => (
              <Link key={path} href={`/dashboard${path}`} onClick={() => setOpen(false)}>
                <Icon size={20} aria-hidden="true" />
                <span>{title}</span>
                {pathname === `/dashboard${path}` ? (
                  <small>Current page</small>
                ) : (
                  <ArrowUpRight size={16} aria-hidden="true" />
                )}
              </Link>
            ))}
            {!matches.length && (
              <p className="page-search-empty">No pages match “{query}”. Try another name.</p>
            )}
          </nav>
          <p className="page-search-hint">Tab to a page · Enter to open · Esc to close</p>
        </dialog>
      )}
    </div>
  );
}
