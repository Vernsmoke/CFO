import type {
  Activity,
  Database,
  DocumentRecord,
  FacilityRequest,
  Profile,
  Registration,
  Role,
  User,
} from "./types";

export const departments = [
  "College of Information Technology",
  "College of Business",
  "College of Education",
  "College of Nursing",
];
export const facilityNames = ["CPD", "Dormitory", "AVR", "Conference room", "Gymnasium"];
export function canManage(role: Role) {
  return role === "staff";
}
export function canViewReports(role: Role) {
  return role === "staff" || role === "viewer";
}
export function canSeeActivity(
  user: Pick<User, "role">,
  department: string,
  activity: Activity,
  profile?: Pick<Profile, "year" | "section">,
) {
  return (
    user.role === "staff" ||
    (activity.status !== "Draft" &&
      (activity.department === "All departments" || activity.department === department) &&
      (!activity.years?.length || activity.years.includes(profile?.year ?? "")) &&
      (!activity.sections?.length ||
        activity.sections.some(
          (section) => section.toLowerCase() === profile?.section.toLowerCase(),
        )))
  );
}
export function activeDocuments(documents: DocumentRecord[], registrationId: string) {
  return documents.filter(
    (document) => document.registrationId === registrationId && !document.supersededBy,
  );
}
export function acceptanceProblem(activity: Activity | undefined, documents: DocumentRecord[]) {
  if (documents.some((document) => document.status !== "Accepted"))
    return "Review and accept each current document before accepting this registration.";
  const missing =
    activity?.requiredDocuments?.filter(
      (requirement) =>
        !documents.some(
          (document) => document.requirement === requirement && document.status === "Accepted",
        ),
    ) ?? [];
  return missing.length ? `Missing accepted documents: ${missing.join(", ")}.` : "";
}
export function configuredFacilities(db: Pick<Database, "settings">) {
  return db.settings?.facilities ?? facilityNames;
}
export function manilaDay(value = new Date().toISOString()) {
  return new Date(Date.parse(value) + 8 * 3600000).toISOString().slice(0, 10);
}
export function registrationLabel(activity: Activity, participants: number, today = manilaDay()) {
  if (activity.status !== "Published") return "Registration unavailable";
  if (activity.deadline < today) return "Registration closed";
  if (participants >= activity.capacity) return "Activity full";
  return "Registration open";
}
export type ReportFilters = {
  activity?: string;
  department?: string;
  status?: string;
  from?: string;
  to?: string;
  group?: string;
};
export function reportMatches(registration: Registration, db: Database, filters: ReportFilters) {
  const activity = db.activities.find((item) => item.id === registration.activityId);
  const profile = db.profiles.find((item) => item.userId === registration.userId);
  const date = activity?.date ?? "";
  return (
    registration.status !== "Withdrawn" &&
    (!filters.activity || registration.activityId === filters.activity) &&
    (!filters.department || profile?.department === filters.department) &&
    (!filters.status || registration.status === filters.status) &&
    (!filters.group || registration.group === filters.group) &&
    (!filters.from || date >= filters.from) &&
    (!filters.to || date <= filters.to)
  );
}
export function overlaps(
  a: Pick<FacilityRequest, "start" | "end">,
  b: Pick<FacilityRequest, "start" | "end">,
) {
  return (
    new Date(a.start).getTime() < new Date(b.end).getTime() &&
    new Date(b.start).getTime() < new Date(a.end).getTime()
  );
}
export function conflicts(request: FacilityRequest, requests: FacilityRequest[]) {
  return requests.filter(
    (other) =>
      other.id !== request.id &&
      other.facility === request.facility &&
      ["Pending", "Approved"].includes(other.status) &&
      overlaps(request, other),
  );
}
export function isAllowedDocument(buffer: Buffer, mime: string) {
  if (mime === "application/pdf") return buffer.subarray(0, 5).toString() === "%PDF-";
  if (mime === "image/png")
    return buffer.subarray(0, 8).equals(Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]));
  if (mime === "image/jpeg") return buffer[0] === 255 && buffer[1] === 216 && buffer[2] === 255;
  return false;
}
export function csvCell(value: string) {
  const safe = /^[=+@\-\t\r]/.test(value) ? `'${value}` : value;
  return `"${safe.replaceAll('"', '""')}"`;
}
