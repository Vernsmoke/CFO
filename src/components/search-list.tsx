"use client";
import { useRef, useState, type ReactNode } from "react";
import { Search, SlidersHorizontal, X } from "lucide-react";
import { Empty } from "./ui";
export function SearchList({
  items,
  placeholder = "Search records…",
  className = "record-list",
  filters = [],
}: {
  items: { key: string; search: string; filter?: string; content: ReactNode }[];
  placeholder?: string;
  className?: string;
  filters?: string[];
}) {
  const [query, setQuery] = useState("");
  const [filter, setFilter] = useState("All");
  const searchRef = useRef<HTMLInputElement>(null);
  const normalizedQuery = query.trim().toLowerCase();
  const filtered = normalizedQuery.length > 0 || filter !== "All";
  const visible = items.filter(
    (item) =>
      item.search.toLowerCase().includes(normalizedQuery) &&
      (filter === "All" || item.filter === filter),
  );
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
            onChange={(e) => setQuery(e.target.value)}
          />
          {query && (
            <button
              type="button"
              className="search-clear"
              aria-label="Clear search"
              onClick={() => {
                setQuery("");
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
              onChange={(e) => setFilter(e.target.value)}
            >
              {["All", ...filters].map((f) => (
                <option key={f}>{f}</option>
              ))}
            </select>
          </label>
        )}
        {filtered && (
          <button
            type="button"
            className="reset-filters"
            onClick={() => {
              setQuery("");
              setFilter("All");
              searchRef.current?.focus();
            }}
          >
            Reset filters
          </button>
        )}
        <span className="result-count" role="status" aria-live="polite" aria-atomic="true">
          {visible.length} of {items.length} {items.length === 1 ? "record" : "records"}
        </span>
      </div>
      {visible.length ? (
        <div className={className}>
          {visible.map((item) => (
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
    </>
  );
}
