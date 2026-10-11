"use client";
import { useTransition } from "react";
import { useRouter } from "next/navigation";
import { RefreshCw } from "lucide-react";
export function RefreshRecords() {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  return (
    <div className="refresh-records">
      <button
        type="button"
        className="button secondary"
        disabled={pending}
        onClick={() => startTransition(() => router.refresh())}
      >
        <RefreshCw size={16} className={pending ? "spin" : ""} />
        {pending ? "Refreshing…" : "Refresh"}
      </button>
      <span role="status">{pending ? "Updating records…" : ""}</span>
    </div>
  );
}
