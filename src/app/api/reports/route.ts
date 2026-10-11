import { currentUser } from "@/lib/auth";
import { audit, mutateDb } from "@/lib/store";
import { csvCell, reportMatches } from "@/lib/policy";
export async function GET(request: Request) {
  const user = await currentUser();
  if (!user) return new Response("Unauthorized", { status: 401 });
  if (user.role !== "staff") return new Response("Forbidden", { status: 403 });
  const params = new URL(request.url).searchParams;
  const filters = Object.fromEntries(
    ["activity", "department", "status", "from", "to", "group"].map((key) => [
      key,
      params.get(key) ?? "",
    ]),
  );
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
        "Group",
        "Facilitator",
      ],
    ];
    if (
      !db.users.some(
        (account) => account.id === user.id && account.active && account.role === "staff",
      )
    )
      throw new Error("Export permission has changed.");
    for (const r of db.registrations.filter((r) => reportMatches(r, db, filters))) {
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
        r.group ?? "",
        r.facilitator ?? "",
      ]);
    }
    audit(
      db,
      user,
      "Student information exported",
      "participant-report",
      `${rows.length - 1} records; CSV; Filters: ${JSON.stringify(filters)}`,
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
