import Link from "next/link";
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
import { canSeeActivity } from "@/lib/policy";
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
  const registrations = db.registrations.filter(
    (r) => user.role === "staff" || r.userId === user.id,
  );
  const activities = db.activities.filter(
    (a) => canSeeActivity(user, profile?.department ?? "", a) && a.status === "Published",
  );
  const notifications = db.notifications.filter((n) => n.userId === user.id && !n.read);
  const announcements = db.announcements.filter(
    (a) => a.department === "All departments" || a.department === profile?.department,
  );
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
        title={`Welcome back, ${user.name.split(" ")[0]}.`}
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
      {student && registrations.some((r) => r.status === "Requires correction") && (
        <div className="attention-note">
          <span className="soft-icon amber-icon">
            <FileCheck2 size={20} />
          </span>
          <div>
            <strong>Your submission needs a correction.</strong>
          </div>
          <Link href="/dashboard/submissions">
            Review submission <ArrowRightIcon />
          </Link>
        </div>
      )}
      {student || staff ? (
        <div className="dashboard-columns">
          <div>
            <SectionHeading title="Upcoming activities" href="/dashboard/activities" />
            {activities.length ? (
              <div className="activity-grid">
                {activities.slice(0, 3).map((a, i) => (
                  <ActivityTile
                    key={a.id}
                    activity={a}
                    registration={
                      student
                        ? registrations.find(
                            (r) => r.activityId === a.id && r.status !== "Withdrawn",
                          )
                        : undefined
                    }
                    index={i}
                  />
                ))}
              </div>
            ) : (
              <Empty />
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
function ArrowRightIcon() {
  return <ChevronRight size={16} />;
}
