import Link from "next/link";
import { WorkspaceTasks } from "@/components/workspace-tasks";
import {
  ArrowUpRight,
  CalendarDays,
  FileCheck2,
  CircleCheck,
  Bell,
  ChevronRight,
  ClipboardCheck,
  UsersRound,
  Building2,
  ShieldCheck,
} from "lucide-react";
import { requireUser } from "@/lib/auth";
import { readDb } from "@/lib/store";
import { canSeeActivity, manilaDay } from "@/lib/policy";
import {
  ActivityTile,
  Empty,
  formatDate,
  PageHeading,
  RegistrationLine,
  SectionHeading,
} from "@/components/ui";

export default async function DashboardPage() {
  const user = await requireUser();
  const db = await readDb();
  const profile = db.profiles.find((p) => p.userId === user.id);
  const registrations = db.registrations
    .filter((r) => user.role === "staff" || r.userId === user.id)
    .sort((a, b) => b.submittedAt.localeCompare(a.submittedAt));
  const today = manilaDay();
  const activities = db.activities
    .filter(
      (a) =>
        canSeeActivity(user, profile?.department ?? "", a, profile) &&
        a.status === "Published" &&
        a.date >= today,
    )
    .sort((a, b) => `${a.date}T${a.time}`.localeCompare(`${b.date}T${b.time}`));
  const notifications = db.notifications.filter((n) => n.userId === user.id && !n.read);
  const announcements = db.announcements
    .filter((a) => a.department === "All departments" || a.department === profile?.department)
    .sort((a, b) => b.createdAt.localeCompare(a.createdAt));
  const student = user.role === "student";
  const staff = user.role === "staff";
  const metrics = student
    ? [
        {
          label: "Upcoming activities",
          value: activities.length,

          icon: CalendarDays,
          href: "/activities",
        },
        {
          label: "My registrations",
          value: registrations.filter((r) => r.status !== "Withdrawn").length,

          icon: FileCheck2,
          href: "/submissions",
        },
        {
          label: "Requirements accepted",
          value: registrations.filter((r) => r.status === "Accepted").length,

          icon: CircleCheck,
          href: "/submissions",
        },
        {
          label: "Unread updates",
          value: notifications.length,

          icon: Bell,
          href: "/notifications",
        },
      ]
    : staff
      ? [
          {
            label: "Published activities",
            value: db.activities.filter((a) => a.status === "Published").length,

            icon: CalendarDays,
            href: "/activities",
          },
          {
            label: "Profiles to verify",
            value: db.profiles.filter((p) => p.status === "Pending verification").length,

            icon: UsersRound,
            href: "/profiles",
          },
          {
            label: "Submissions to review",
            value: db.registrations.filter((r) => ["Submitted", "Under review"].includes(r.status))
              .length,

            icon: FileCheck2,
            href: "/submissions",
          },
          {
            label: "Attendance entries",
            value: db.attendance.length,

            icon: ClipboardCheck,
            href: "/attendance",
          },
        ]
      : user.role === "admin"
        ? [
            {
              label: "Active accounts",
              value: db.users.filter((u) => u.active).length,

              icon: UsersRound,
              href: "/accounts",
            },
            {
              label: "Audit events",
              value: db.audit.length,

              icon: ShieldCheck,
              href: "/audit",
            },
          ]
        : user.role === "viewer"
          ? [
              {
                label: "Published activities",
                value: db.activities.filter((a) => a.status === "Published").length,

                icon: CalendarDays,
                href: "/reports",
              },
              {
                label: "Registrations",
                value: db.registrations.filter((r) => r.status !== "Withdrawn").length,

                icon: FileCheck2,
                href: "/reports",
              },
            ]
          : [
              {
                label:
                  user.role === "approver" ? "Requests awaiting review" : "My facility requests",
                value: db.facilities.filter((f) =>
                  user.role === "approver" ? f.status === "Pending" : f.userId === user.id,
                ).length,

                icon: Building2,
                href: "/facilities",
              },
              {
                label: "Unread updates",
                value: notifications.length,

                icon: Bell,
                href: "/notifications",
              },
            ];
  return (
    <>
      <PageHeading
        eyebrow="Your workspace"
        title={`Welcome back, ${user.name.split(" ")[0]}.`}
        description={
          student
            ? "Your activities, requirements, and updates at a glance."
            : "A clear view of what’s happening in your office."
        }
        action={
          student ? (
            <Link href="/dashboard/profile" className="profile-status">
              <ShieldCheck size={16} />
              <span>Profile: {profile?.status ?? "Not submitted"}</span>
              <ChevronRight size={14} />
            </Link>
          ) : undefined
        }
      />
      <section className="dashboard-summary overview-summary" aria-label="Workspace summary">
        <h2>At a glance</h2>
        <div className={`metrics-grid ${metrics.length === 2 ? "two" : ""}`}>
          {metrics.map(({ label, value, icon: Icon, href }) => (
            <Link href={`/dashboard${href}`} key={label} className="metric">
              <div>
                <span>{label}</span>
                <Icon size={20} strokeWidth={1.6} />
              </div>
              <strong>{String(value).padStart(2, "0")}</strong>
            </Link>
          ))}
        </div>
      </section>
      <WorkspaceTasks user={user} db={db} />
      {student || staff ? (
        <div className="dashboard-columns">
          <div>
            <SectionHeading title="Upcoming activities" href="/dashboard/activities" />
            {activities.length ? (
              <div className="activity-grid">
                {activities.slice(0, 3).map((a) => (
                  <ActivityTile
                    key={a.id}
                    activity={a}
                    participants={
                      db.registrations.filter(
                        (r) => r.activityId === a.id && r.status !== "Withdrawn",
                      ).length
                    }
                    registration={
                      student
                        ? registrations.find(
                            (r) => r.activityId === a.id && r.status !== "Withdrawn",
                          )
                        : undefined
                    }
                  />
                ))}
              </div>
            ) : (
              <Empty
                title="No upcoming activities"
                description="New formation activities will appear here when they are published for you."
              />
            )}
            <SectionHeading
              title={student ? "My submissions" : "Recent registrations"}
              href="/dashboard/submissions"
            />
            <div className="surface">
              {registrations.slice(0, 4).map((r) => (
                <RegistrationLine
                  key={r.id}
                  registration={r}
                  activity={db.activities.find((a) => a.id === r.activityId)}
                />
              ))}
              {!registrations.length && (
                <Empty
                  title="No registrations yet"
                  description="Register for an activity to see your submissions."
                />
              )}
            </div>
          </div>
          <aside className="dashboard-aside">
            <SectionHeading title="Announcements" href="/dashboard/announcements" label="All" />
            <div className="surface announcement-preview">
              {!announcements.length && (
                <Empty
                  title="No announcements yet"
                  description="Office updates will appear here."
                />
              )}
              {announcements.slice(0, 2).map((a) => (
                <article key={a.id}>
                  <div>
                    <small>{formatDate(a.createdAt, true)}</small>
                  </div>
                  <Link href="/dashboard/announcements">
                    <h3>{a.title}</h3>
                    <ArrowUpRight size={16} />
                  </Link>
                </article>
              ))}
            </div>
          </aside>
        </div>
      ) : null}
    </>
  );
}
