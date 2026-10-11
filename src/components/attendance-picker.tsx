"use client";
import { useRouter } from "next/navigation";
export function AttendancePicker({
  activities,
  selected,
}: {
  activities: { id: string; title: string }[];
  selected: string;
}) {
  const router = useRouter();
  return (
    <label className="field attendance-picker">
      <span>Attendance activity</span>
      <select
        value={selected}
        onChange={(event) => {
          const params = new URLSearchParams(location.search);
          params.set("activity", event.target.value);
          params.delete("page");
          router.push(`/dashboard/attendance?${params}`, { scroll: false });
        }}
      >
        <option value="All">All activities</option>
        {activities.map((activity) => (
          <option key={activity.id} value={activity.id}>
            {activity.title}
          </option>
        ))}
      </select>
    </label>
  );
}
