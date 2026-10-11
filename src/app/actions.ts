"use server";

import { randomBytes, randomUUID } from "node:crypto";
import { mkdir, writeFile, unlink } from "node:fs/promises";
import path from "node:path";
import { cookies, headers } from "next/headers";
import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { z } from "zod";
import { currentUser, requireUser } from "@/lib/auth";
import { hashToken, verifyPassword } from "@/lib/crypto";
import { audit, mutateDb, notify, uploadDirectory } from "@/lib/store";
import {
  canSeeActivity,
  conflicts,
  departments,
  facilityNames,
  isAllowedDocument,
} from "@/lib/policy";
import type { ActionResult, Activity, Role } from "@/lib/types";

function text(form: FormData, key: string, max = 2000) {
  return z.string().trim().min(1, `${key} is required.`).max(max).parse(form.get(key));
}
function optional(form: FormData, key: string, max = 2000) {
  return z
    .string()
    .trim()
    .max(max)
    .parse(form.get(key) ?? "");
}
function enumValue<T extends string>(form: FormData, key: string, values: readonly T[]): T {
  const value = text(form, key);
  if (!values.includes(value as T)) throw new Error(`Invalid ${key}.`);
  return value as T;
}
function dateValue(form: FormData, key: string) {
  const value = text(form, key, 10);
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value) || Number.isNaN(Date.parse(value)))
    throw new Error(`Invalid ${key}.`);
  return value;
}
const success = (message: string): ActionResult => ({ ok: true, message });
function failure(error: unknown): ActionResult {
  return {
    ok: false,
    message:
      error instanceof z.ZodError
        ? error.issues[0].message
        : error instanceof Error
          ? error.message
          : "Unable to complete this action.",
  };
}

export async function loginAction(_previous: ActionResult, form: FormData): Promise<ActionResult> {
  try {
    const universityId = text(form, "universityId", 50);
    const password = text(form, "password", 128);
    const ip = (await headers()).get("x-forwarded-for")?.split(",")[0] ?? "local";
    const accepted = await mutateDb((db) => {
      const key = `${ip}:${universityId.toLowerCase()}`;
      db.attempts = db.attempts.filter((a) => a.resetAt > Date.now());
      let attempt = db.attempts.find((a) => a.key === key);
      if (attempt && attempt.count >= 5)
        throw new Error("Too many attempts. Try again in 15 minutes.");
      if (!attempt) {
        attempt = { key, count: 0, resetAt: Date.now() + 15 * 60 * 1000 };
        db.attempts.push(attempt);
      }
      attempt.count++;
      const user = db.users.find(
        (u) => u.universityId.toLowerCase() === universityId.toLowerCase() && u.active,
      );
      if (!user || !verifyPassword(password, user.passwordHash)) return null;
      db.attempts = db.attempts.filter((a) => a.key !== key);
      const token = randomBytes(32).toString("hex");
      db.sessions = db.sessions.filter((s) => s.expiresAt > Date.now());
      db.sessions.push({
        tokenHash: hashToken(token),
        userId: user.id,
        expiresAt: Date.now() + 8 * 60 * 60 * 1000,
      });
      audit(db, user, "Signed in", user.universityId);
      return token;
    });
    if (!accepted) return { ok: false, message: "University ID Number or password is incorrect." };
    (await cookies()).set("cfo_session", accepted, {
      httpOnly: true,
      secure: process.env.NODE_ENV === "production",
      sameSite: "lax",
      path: "/",
      maxAge: 8 * 60 * 60,
    });
  } catch (error) {
    return failure(error);
  }
  redirect("/dashboard");
}

export async function logoutAction() {
  const token = (await cookies()).get("cfo_session")?.value;
  if (token)
    await mutateDb((db) => {
      db.sessions = db.sessions.filter((s) => s.tokenHash !== hashToken(token));
    });
  (await cookies()).delete("cfo_session");
  redirect("/login");
}

