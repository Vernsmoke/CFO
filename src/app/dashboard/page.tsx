import Link from "next/link";
import Image from "next/image";
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
  Sprout,
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
import { WorkflowForm } from "@/components/forms";

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
          detail: "A chance to grow",
          icon: CalendarDays,
          href: "/activities",
        },
        {
          label: "My registrations",
          value: registrations.filter((r) => r.status !== "Withdrawn").length,
          detail: "Your journey in motion",
          icon: FileCheck2,
          href: "/submissions",
        },
        {
          label: "Requirements accepted",
          value: registrations.filter((r) => r.status === "Accepted").length,
          detail: "Ready for the next step",
          icon: CircleCheck,
          href: "/submissions",
        },
        {
          label: "Unread updates",
          value: notifications.length,
          detail: "Stay in the loop",
          icon: Bell,
          href: "/notifications",
        },
      ]
    : staff
      ? [
          {
            label: "Published activities",
            value: db.activities.filter((a) => a.status === "Published").length,
            detail: "Formation in motion",
            icon: CalendarDays,
            href: "/activities",
          },
          {
            label: "Profiles to verify",
            value: db.profiles.filter((p) => p.status === "Pending verification").length,
            detail: "Awaiting your review",
            icon: UsersRound,
            href: "/profiles",
          },
          {
            label: "Submissions to review",
            value: db.registrations.filter((r) => ["Submitted", "Under review"].includes(r.status))
              .length,
            detail: "Help students move forward",
            icon: FileCheck2,
            href: "/submissions",
          },
          {
            label: "Attendance entries",
            value: db.attendance.length,
            detail: "Recorded by staff",
            icon: ClipboardCheck,
            href: "/attendance",
          },
        ]
      : user.role === "admin"
        ? [
            {
              label: "Active accounts",
              value: db.users.filter((u) => u.active).length,
              detail: "Authorized pilot accounts",
              icon: UsersRound,
              href: "/accounts",
            },
            {
              label: "Audit events",
              value: db.audit.length,
              detail: "Accountability in action",
              icon: ShieldCheck,
              href: "/audit",
            },
          ]
        : user.role === "viewer"
          ? [
              {
                label: "Published activities",
                value: db.activities.filter((a) => a.status === "Published").length,
                detail: "Summary access",
                icon: CalendarDays,
                href: "/reports",
              },
              {
                label: "Registrations",
                value: db.registrations.filter((r) => r.status !== "Withdrawn").length,
                detail: "Aggregate records only",
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
                detail: "Make space for community",
                icon: Building2,
                href: "/facilities",
              },
              {
                label: "Unread updates",
                value: notifications.length,
                detail: "Stay in the loop",
                icon: Bell,
                href: "/notifications",
              },
            ];
  return (
    <>
      <PageHeading
        eyebrow={new Intl.DateTimeFormat("en-PH", {
          timeZone: "Asia/Manila",
          weekday: "long",
          month: "long",
          day: "numeric",
          year: "numeric",
        })
          .format(new Date())
          .toUpperCase()}
        title={`Welcome back, ${user.name.split(" ")[0]}.`}
        description={
          student
            ? "A new day. A little growth. Your formation journey continues here."
            : "A shared purpose, a clearer view. Here’s what needs your attention."
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
      <section className="dashboard-hero">
        <div>
          <span className="tiny-overline">YOUR JOURNEY OF FORMATION</span>
          <h2>
            Make space for
            <br />
            <em>what matters.</em>
          </h2>
          <p>
            {student
              ? "Discover moments of reflection, faith, and connection. Every experience is a step toward becoming."
              : "Bring people together. Keep the details in order. Give our community the space to grow."}
          </p>
          <Link
            href={
              student || staff
                ? "/dashboard/activities"
                : user.role === "admin"
                  ? "/dashboard/accounts"
                  : user.role === "viewer"
                    ? "/dashboard/reports"
                    : "/dashboard/facilities"
            }
            className="button light-button"
          >
            {student ? "Explore activities" : staff ? "Manage activities" : "Open your workspace"}
            <ArrowUpRight size={17} />
          </Link>
        </div>
        <div className="hero-landscape">
          <Image
            src="/images/formation.jpg"
            alt="Illustrative scene for reflection"
            fill
            loading="eager"
            sizes="(max-width: 800px) 100vw, 38vw"
          />
          <div className="landscape-overlay" />
          <div className="landscape-words">
            <Sprout size={25} strokeWidth={1.2} />
            <p>
              Rooted in faith.
              <br />
              Growing together.
            </p>
          </div>
        </div>
      </section>
      <div className={`metrics-grid ${metrics.length === 2 ? "two" : ""}`}>
        {metrics.map(({ label, value, detail, icon: Icon, href }) => (
          <Link href={`/dashboard${href}`} key={label} className="metric">
            <div>
              <span>{label}</span>
              <Icon size={20} strokeWidth={1.6} />
            </div>
            <strong>{String(value).padStart(2, "0")}</strong>
            <small>
              {detail}
              <ArrowUpRight size={13} />
            </small>
          </Link>
        ))}
      </div>
      {student && registrations.some((r) => r.status === "Requires correction") && (
        <div className="attention-note">
          <span className="soft-icon amber-icon">
            <FileCheck2 size={20} />
          </span>
          <div>
            <strong>A small update is needed.</strong>
            <p>
              A submission needs a correction. Review the staff note and upload an updated document.
            </p>
          </div>
          <Link href="/dashboard/submissions">
            Review submission <ArrowRightIcon />
          </Link>
        </div>
      )}
      {student || staff ? (
        <div className="dashboard-columns">
          <div>
            <SectionHeading
              title={student ? "Upcoming formation activities" : "Formation at a glance"}
              href="/dashboard/activities"
            />
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
              title={student ? "My recent submissions" : "Recent registrations"}
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
                  title="Your journey starts here"
                  description="Register for an activity to see your submissions."
                />
              )}
            </div>
          </div>
          <aside className="dashboard-aside">
            <SectionHeading
              title="Office announcements"
              href="/dashboard/announcements"
              label="All"
            />
            <div className="surface announcement-preview">
              {announcements.slice(0, 2).map((a) => (
                <article key={a.id}>
                  <div>
                    <span className="small muted">CFO update</span>
                    <small>{formatDate(a.createdAt, true)}</small>
                  </div>
                  <h3>{a.title}</h3>
                  <p>{a.body}</p>
                  <Link href="/dashboard/announcements">
                    Read announcement <ArrowUpRight size={14} />
                  </Link>
                </article>
              ))}
            </div>
            <div className="quiet-card">
              <Sprout size={32} strokeWidth={1.2} />
              <h3>
                Be present.
                <br />
                <em>Be open. Be you.</em>
              </h3>
              <p>
                During formation activities, staff record your attendance. Give yourself the space
                to participate fully.
              </p>
            </div>
          </aside>
        </div>
      ) : (
        <div className="surface role-welcome">
          <ShieldCheck size={30} strokeWidth={1.2} />
          <h2>A workspace shaped around your role.</h2>
          <p>
            Use the navigation to access your authorized tasks. Records and decisions are kept
            within the permissions assigned to your account.
          </p>
          <Link
            href={metrics[0] ? `/dashboard${metrics[0].href}` : "/dashboard/notifications"}
            className="button"
          >
            Continue
            <ArrowUpRight size={17} />
          </Link>
        </div>
      )}
      {notifications.length > 0 && (
        <details className="notification-summary">
          <summary>
            {notifications.length} unread in-system update{notifications.length !== 1 ? "s" : ""}
          </summary>
          {notifications.slice(0, 3).map((n) => (
            <p key={n.id}>{n.message}</p>
          ))}
          <WorkflowForm intent="readNotifications" label="Mark updates as read" secondary />
        </details>
      )}
    </>
  );
}
function ArrowRightIcon() {
  return <ChevronRight size={16} />;
}
