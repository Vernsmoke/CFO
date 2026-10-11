"use client";
import { useRef, type ReactNode } from "react";
import { useSearchParams } from "next/navigation";
import { Search, SlidersHorizontal, X } from "lucide-react";
import { Empty } from "./ui";
import { RefreshRecords } from "./record-tools";
import { manilaDay } from "@/lib/policy";

type Dimension = { key: string; label: string; options: { value: string; label: string }[] };
export function SearchList({
  items,
  placeholder = "Search records…",
  className = "record-list",
  filters = [],
  dimensions = [],
  dates = false,
  pageSize = 10,
}: {
  items: {
    key: string;
    search: string;
    filter?: string;
    date?: string;
    values?: Record<string, string>;
    content: ReactNode;
  }[];
  placeholder?: string;
  className?: string;
  filters?: string[];
  dimensions?: Dimension[];
  dates?: boolean;
  pageSize?: number;
}) {
  const params = useSearchParams();
  const query = params.get("q") ?? "";
  const filter = params.get("filter") ?? "All";
  const from = dates ? (params.get("from") ?? "") : "";
  const to = dates ? (params.get("to") ?? "") : "";
  const searchRef = useRef<HTMLInputElement>(null);
  const normalizedQuery = query.trim().toLowerCase();
  const filtered = Boolean(
    normalizedQuery ||
    filter !== "All" ||
    from ||
    to ||
    dimensions.some((dimension) => params.get(dimension.key)),
  );
  const visible = items.filter(
    (item) =>
      item.search.toLowerCase().includes(normalizedQuery) &&
      (filter === "All" || item.filter === filter) &&
      (!from || Boolean(item.date && item.date.slice(0, 10) >= from)) &&
      (!to || Boolean(item.date && item.date.slice(0, 10) <= to)) &&
      dimensions.every(
        (dimension) =>
          !params.get(dimension.key) || item.values?.[dimension.key] === params.get(dimension.key),
      ),
  );
  const pages = Math.max(1, Math.ceil(visible.length / pageSize));
  const page = Math.min(pages, Math.max(1, Math.floor(Number(params.get("page"))) || 1));
  function update(changes: Record<string, string>) {
    const next = new URLSearchParams(params.toString());
    next.delete("page");
    for (const [key, value] of Object.entries(changes)) {
      if (value && value !== "All") next.set(key, value);
      else next.delete(key);
    }
    window.history.replaceState(null, "", `${location.pathname}${next.size ? `?${next}` : ""}`);
  }
  const reset = () => {
    update(
      Object.fromEntries(
        ["q", "filter", "from", "to", ...dimensions.map((dimension) => dimension.key)].map(
          (key) => [key, ""],
        ),
      ),
    );
    searchRef.current?.focus();
  };
  return (
    <>
      <div className="list-toolbar">
        <div className="search-field">
          <Search size={18} />
          <input
            ref={searchRef}
            type="search"
            aria-label="Search records"
            placeholder={placeholder}
            value={query}
            onChange={(event) => update({ q: event.target.value })}
          />
          {query && (
            <button
              type="button"
              className="search-clear"
              aria-label="Clear search"
              onClick={() => {
                update({ q: "" });
                searchRef.current?.focus();
              }}
            >
              <X size={16} />
            </button>
          )}
        </div>
        {filters.length > 0 && (
          <label className="filter-field">
            <SlidersHorizontal size={16} />
            <select
              aria-label="Filter records"
              value={filter}
              onChange={(event) => update({ filter: event.target.value })}
            >
              {["All", ...filters].map((value) => (
                <option key={value}>{value}</option>
              ))}
            </select>
          </label>
        )}
        {filtered && (
          <button type="button" className="reset-filters" onClick={reset}>
            Reset filters
          </button>
        )}
        <span className="result-count" role="status" aria-live="polite" aria-atomic="true">
          {visible.length} of {items.length} {items.length === 1 ? "record" : "records"}
        </span>
        <RefreshRecords />
      </div>
      {(dimensions.length > 0 || dates) && (
        <details
          className="list-filters"
          open={Boolean(from || to || dimensions.some((dimension) => params.get(dimension.key)))}
        >
          <summary>More filters</summary>
          <div className="form-grid">
            {dimensions.map((dimension) => (
              <label className="field" key={dimension.key}>
                <span>{dimension.label}</span>
                <select
                  value={params.get(dimension.key) ?? ""}
                  onChange={(event) => update({ [dimension.key]: event.target.value })}
                >
                  <option value="">All</option>
                  {dimension.options.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </label>
            ))}
            {dates && (
              <>
                <label className="field">
                  <span>From date</span>
                  <input
                    type="date"
                    value={from}
                    onChange={(event) => update({ from: event.target.value })}
                  />
                </label>
                <label className="field">
                  <span>To date</span>
                  <input
                    type="date"
                    value={to}
                    min={from}
                    onChange={(event) => update({ to: event.target.value })}
                  />
                </label>
              </>
            )}
          </div>
          {dates && (
            <div className="date-presets">
              <button
                type="button"
                className="button secondary"
                onClick={() => update({ from: manilaDay(), to: manilaDay() })}
              >
                Today
              </button>
              <button
                type="button"
                className="button secondary"
                onClick={() => {
                  const today = manilaDay();
                  const date = new Date(`${today}T00:00:00Z`);
                  date.setUTCDate(date.getUTCDate() - ((date.getUTCDay() + 6) % 7));
                  update({ from: date.toISOString().slice(0, 10), to: today });
                }}
              >
                This week
              </button>
            </div>
          )}
        </details>
      )}
      {from && to && from > to && (
        <p className="field-error" role="alert">
          The end date must follow the start date.
        </p>
      )}
      {visible.length ? (
        <div className={className}>
          {visible.slice((page - 1) * pageSize, page * pageSize).map((item) => (
            <div key={item.key}>{item.content}</div>
          ))}
        </div>
      ) : (
        <Empty
          title={filtered ? "No matching records" : "No records yet"}
          description={
            filtered
              ? "Try another search or reset your filters."
              : "Records will appear here when available."
          }
        />
      )}
      {pages > 1 && (
        <nav className="pagination" aria-label="Record pages">
          <button
            type="button"
            className="button secondary"
            disabled={page === 1}
            onClick={() => update({ page: String(page - 1) })}
          >
            Previous
          </button>
          <span>
            Page {page} of {pages}
          </span>
          <button
            type="button"
            className="button secondary"
            disabled={page === pages}
            onClick={() => update({ page: String(page + 1) })}
          >
            Next
          </button>
        </nav>
      )}
    </>
  );
}
