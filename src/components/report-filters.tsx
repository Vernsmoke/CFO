"use client";
import { useRouter } from "next/navigation";
export function ReportFilters({
  activities,
  departments,
  groups,
  values,
  exportAllowed,
}: {
  activities: { id: string; title: string }[];
  departments: string[];
  groups: string[];
  values: Record<string, string | undefined>;
  exportAllowed: boolean;
}) {
  const router = useRouter();
  const params = new URLSearchParams(
    Object.entries(values).filter((entry): entry is [string, string] => Boolean(entry[1])),
  );
  return (
    <div className="surface report-filter-panel">
      {values.from && values.to && values.from > values.to && (
        <p className="field-error" role="alert">
          The report end date must follow the start date.
        </p>
      )}
      <form
        className="form-grid"
        action={(data) => {
          const next = new URLSearchParams();
          for (const [key, value] of data)
            if (typeof value === "string" && value) next.set(key, value);
          router.push(`/dashboard/reports${next.size ? `?${next}` : ""}`);
        }}
      >
        <label className="field">
          <span>Report activity</span>
          <select name="activity" defaultValue={values.activity ?? ""}>
            <option value="">All activities</option>
            {activities.map((activity) => (
              <option key={activity.id} value={activity.id}>
                {activity.title}
              </option>
            ))}
          </select>
        </label>
        <label className="field">
          <span>Report department</span>
          <select name="department" defaultValue={values.department ?? ""}>
            <option value="">All departments</option>
            {departments.map((department) => (
              <option key={department}>{department}</option>
            ))}
          </select>
        </label>
        <label className="field">
          <span>Report status</span>
          <select name="status" defaultValue={values.status ?? ""}>
            <option value="">All statuses</option>
            {["Submitted", "Under review", "Requires correction", "Accepted"].map((status) => (
              <option key={status}>{status}</option>
            ))}
          </select>
        </label>
        {groups.length > 0 && (
          <label className="field">
            <span>Report group</span>
            <select name="group" defaultValue={values.group ?? ""}>
              <option value="">All groups</option>
              {groups.map((group) => (
                <option key={group}>{group}</option>
              ))}
            </select>
          </label>
        )}
        <label className="field">
          <span>Report from date</span>
          <input name="from" type="date" defaultValue={values.from} />
        </label>
        <label className="field">
          <span>Report to date</span>
          <input name="to" type="date" defaultValue={values.to} min={values.from} />
        </label>
        <div className="report-filter-actions">
          <button className="button">Apply filters</button>
          <button
            type="button"
            className="button secondary"
            onClick={() => router.push("/dashboard/reports")}
          >
            Reset
          </button>
          {exportAllowed && (
            <a className="button secondary" href={`/api/reports${params.size ? `?${params}` : ""}`}>
              Export filtered CSV
            </a>
          )}
        </div>
      </form>
    </div>
  );
}
