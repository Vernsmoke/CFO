import { currentUser } from "@/lib/auth";
import { audit, mutateDb } from "@/lib/store";
import { csvCell } from "@/lib/policy";
export async function GET() {
  const user = await currentUser();
  if (!user) return new Response("Unauthorized", { status: 401 });
  if (user.role !== "staff") return new Response("Forbidden", { status: 403 });
  const csv = await mutateDb((db) => {
    const rows = [
      [
        "Reference",
        "University ID Number",
        "Name",
        "Activity",
        "Submission status",
        "Time-in UTC",
        "Time-out UTC",
      ],
    ];
    for (const r of db.registrations.filter((r) => r.status !== "Withdrawn")) {
      const student = db.users.find((u) => u.id === r.userId);
      const activity = db.activities.find((a) => a.id === r.activityId);
      const entry = db.attendance.find((a) => a.registrationId === r.id);
      rows.push([
        r.id,
        student?.universityId ?? "",
        student?.name ?? "",
        activity?.title ?? "",
        r.status,
        entry?.timeIn ?? "",
        entry?.timeOut ?? "",
      ]);
    }
    audit(
      db,
      user,
      "Student information exported",
      "participant-report",
      `${rows.length - 1} records; CSV`,
    );
    return rows.map((row) => row.map(csvCell).join(",")).join("\r\n");
  });
  return new Response(csv, {
    headers: {
      "Content-Type": "text/csv; charset=utf-8",
      "Content-Disposition": "attachment; filename=formation-participants.csv",
      "Cache-Control": "private, no-store",
    },
  });
}
