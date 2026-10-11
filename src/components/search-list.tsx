"use client";
import { useState, type ReactNode } from "react";
import { Search, SlidersHorizontal } from "lucide-react";
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
  const visible = items.filter(
    (item) =>
      item.search.toLowerCase().includes(query.toLowerCase()) &&
      (filter === "All" || item.filter === filter),
  );
  return (
    <>
      <div className="list-toolbar">
        <label className="search-field">
          <Search size={18} />
          <input
            aria-label="Search records"
            placeholder={placeholder}
            value={query}
            onChange={(e) => setQuery(e.target.value)}
          />
        </label>
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
        <span className="result-count">
          {visible.length} {visible.length === 1 ? "record" : "records"}
        </span>
      </div>
      {visible.length ? (
        <div className={className}>
          {visible.map((item) => (
            <div key={item.key}>{item.content}</div>
          ))}
        </div>
      ) : (
        <Empty title="No matching records" description="Try another search or filter." />
      )}
    </>
  );
}