export async function workflowAction(
  _previous: ActionResult,
  form: FormData,
): Promise<ActionResult> {
  const user = await currentUser();
  if (!user) return { ok: false, message: "Your session has ended. Sign in again." };
  let uploadedPath = "";
  try {
    const intent = text(form, "intent", 50);
    const roles: Record<string, Role[]> = {
      profile: ["student"],
      verifyProfile: ["staff"],
      createActivity: ["staff"],
      editActivity: ["staff"],
      activityStatus: ["staff"],
      register: ["student"],
      withdraw: ["student"],
      upload: ["student"],
      review: ["staff"],
      attendance: ["staff"],
      correctAttendance: ["staff"],
      requestFacility: ["representative"],
      decideFacility: ["approver"],
      announcement: ["staff"],
      reminder: ["staff"],
      readNotifications: ["student", "staff", "representative", "approver", "admin", "viewer"],
      account: ["admin"],
    };
    if (!roles[intent]?.includes(user.role))
      throw new Error("You do not have permission for this action.");
    let upload: { storageName: string; filename: string; mime: string } | undefined;
    if (intent === "upload") {
      const file = form.get("file");
      if (!(file instanceof File) || !file.size)
        throw new Error("Choose a PDF, PNG, or JPEG file.");
      if (file.size > 5 * 1024 * 1024) throw new Error("Files must be 5 MB or smaller.");
      const buffer = Buffer.from(await file.arrayBuffer());
      if (!isAllowedDocument(buffer, file.type))
        throw new Error("File content must be a valid PDF, PNG, or JPEG.");
      const storageName = randomUUID();
      await mkdir(uploadDirectory, { recursive: true });
      uploadedPath = path.join(uploadDirectory, storageName);
      await writeFile(uploadedPath, buffer, { mode: 0o600 });
      upload = {
        storageName,
        filename: file.name.replace(/[^a-zA-Z0-9._ ()-]/g, "_").slice(0, 120),
        mime: file.type,
      };
    }
    const result = await mutateDb((db) => {
      const authorized = db.users.find((u) => u.id === user.id && u.active);
      if (!authorized || authorized.role !== user.role || !roles[intent].includes(authorized.role))
        throw new Error("Your permissions have changed. Sign in again.");
      const id = optional(form, "id", 100);
      const now = new Date().toISOString();
      if (intent === "profile") {
        const department = enumValue(form, "department", departments);
        const year = enumValue(form, "year", ["1", "2", "3", "4", "5"]);
        const section = text(form, "section", 30);
        const contact = z
          .email()
          .max(120)
          .parse(text(form, "contact", 120));
        const profile = db.profiles.find((p) => p.userId === user.id);
        const next = {
          userId: user.id,
          department,
          year,
          section,
          contact,
          status: "Pending verification" as const,
          note: "",
        };
        if (profile) Object.assign(profile, next);
        else db.profiles.push(next);
        audit(db, user, "Profile submitted", user.universityId);
        return success("Profile submitted for staff verification.");
      }
      if (intent === "verifyProfile") {
        const profile = db.profiles.find((p) => p.userId === id);
        if (!profile || profile.status !== "Pending verification")
          throw new Error("This profile is not awaiting verification.");
        const status = enumValue(form, "status", ["Verified", "Correction requested"]);
        const note =
          status === "Correction requested" ? text(form, "note") : optional(form, "note");
        profile.status = status;
        profile.note = note;
        audit(db, user, "Profile review", id, `${status}: ${note}`);
        notify(db, id, `Profile: ${status}. ${note}`);
        return success("Profile review saved.");
      }
      if (intent === "createActivity" || intent === "editActivity") {
        const date = dateValue(form, "date");
        const deadline = dateValue(form, "deadline");
        if (deadline > date)
          throw new Error("Registration deadline must be on or before the activity date.");
        const time = text(form, "time", 5);
        if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(time))
          throw new Error("Enter a valid activity time.");
        const activity: Activity = {
          id: randomUUID(),
          title: text(form, "title", 160),
          description: text(form, "description"),
          type: enumValue(form, "type", [
            "Retreat",
            "Recollection",
            "Liturgy",
            "Formation program",
          ]),
          date,
          time,
          deadline,
          venue: text(form, "venue", 100),
          department: enumValue(form, "department", ["All departments", ...departments]),
          requirements: text(form, "requirements", 1000),
          status: enumValue(form, "status", ["Draft", "Published"]),
          capacity: z.coerce.number().int().min(1).max(10000).parse(form.get("capacity")),
          createdBy: user.id,
        };
        if (intent === "editActivity") {
          const existing = db.activities.find((a) => a.id === id);
          if (!existing) throw new Error("Activity not found.");
          const before = JSON.stringify(existing);
          Object.assign(existing, activity, {
            id: existing.id,
            createdBy: existing.createdBy,
            status: existing.status,
          });
          audit(
            db,
            user,
            "Activity updated",
            id,
            `Before: ${before}; After: ${JSON.stringify(existing)}`,
          );
          for (const r of db.registrations.filter(
            (r) => r.activityId === id && r.status !== "Withdrawn",
          ))
            notify(
              db,
              r.userId,
              `Details updated for ${existing.title}. Please check the latest schedule and requirements.`,
            );
        } else {
          db.activities.push(activity);
          audit(db, user, "Activity created", activity.id, activity.title);
        }
        return success("Activity saved.");
      }
      if (intent === "activityStatus") {
        const activity = db.activities.find((a) => a.id === id);
        if (!activity) throw new Error("Activity not found.");
        const before = activity.status;
        activity.status = enumValue(form, "status", [
          "Draft",
          "Published",
          "Postponed",
          "Cancelled",
          "Archived",
        ]);
        audit(db, user, "Activity status changed", id, `${before} → ${activity.status}`);
        for (const r of db.registrations.filter(
          (r) => r.activityId === id && r.status !== "Withdrawn",
        ))
          notify(db, r.userId, `${activity.title}: ${activity.status}.`);
        return success("Activity status updated and participants notified in-system.");
      }
      if (intent === "register") {
        const activity = db.activities.find((a) => a.id === id);
        const profile = db.profiles.find((p) => p.userId === user.id);
        if (
          !activity ||
          !canSeeActivity(user, profile?.department ?? "", activity) ||
          activity.status !== "Published"
        )
          throw new Error("Registration is unavailable for this activity.");
        if (new Date(`${activity.deadline}T23:59:59+08:00`).getTime() < Date.now())
          throw new Error("The registration deadline has passed.");
        if (
          db.registrations.some(
            (r) => r.activityId === id && r.userId === user.id && r.status !== "Withdrawn",
          )
        )
          throw new Error("You already have an active registration.");
        if (
          db.registrations.filter((r) => r.activityId === id && r.status !== "Withdrawn").length >=
          activity.capacity
        )
          throw new Error("This activity has reached its participant capacity.");
        const reference = `CFO-${randomUUID().slice(0, 8).toUpperCase()}`;
        db.registrations.push({
          id: reference,
          userId: user.id,
          activityId: id,
          status: "Submitted",
          note: "",
          submittedAt: now,
        });
        audit(db, user, "Registered for activity", reference, activity.title);
        return success(
          `Registration submitted. Reference: ${reference}. Upload your supporting documents in My submissions.`,
        );
      }
      if (["upload", "withdraw"].includes(intent)) {
        const registration = db.registrations.find((r) => r.id === id && r.userId === user.id);
        if (!registration || ["Accepted", "Withdrawn"].includes(registration.status))
          throw new Error("This registration cannot be changed. Contact the CFO.");
        if (intent === "withdraw") {
          registration.status = "Withdrawn";
          audit(db, user, "Registration withdrawn", id);
          return success("Registration withdrawn.");
        }
        if (!upload) throw new Error("No upload available.");
        db.documents.push({
          ...upload,
          id: randomUUID(),
          registrationId: id,
          userId: user.id,
          uploadedAt: now,
        });
        registration.status = "Submitted";
        audit(db, user, "Document uploaded", id, upload.filename);
        return success("Document uploaded securely and submission returned for review.");
      }
      if (intent === "review") {
        const registration = db.registrations.find((r) => r.id === id);
        if (!registration || registration.status === "Withdrawn")
          throw new Error("Submission unavailable for review.");
        const status = enumValue(form, "status", [
          "Under review",
          "Requires correction",
          "Accepted",
        ]);
        const note = status === "Requires correction" ? text(form, "note") : optional(form, "note");
        const before = registration.status;
        registration.status = status;
        registration.note = note;
        audit(db, user, "Submission reviewed", id, `${before} → ${status}: ${note}`);
        notify(db, registration.userId, `${id}: ${status}. ${note}`);
        return success("Document review saved. Acceptance confirms CFO requirements only.");
      }
      if (["attendance", "correctAttendance"].includes(intent)) {
        const registration = db.registrations.find((r) => r.id === id && r.status !== "Withdrawn");
        if (!registration) throw new Error("Participant not found.");
        let attendance = db.attendance.find((a) => a.registrationId === id);
        if (intent === "correctAttendance") {
          if (!attendance) throw new Error("Attendance has not yet been recorded.");
          const reason = text(form, "reason");
          const before = JSON.stringify(attendance);
          const timeIn = optional(form, "timeIn", 30);
          const timeOut = optional(form, "timeOut", 30);
          for (const v of [timeIn, timeOut])
            if (v && !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(v))
              throw new Error("Invalid attendance time.");
          const nextIn = timeIn ? new Date(`${timeIn}:00+08:00`).toISOString() : "";
          const nextOut = timeOut ? new Date(`${timeOut}:00+08:00`).toISOString() : "";
          if (nextOut && (!nextIn || nextOut < nextIn))
            throw new Error("Time-out must follow time-in.");
          attendance.timeIn = nextIn;
          attendance.timeOut = nextOut;
          audit(
            db,
            user,
            "Attendance corrected",
            id,
            `Before: ${before}; After: ${JSON.stringify(attendance)}; Reason: ${reason}`,
          );
          return success("Correction saved with original values and reason in the audit history.");
        }
        const entry = enumValue(form, "entry", ["timeIn", "timeOut"]);
        if (!attendance) {
          attendance = { id: randomUUID(), registrationId: id, timeIn: "", timeOut: "" };
          db.attendance.push(attendance);
        }
        if (attendance[entry])
          throw new Error("This entry already exists. Use an authorized correction.");
        if (entry === "timeOut" && !attendance.timeIn)
          throw new Error("Record time-in before time-out.");
        attendance[entry] = now;
        audit(db, user, "Attendance recorded", id, `${entry}: ${now}`);
        return success(`${entry === "timeIn" ? "Time-in" : "Time-out"} recorded.`);
      }
      if (intent === "requestFacility") {
        const start = text(form, "start", 30);
        const end = text(form, "end", 30);
        for (const v of [start, end])
          if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(v))
            throw new Error("Enter valid dates and times.");
        const startISO = new Date(`${start}:00+08:00`).toISOString();
        const endISO = new Date(`${end}:00+08:00`).toISOString();
        if (startISO >= endISO || Date.parse(startISO) < Date.now())
          throw new Error("Use a future start time and a later end time.");
        const request = {
          id: randomUUID(),
          userId: user.id,
          facility: enumValue(form, "facility", facilityNames),
          purpose: text(form, "purpose", 500),
          start: startISO,
          end: endISO,
          participants: z.coerce.number().int().min(1).max(10000).parse(form.get("participants")),
          status: "Pending" as const,
          note: "",
        };
        if (id) {
          const existing = db.facilities.find(
            (r) => r.id === id && r.userId === user.id && r.status === "Returned for correction",
          );
          if (!existing) throw new Error("This request cannot be resubmitted.");
          const before = JSON.stringify(existing);
          Object.assign(existing, request, { id });
          audit(
            db,
            user,
            "Facility request resubmitted",
            id,
            `Before: ${before}; After: ${JSON.stringify(existing)}`,
          );
        } else {
          db.facilities.push(request);
          audit(db, user, "Facility requested", request.id, request.facility);
        }
        const count = conflicts({ ...request, id: id || request.id }, db.facilities).length;
        return success(
          `Request submitted.${count ? ` ${count} possible scheduling conflict(s) flagged for the approver.` : ""}`,
        );
      }
      if (intent === "decideFacility") {
        const request = db.facilities.find((r) => r.id === id);
        if (!request || !["Pending", "Returned for correction"].includes(request.status))
          throw new Error("Request is no longer awaiting a decision.");
        const status = enumValue(form, "status", [
          "Approved",
          "Rejected",
          "Returned for correction",
        ]);
        const note = status === "Approved" ? optional(form, "note") : text(form, "note");
        if (
          status === "Approved" &&
          conflicts(request, db.facilities).some((r) => r.status === "Approved")
        )
          throw new Error(
            "An approved booking overlaps. Resolve that booking before approving this request.",
          );
        request.status = status;
        request.note = note;
        audit(db, user, "Facility decision", id, `${status}: ${note}`);
        notify(db, request.userId, `${request.facility} request: ${status}. ${note}`);
        return success("Facility decision saved.");
      }
      if (intent === "announcement") {
        const announcement = {
          id: randomUUID(),
          title: text(form, "title", 160),
          body: text(form, "body"),
          department: enumValue(form, "department", ["All departments", ...departments]),
          createdAt: now,
          author: user.id,
        };
        db.announcements.unshift(announcement);
        for (const recipient of db.users.filter(
          (u) =>
            u.active &&
            u.role === "student" &&
            (announcement.department === "All departments" ||
              db.profiles.some(
                (p) => p.userId === u.id && p.department === announcement.department,
              )),
        ))
          notify(db, recipient.id, announcement.title);
        audit(db, user, "Announcement published", announcement.id, announcement.title);
        return success("Announcement published with targeted in-system notifications.");
      }
      if (intent === "reminder") {
        const activity = db.activities.find((a) => a.id === id);
        if (!activity) throw new Error("Activity not found.");
        const registrations = db.registrations.filter(
          (r) => r.activityId === id && !["Accepted", "Withdrawn"].includes(r.status),
        );
        for (const r of registrations)
          notify(
            db,
            r.userId,
            `Reminder: check incomplete requirements for ${activity.title}. Deadline: ${activity.deadline}.`,
          );
        audit(
          db,
          user,
          "In-system reminders created",
          id,
          `${registrations.length} recipients; no external delivery or read confirmation.`,
        );
        return success(
          `${registrations.length} in-system reminders created. Accepted submissions were excluded.`,
        );
      }
      if (intent === "readNotifications") {
        for (const n of db.notifications.filter((n) => n.userId === user.id)) n.read = true;
        return success("Notifications marked as read.");
      }
      if (intent === "account") {
        const account = db.users.find((u) => u.id === id);
        if (!account || account.id === user.id)
          throw new Error("You cannot modify your own account here.");
        const role = enumValue(form, "role", [
          "student",
          "staff",
          "approver",
          "representative",
          "admin",
          "viewer",
        ]);
        const active = enumValue(form, "active", ["true", "false"]) === "true";
        const before = `${account.role}, active=${account.active}`;
        account.role = role;
        account.active = active;
        db.sessions = db.sessions.filter((s) => s.userId !== id);
        audit(db, user, "Account permission changed", id, `${before} → ${role}, active=${active}`);
        return success("Account updated and previous sessions revoked.");
      }
      throw new Error("Unsupported action.");
    });
    revalidatePath("/dashboard", "layout");
    return result;
  } catch (error) {
    if (uploadedPath) await unlink(uploadedPath).catch(() => undefined);
    return failure(error);
  }
}

export async function checkSession() {
  return Boolean(await requireUser());
}
