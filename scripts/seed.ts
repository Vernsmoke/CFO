import { access } from "node:fs/promises";
import { hashPassword } from "../src/lib/crypto";
import { writeDatabase } from "../src/lib/store";
import type { Database, Role } from "../src/lib/types";

async function seed() {
  if (process.env.DEMO_MODE !== "true" || !process.env.DEMO_PASSWORD)
    throw new Error("Set DEMO_MODE=true and DEMO_PASSWORD to seed fictional accounts.");
  try {
    await access("data/cfo.json");
    console.log("Existing data preserved. No changes made.");
    return;
  } catch (error) {
    if ((error as NodeJS.ErrnoException).code !== "ENOENT") throw error;
  }
  const accounts: [string, string, string, Role][] = [
    ["student-1", "2024-0001", "Alex Rivera", "student"],
    ["student-2", "2024-0002", "Jamie Santos", "student"],
    ["staff-1", "CFO-001", "Maria Dela Cruz", "staff"],
    ["rep-1", "DEP-001", "Daniel Reyes", "representative"],
    ["approver-1", "APR-001", "Gabriel Mendoza", "approver"],
    ["admin-1", "ADM-001", "System Administrator", "admin"],
    ["viewer-1", "VIEW-001", "Office Viewer", "viewer"],
  ];
  const now = new Date().toISOString();
  const db: Database = {
    users: accounts.map(([id, universityId, name, role]) => ({
      id,
      universityId,
      name,
      role,
      passwordHash: hashPassword(process.env.DEMO_PASSWORD!),
      active: true,
    })),
    profiles: [
      {
        userId: "student-1",
        department: "College of Information Technology",
        year: "3",
        section: "A",
        contact: "alex@example.invalid",
        status: "Verified",
        note: "Fictional pilot profile.",
      },
      {
        userId: "student-2",
        department: "College of Nursing",
        year: "2",
        section: "B",
        contact: "jamie@example.invalid",
        status: "Pending verification",
        note: "",
      },
    ],
    activities: [
      {
        id: "activity-1",
        title: "A pause. A prayer. A new beginning.",
        type: "Recollection",
        description:
          "Step away from the everyday and make room for reflection, meaningful conversations, and a renewed sense of purpose.",
        date: "2026-11-06",
        time: "08:00",
        venue: "University Chapel",
        department: "All departments",
        deadline: "2026-11-03",
        requirements: "Reflection form; parent or guardian consent",
        status: "Published",
        capacity: 120,
        createdBy: "staff-1",
      },
      {
        id: "activity-2",
        title: "Rooted in faith, growing together",
        type: "Retreat",
        description:
          "A day of community and guided reflection for the College of Information Technology.",
        date: "2026-11-20",
        time: "07:30",
        venue: "Formation Center",
        department: "College of Information Technology",
        deadline: "2026-11-15",
        requirements: "Consent form; retreat registration form",
        status: "Published",
        capacity: 80,
        createdBy: "staff-1",
      },
      {
        id: "activity-3",
        title: "First Friday Eucharistic Celebration",
        type: "Liturgy",
        description: "Gather as one university community for prayer and thanksgiving.",
        date: "2026-12-04",
        time: "09:00",
        venue: "University Chapel",
        department: "All departments",
        deadline: "2026-12-03",
        requirements: "No supporting documents required",
        status: "Published",
        capacity: 300,
        createdBy: "staff-1",
      },
    ],
    registrations: [
      {
        id: "CFO-DEMO-001",
        userId: "student-1",
        activityId: "activity-1",
        status: "Requires correction",
        note: "Please upload a readable copy of the consent form.",
        submittedAt: now,
      },
    ],
    documents: [],
    attendance: [],
    facilities: [
      {
        id: "facility-1",
        userId: "rep-1",
        facility: "AVR",
        purpose: "Department formation orientation",
        start: "2026-11-05T09:00:00+08:00",
        end: "2026-11-05T11:00:00+08:00",
        participants: 40,
        status: "Pending",
        note: "",
      },
    ],
    announcements: [
      {
        id: "announcement-1",
        title: "A little preparation goes a long way",
        body: "Please check your activity requirements before the registration deadline. If a document needs correction, you can resubmit it from My submissions.",
        department: "All departments",
        createdAt: now,
        author: "staff-1",
      },
      {
        id: "announcement-2",
        title: "Be present. Be open. Be you.",
        body: "For formation activities, attendance is recorded by authorized staff. Keep your phone away and give yourself the space to participate.",
        department: "All departments",
        createdAt: now,
        author: "staff-1",
      },
    ],
    audit: [],
    sessions: [],
    attempts: [],
    notifications: [
      {
        id: "notice-1",
        userId: "student-1",
        message:
          "Your recollection submission needs a correction. Review the staff note in My submissions.",
        createdAt: now,
        read: false,
      },
    ],
  };
  await writeDatabase(db);
  console.log("Fictional pilot data created. Seven role accounts are available.");
}
seed().catch((error) => {
  console.error(error.message);
  process.exitCode = 1;
});
