import Link from "next/link";
import { ArrowUpRight, CalendarDays, MapPin, Clock3, FileCheck2, Inbox } from "lucide-react";
import type { Activity, Registration } from "@/lib/types";
import { registrationLabel } from "@/lib/policy";
export function formatDate(date: string, short = false) {
  return new Intl.DateTimeFormat("en-PH", {
    timeZone: "Asia/Manila",
    month: short ? "short" : "long",
    day: "numeric",
    year: short ? undefined : "numeric",
  }).format(new Date(date.length === 10 ? `${date}T12:00:00+08:00` : date));
}
export function formatTime(date: string) {
  return date
    ? new Intl.DateTimeFormat("en-PH", {
        timeZone: "Asia/Manila",
        hour: "numeric",
        minute: "2-digit",
      }).format(new Date(date))
    : "—";
}
export function Status({ children }: { children: string }) {
  const color = ["Verified", "Accepted", "Approved", "Published"].includes(children)
    ? "green"
    : [
          "Requires correction",
          "Correction requested",
          "Returned for correction",
          "Postponed",
        ].includes(children)
      ? "amber"
      : ["Rejected", "Cancelled", "Withdrawn"].includes(children)
        ? "gray"
        : "blue";
  return (
    <span className={`status ${color}`}>
      <i />
      {children}
    </span>
  );
}
export function PageHeading({
  eyebrow,
  title,
  description,
  action,
}: {
  eyebrow?: string;
  title: string;
  description?: string;
  action?: React.ReactNode;
}) {
  return (
    <div className="page-heading">
      <div>
        {eyebrow && <span className="tiny-overline">{eyebrow}</span>}
        <h1>{title}</h1>
        {description && <p>{description}</p>}
      </div>
      {action}
    </div>
  );
}
export function Empty({
  title = "You’re all caught up.",
  description = "There are no records here yet.",
}: {
  title?: string;
  description?: string;
}) {
  return (
    <div className="empty-state">
      <Inbox size={35} strokeWidth={1.2} />
      <h3>{title}</h3>
      <p>{description}</p>
    </div>
  );
}
export function SectionHeading({
  title,
  href,
  label = "View all",
}: {
  title: string;
  href?: string;
  label?: string;
}) {
  return (
    <div className="section-heading">
      <h2>{title}</h2>
      {href && (
        <Link href={href}>
          {label}
          <ArrowUpRight size={16} />
        </Link>
      )}
    </div>
  );
}
export function ActivityTile({
  activity,
  registration,
  participants,
}: {
  activity: Activity;
  registration?: Registration;
  participants: number;
}) {
  const date = new Date(`${activity.date}T12:00:00Z`);
  return (
    <Link href="/dashboard/activities" className="activity-tile">
      <span className="activity-date">
        <small>{date.toLocaleString("en", { month: "short", timeZone: "UTC" })}</small>
        <strong>{date.getUTCDate()}</strong>
      </span>
      <div className="activity-tile-content">
        <div className="tile-status">
          <span className="activity-kind">{activity.type}</span>
          {registration ? (
            <Status>{registration.status}</Status>
          ) : (
            <span className="small muted">{registrationLabel(activity, participants)}</span>
          )}
        </div>
        <h3>{activity.title}</h3>
        <p>
          <CalendarDays size={14} />
          {formatDate(activity.date, true)}
          <span>·</span>
          {activity.time}
        </p>
        <p>
          <MapPin size={14} />
          {activity.venue}
        </p>
      </div>
      <ArrowUpRight className="activity-open" size={20} />
    </Link>
  );
}
export function RegistrationLine({
  activity,
  registration,
}: {
  activity?: Activity;
  registration: Registration;
}) {
  return (
    <div className="registration-line">
      <span className="soft-icon">
        <FileCheck2 size={21} />
      </span>
      <div>
        <strong>{activity?.title ?? "Formation activity"}</strong>
        <small>
          {registration.id} · {formatDate(registration.submittedAt, true)}
        </small>
      </div>
      <Status>{registration.status}</Status>
      <Link
        href="/dashboard/submissions"
        className="icon-button"
        aria-label={`View submission ${registration.id}`}
      >
        <ArrowUpRight size={19} />
      </Link>
    </div>
  );
}
export function ActivityMetadata({ activity }: { activity: Activity }) {
  return (
    <div className="activity-meta">
      <span>
        <CalendarDays size={16} />
        {formatDate(activity.date)}
      </span>
      <span>
        <Clock3 size={16} />
        {activity.time}
      </span>
      <span>
        <MapPin size={16} />
        {activity.venue}
      </span>
    </div>
  );
}
