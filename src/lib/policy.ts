import type { Activity, FacilityRequest, Role, User } from "./types";

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
export function canSeeActivity(user: Pick<User, "role">, department: string, activity: Activity) {
  return (
    user.role === "staff" ||
    (activity.status !== "Draft" &&
      (activity.department === "All departments" || activity.department === department))
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
