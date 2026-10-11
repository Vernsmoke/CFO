import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowUpRight, ShieldCheck, CalendarDays, AlertTriangle } from "lucide-react";
import { requireUser } from "@/lib/auth";
import { readDb } from "@/lib/store";
import {
  canSeeActivity,
  conflicts,
  departments,
  configuredFacilities,
  activeDocuments,
  reportMatches,
  manilaDay,
} from "@/lib/policy";
import {
  ActivityMetadata,
  Empty,
  formatDate,
  formatTime,
  PageHeading,
  Status,
} from "@/components/ui";
import { Field, Select, Textarea, WorkflowForm } from "@/components/forms";
import { SearchList } from "@/components/search-list";
import type { Activity, FacilityRequest, Role } from "@/lib/types";

import { RecordHistory, DocumentReview } from "@/components/workflow-details";
import { SubmissionUpload } from "@/components/submission-upload";
import { FacilityAvailability } from "@/components/facility-availability";
import { AttendancePicker } from "@/components/attendance-picker";
import { ReportFilters } from "@/components/report-filters";
import { OfficeSettings } from "@/components/office-settings";
import { ActivityArtwork } from "@/components/activity-artwork";

const access: Record<string, Role[]> = {
  activities: ["student", "staff"],
  submissions: ["student", "staff"],
  profile: ["student"],
  profiles: ["staff"],
  attendance: ["staff"],
  facilities: ["staff", "representative", "approver"],
  announcements: ["student", "staff"],
  reports: ["staff", "viewer"],
  accounts: ["admin"],
  settings: ["admin"],
  audit: ["admin", "staff"],
  notifications: ["student", "staff", "representative", "approver", "admin", "viewer"],
};
function manilaInput(value: string) {
  return value ? new Date(Date.parse(value) + 8 * 60 * 60 * 1000).toISOString().slice(0, 16) : "";
}
export default async function SectionPage({
  params,
  searchParams,
}: {
  params: Promise<{ section: string }>;
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  const { section } = await params;
  const user = await requireUser();
  if (!access[section]?.includes(user.role)) notFound();
  const db = await readDb();
  const query = await searchParams;
  const filters = Object.fromEntries(
    ["activity", "department", "status", "from", "to", "group"].map((key) => [
      key,
      typeof query[key] === "string" ? query[key] : "",
    ]),
  );
  const facilities = configuredFacilities(db);
  const today = manilaDay();
  const activityFilter = {
    key: "activity",
    label: "Activity",
    options: db.activities.map((a) => ({ value: a.id, label: a.title })),
  };
  const departmentFilter = {
    key: "department",
    label: "Department",
    options: departments.map((name) => ({ value: name, label: name })),
  };
  const staff = user.role === "staff";
  const profile = db.profiles.find((p) => p.userId === user.id);
  const nameFor = (id: string) => db.users.find((u) => u.id === id)?.name ?? "Unknown account";
  const idFor = (id: string) => db.users.find((u) => u.id === id)?.universityId ?? "";
  if (section === "activities") {
    const activities = db.activities.filter((a) =>
      canSeeActivity(user, profile?.department ?? "", a, profile),
    );
    return (
      <>
        <PageHeading
          title="Formation activities"
          description={
            staff
              ? "Create and manage formation activities."
              : "Check requirements before registering."
          }
        />
        {staff && (
          <details className="create-panel">
            <summary>
              Create a formation activity <ArrowUpRight size={18} />
            </summary>
            <WorkflowForm
              intent="createActivity"
              label="Save activity"
              draftKey={`${user.id}:createActivity`}
            >
              <div className="form-grid">
                <Field label="Activity title" name="title" />
                <Select
                  label="Activity type"
                  name="type"
                  options={["Retreat", "Recollection", "Liturgy", "Formation program"]}
                />
                <Field label="Date" name="date" type="date" />
                <Field label="Time (Philippine time)" name="time" type="time" />
                <Field label="Venue" name="venue" />
                <Field label="Registration deadline" name="deadline" type="date" />
                <Select
                  label="Intended participants"
                  name="department"
                  options={["All departments", ...departments]}
                />
                <Field
                  label="Participant capacity"
                  name="capacity"
                  type="number"
                  min={1}
                  max={10000}
                  value={100}
                />
                <Field
                  label="Year levels (comma-separated; blank for all)"
                  name="years"
                  required={false}
                />
                <Field
                  label="Sections (comma-separated; blank for all)"
                  name="sections"
                  required={false}
                />
                <Select label="Publication status" name="status" options={["Draft", "Published"]} />
              </div>
              <Textarea label="Description" name="description" />
              <Textarea
                label="Required document names (one per line; optional)"
                name="requiredDocuments"
                required={false}
              />
              <Textarea
                label="Requirements (or state that none are required)"
                name="requirements"
              />
            </WorkflowForm>
          </details>
        )}
        <SearchList
          placeholder="Search an activity, venue, or program…"
          filters={["Retreat", "Recollection", "Liturgy", "Formation program"]}
          dates
          dimensions={staff ? [departmentFilter] : []}
          items={activities.map((activity) => {
            const registration = db.registrations.find(
              (r) =>
                r.activityId === activity.id && r.userId === user.id && r.status !== "Withdrawn",
            );
            const count = db.registrations.filter(
              (r) => r.activityId === activity.id && r.status !== "Withdrawn",
            ).length;
            return {
              key: activity.id,
              search: `${activity.title} ${activity.venue} ${activity.type}`,
              filter: activity.type,
              date: activity.date,
              values: { department: activity.department },
              content: (
                <article className="activity-detail surface">
                  <ActivityArtwork type={activity.type} detail />
                  <div className="activity-detail-body">
                    <div className="between">
                      <span className="tiny-overline">{activity.type}</span>
                      <Status>{activity.status}</Status>
                    </div>
                    <h2>{activity.title}</h2>
                    <details className="record-description">
                      <summary>Activity details</summary>
                      <p>{activity.description}</p>
                    </details>
                    <ActivityMetadata activity={activity} />
                    <div className="requirement-box">
                      <strong>Prepare for your activity</strong>
                      <p>{activity.requirements}</p>
                      <small>
                        Register by {formatDate(activity.deadline)} · {count} / {activity.capacity}{" "}
                        participants
                      </small>
                    </div>
                    <p className="small muted">
                      {activity.department}
                      {activity.years?.length ? ` · Years ${activity.years.join(", ")}` : ""}
                      {activity.sections?.length
                        ? ` · Sections ${activity.sections.join(", ")}`
                        : ""}
                    </p>
                    {user.role === "student" && (
                      <p className="deadline-indicator">
                        {activity.deadline < today
                          ? "Registration deadline passed"
                          : `Register by ${formatDate(activity.deadline)}`}
                      </p>
                    )}
                    {staff ? (
                      <>
                        <ActivityEditForm activity={activity} userId={user.id} />
                        <div className="inline-actions">
                          <WorkflowForm
                            intent="activityStatus"
                            id={activity.id}
                            label="Update status"
                            secondary
                          >
                            <Select
                              label="Activity status"
                              name="status"
                              value={activity.status}
                              options={["Draft", "Published", "Postponed", "Cancelled", "Archived"]}
                            />
                          </WorkflowForm>
                          <WorkflowForm
                            intent="reminder"
                            id={activity.id}
                            label="Remind incomplete participants"
                            secondary
                          />
                        </div>
                      </>
                    ) : registration ? (
                      <div className="between">
                        <div>
                          <Status>{registration.status}</Status>
                          <p className="small muted">Reference: {registration.id}</p>
                          {registration.status === "Submitted" && (
                            <p className="small muted">
                              Registration submitted. Check your requirements and staff feedback in
                              My submissions.
                            </p>
                          )}
                        </div>
                        <Link
                          href={`/dashboard/submissions?q=${encodeURIComponent(registration.id)}`}
                          className="text-link"
                        >
                          View my submission
                          <ArrowUpRight size={16} />
                        </Link>
                      </div>
                    ) : activity.status === "Published" &&
                      activity.deadline >= today &&
                      count < activity.capacity ? (
                      <WorkflowForm
                        intent="register"
                        id={activity.id}
                        label="Register for this activity"
                      />
                    ) : (
                      <p className="muted small">Registration is unavailable.</p>
                    )}
                  </div>
                </article>
              ),
            };
          })}
        />
      </>
    );
  }
  if (section === "profile")
    return (
      <>
        <PageHeading title="My student profile" description="Submit your details for CFO review." />
        <div className="profile-layout">
          <section className="surface profile-card">
            <span className="large-avatar">
              {user.name
                .split(" ")
                .map((n) => n[0])
                .slice(0, 2)
                .join("")}
            </span>
            <h2>{user.name}</h2>
            <p>{user.universityId}</p>
            <Status>{profile?.status ?? "Not submitted"}</Status>
            <div className="profile-divider" />
            <ShieldCheck size={26} strokeWidth={1.3} />
            <p className="small muted">Official academic records remain with the Registrar.</p>
          </section>
          <section className="surface form-surface">
            <h2>Profile details</h2>
            {profile?.note && <p className="notice-inline">Staff note: {profile.note}</p>}
            <WorkflowForm intent="profile" label="Submit for verification">
              <div className="form-grid">
                <Select
                  label="Department / program"
                  name="department"
                  value={profile?.department}
                  options={departments}
                />
                <Select
                  label="Year level"
                  name="year"
                  value={profile?.year}
                  options={["1", "2", "3", "4", "5"]}
                />
                <Field label="Section" name="section" value={profile?.section} />
                <Field label="Contact email" name="contact" type="email" value={profile?.contact} />
              </div>
              <p className="small muted">Changes require staff verification.</p>
            </WorkflowForm>
          </section>
        </div>
      </>
    );
  if (section === "profiles")
    return (
      <>
        <PageHeading
          title="Student profiles"
          description="Verify details or request corrections."
        />
        <SearchList
          placeholder="Search student name, ID Number, or department…"
          filters={["Pending verification", "Verified", "Correction requested"]}
          dimensions={[departmentFilter]}
          items={db.profiles.map((p) => ({
            key: p.userId,
            search: `${nameFor(p.userId)} ${idFor(p.userId)} ${p.department}`,
            filter: p.status,
            values: { department: p.department },
            content: (
              <article className="surface record-card">
                <div className="record-top">
                  <div>
                    <h2>{nameFor(p.userId)}</h2>
                    <p>
                      {idFor(p.userId)} · {p.department}
                    </p>
                  </div>
                  <Status>{p.status}</Status>
                </div>
                <div className="record-facts">
                  <span>
                    Year {p.year} · Section {p.section}
                  </span>
                  <span>{p.contact}</span>
                </div>
                {p.note && <p className="notice-inline">{p.note}</p>}
                {p.status === "Pending verification" && (
                  <WorkflowForm intent="verifyProfile" id={p.userId} label="Save review">
                    <div className="form-grid">
                      <Select
                        label="Decision"
                        name="status"
                        options={["Verified", "Correction requested"]}
                      />
                      <Field
                        label="Review note (required for corrections)"
                        name="note"
                        required={false}
                      />
                    </div>
                  </WorkflowForm>
                )}
              </article>
            ),
          }))}
        />
      </>
    );
  if (section === "submissions") {
    const registrations = db.registrations.filter((r) => staff || r.userId === user.id);
    return (
      <>
        <PageHeading
          title={staff ? "Submission review" : "My submissions"}
          description={
            staff
              ? "Review documents and provide feedback."
              : "Upload requirements and check staff feedback."
          }
        />
        <p className="boundary-note">
          <ShieldCheck size={17} />
          CFO acceptance covers requirements only. Payment confirmation remains with the cashier.
        </p>
        <SearchList
          filters={["Submitted", "Under review", "Requires correction", "Accepted", "Withdrawn"]}
          dimensions={staff ? [activityFilter, departmentFilter] : []}
          dates
          placeholder={
            staff ? "Search student, reference, or activity…" : "Search reference or activity…"
          }
          items={registrations.map((r) => {
            const activity = db.activities.find((a) => a.id === r.activityId);
            const documents = db.documents.filter((d) => d.registrationId === r.id);
            return {
              key: r.id,
              search: `${r.id} ${activity?.title} ${staff ? nameFor(r.userId) : ""}`,
              filter: r.status,
              date: manilaDay(r.submittedAt),
              values: {
                activity: r.activityId,
                department: db.profiles.find((p) => p.userId === r.userId)?.department ?? "",
              },
              content: (
                <article className="surface record-card">
                  <div className="record-top">
                    <div>
                      <span className="tiny-overline">{r.id}</span>
                      <h2>{activity?.title}</h2>
                      <p>
                        {staff ? `${nameFor(r.userId)} · ${idFor(r.userId)} · ` : ""}Submitted{" "}
                        {formatDate(r.submittedAt)}
                      </p>
                    </div>
                    <Status>{r.status}</Status>
                  </div>
                  <div className="requirement-box">
                    <strong>Required for this activity</strong>
                    <p>{activity?.requirements}</p>
                  </div>
                  {r.note && (
                    <p className="notice-inline">
                      <strong>Staff feedback:</strong> {r.note}
                    </p>
                  )}
                  <div className="document-list">
                    <h3>
                      Supporting documents <span>{documents.length}</span>
                    </h3>
                    {activeDocuments(documents, r.id).map((d) => (
                      <DocumentReview
                        key={d.id}
                        document={d}
                        staff={staff}
                        editable={r.status !== "Withdrawn"}
                      />
                    ))}
                    {documents.some((d) => d.supersededBy) && (
                      <details className="record-history">
                        <summary>Previous versions</summary>
                        {documents
                          .filter((d) => d.supersededBy)
                          .map((d) => (
                            <DocumentReview
                              key={d.id}
                              document={d}
                              staff={false}
                              editable={false}
                            />
                          ))}
                      </details>
                    )}
                    {!documents.length && (
                      <p className="small muted">No supporting documents uploaded.</p>
                    )}
                  </div>
                  {staff && r.status !== "Withdrawn" ? (
                    <WorkflowForm intent="review" id={r.id} label="Save review">
                      <div className="form-grid">
                        <Select
                          label="Review status"
                          name="status"
                          value={r.status === "Accepted" ? "Accepted" : "Under review"}
                          options={["Under review", "Requires correction", "Accepted"]}
                        />
                        <Field
                          label="Feedback (required for corrections)"
                          name="note"
                          required={false}
                          value={r.note}
                        />
                      </div>
                    </WorkflowForm>
                  ) : !staff && !["Accepted", "Withdrawn"].includes(r.status) ? (
                    <>
                      <SubmissionUpload
                        registrationId={r.id}
                        requirements={activity?.requiredDocuments ?? []}
                        documents={activeDocuments(documents, r.id).map((d) => ({
                          id: d.id,
                          filename: d.filename,
                          requirement: d.requirement,
                        }))}
                      />
                      <details className="withdraw-details">
                        <summary>Withdraw this registration</summary>
                        <p className="small muted">
                          This removes your active registration. Contact the CFO if you need
                          guidance.
                        </p>
                        <WorkflowForm
                          intent="withdraw"
                          id={r.id}
                          label="Confirm withdrawal"
                          secondary
                        />
                      </details>
                    </>
                  ) : null}
                  <RecordHistory
                    events={db.audit.filter((event) => event.target === r.id)}
                    staff={staff}
                    users={staff ? db.users : []}
                  />
                </article>
              ),
            };
          })}
        />
      </>
    );
  }
  if (section === "attendance") {
    const selectedActivity =
      typeof query.activity === "string"
        ? query.activity
        : (db.activities.find((a) => a.status === "Published")?.id ?? "All");
    const registrations = db.registrations.filter(
      (r) =>
        r.status !== "Withdrawn" &&
        (selectedActivity === "All" || r.activityId === selectedActivity),
    );
    return (
      <>
        <PageHeading
          title="Staff-recorded attendance"
          description="Record participants from the list. Students do not need their phones."
        />
        <p className="boundary-note">
          <CalendarDays size={17} />
          Time-in and time-out record checkpoints, not continuous participation. All times are
          Philippine time.
        </p>
        <AttendancePicker
          activities={db.activities.map((a) => ({ id: a.id, title: a.title }))}
          selected={selectedActivity}
        />
        <SearchList
          placeholder="Search participant, University ID, or activity…"
          filters={["Not recorded", "Time-in recorded", "Time-out recorded"]}
          dimensions={[
            departmentFilter,
            {
              key: "group",
              label: "Participant group",
              options: [
                ...new Set(
                  registrations
                    .map((r) => r.group)
                    .filter((value): value is string => Boolean(value)),
                ),
              ].map((value) => ({ value, label: value })),
            },
          ]}
          items={registrations.map((r) => {
            const a = db.activities.find((a) => a.id === r.activityId);
            const entry = db.attendance.find((e) => e.registrationId === r.id);
            return {
              key: r.id,
              search: `${nameFor(r.userId)} ${idFor(r.userId)} ${a?.title} ${r.group ?? ""} ${r.facilitator ?? ""}`,
              filter: entry?.timeOut
                ? "Time-out recorded"
                : entry?.timeIn
                  ? "Time-in recorded"
                  : "Not recorded",
              values: {
                department: db.profiles.find((p) => p.userId === r.userId)?.department ?? "",
                group: r.group ?? "",
              },
              content: (
                <article className="surface record-card">
                  <div className="record-top">
                    <div>
                      <h2>{nameFor(r.userId)}</h2>
                      <p>
                        {idFor(r.userId)} · {a?.title}
                      </p>
                    </div>
                    {entry?.timeIn && !entry.timeOut && <Status>Missing time-out</Status>}
                  </div>
                  <div className="attendance-times">
                    <div>
                      <span>Time-in</span>
                      <strong>{formatTime(entry?.timeIn ?? "")}</strong>
                      {entry?.timeIn && <small>{formatDate(entry.timeIn, true)}</small>}
                    </div>
                    <div>
                      <span>Time-out</span>
                      <strong>{formatTime(entry?.timeOut ?? "")}</strong>
                      {entry?.timeOut && <small>{formatDate(entry.timeOut, true)}</small>}
                    </div>
                  </div>
                  <div className="inline-actions">
                    {!entry?.timeIn && (
                      <WorkflowForm intent="attendance" id={r.id} label="Record time-in">
                        <input type="hidden" name="entry" value="timeIn" />
                      </WorkflowForm>
                    )}
                    {entry?.timeIn && !entry.timeOut && (
                      <WorkflowForm intent="attendance" id={r.id} label="Record time-out">
                        <input type="hidden" name="entry" value="timeOut" />
                      </WorkflowForm>
                    )}
                  </div>
                  <p className="attendance-outcome">
                    {entry?.timeOut
                      ? "Time-in and time-out recorded"
                      : entry?.timeIn
                        ? "Time-in recorded"
                        : "Attendance not yet recorded"}
                  </p>
                  <details className="correction-details">
                    <summary>Participant group</summary>
                    <WorkflowForm intent="assignGroup" id={r.id} label="Save participant group">
                      <Field label="Group" name="group" required={false} value={r.group} />
                      <Field
                        label="Facilitator"
                        name="facilitator"
                        required={false}
                        value={r.facilitator}
                      />
                    </WorkflowForm>
                  </details>
                  <RecordHistory
                    events={db.audit.filter(
                      (event) =>
                        event.target === r.id &&
                        [
                          "Attendance recorded",
                          "Attendance corrected",
                          "Participant group changed",
                        ].includes(event.action),
                    )}
                    staff
                    users={db.users}
                  />
                  {entry && (
                    <details className="correction-details">
                      <summary>Correct attendance with a reason</summary>
                      <WorkflowForm
                        intent="correctAttendance"
                        id={r.id}
                        label="Save audited correction"
                      >
                        <div className="form-grid">
                          <Field
                            label="Time-in (Philippine time)"
                            name="timeIn"
                            type="datetime-local"
                            required={false}
                            value={manilaInput(entry.timeIn)}
                          />
                          <Field
                            label="Time-out (Philippine time)"
                            name="timeOut"
                            type="datetime-local"
                            required={false}
                            value={manilaInput(entry.timeOut)}
                          />
                        </div>
                        <Textarea label="Reason for correction" name="reason" />
                      </WorkflowForm>
                    </details>
                  )}
                </article>
              ),
            };
          })}
        />
      </>
    );
  }
  if (section === "facilities") {
    const requests = db.facilities.filter(
      (r) =>
        user.role === "approver" ||
        (user.role === "representative" && r.userId === user.id) ||
        (staff && r.status === "Approved"),
    );
    return (
      <>
        <PageHeading
          title="Facility requests"
          description={
            user.role === "approver"
              ? "Review requests and resolve scheduling conflicts before approval."
              : user.role === "representative"
                ? "Request a space and follow your department’s approval status."
                : "View approved facility bookings for office coordination."
          }
        />
        <FacilityAvailability
          facilities={[...new Set([...facilities, ...db.facilities.map((r) => r.facility)])]}
          bookings={db.facilities
            .filter(
              (r) => r.status === "Approved" || (r.status === "Pending" && user.role !== "staff"),
            )
            .map(({ facility, start, end, status }) => ({ facility, start, end, status }))}
        />
        {user.role === "representative" && (
          <details className="create-panel">
            <summary>
              Request a facility <ArrowUpRight size={18} />
            </summary>
            <WorkflowForm
              intent="requestFacility"
              label="Submit facility request"
              draftKey={`${user.id}:requestFacility`}
            >
              <div className="form-grid">
                <Select label="Facility" name="facility" options={facilities} />
                <Field
                  label="Expected participants"
                  name="participants"
                  type="number"
                  min={1}
                  value={40}
                />
                <Field label="Start (Philippine time)" name="start" type="datetime-local" />
                <Field label="End (Philippine time)" name="end" type="datetime-local" />
              </div>
              <Textarea label="Event purpose" name="purpose" />
            </WorkflowForm>
          </details>
        )}
        <SearchList
          filters={["Pending", "Approved", "Rejected", "Returned for correction"]}
          placeholder="Search facility or event purpose…"
          dates
          dimensions={[
            {
              key: "facility",
              label: "Facility",
              options: [...new Set(requests.map((r) => r.facility))].map((value) => ({
                value,
                label: value,
              })),
            },
          ]}
          items={requests.map((r) => {
            const collision = user.role === "approver" ? conflicts(r, db.facilities) : [];
            return {
              key: r.id,
              search: `${r.facility} ${r.purpose}`,
              date: manilaDay(r.start),
              values: { facility: r.facility },
              filter: r.status,
              content: (
                <article className="surface record-card">
                  <div className="record-top">
                    <div>
                      <span className="tiny-overline">{r.facility}</span>
                      <h2>{r.purpose}</h2>
                      <p>
                        {nameFor(r.userId)} · {r.participants} participants
                      </p>
                    </div>
                    <Status>{r.status}</Status>
                  </div>
                  <div className="activity-meta">
                    <span>
                      <CalendarDays size={16} />
                      {formatDate(r.start)} · {formatTime(r.start)} – {formatTime(r.end)}
                      {formatDate(r.start) !== formatDate(r.end) ? ` (${formatDate(r.end)})` : ""}
                    </span>
                  </div>
                  {r.note && <p className="notice-inline">Decision note: {r.note}</p>}
                  {collision.length > 0 && (
                    <div className="conflict-note">
                      <AlertTriangle size={18} />
                      <div>
                        <strong>
                          {collision.length} possible scheduling conflict
                          {collision.length > 1 ? "s" : ""}
                        </strong>
                        {collision.map((c) => (
                          <p key={c.id}>
                            {c.purpose} · {c.status} · {formatTime(c.start)}–{formatTime(c.end)}
                          </p>
                        ))}
                      </div>
                    </div>
                  )}
                  {user.role === "representative" && r.status === "Returned for correction" && (
                    <FacilityCorrection request={r} facilities={facilities} userId={user.id} />
                  )}
                  {user.role === "approver" &&
                    ["Pending", "Returned for correction"].includes(r.status) && (
                      <WorkflowForm intent="decideFacility" id={r.id} label="Save decision">
                        <div className="form-grid">
                          <Select
                            label="Decision"
                            name="status"
                            options={["Approved", "Rejected", "Returned for correction"]}
                          />
                          <Field
                            label="Decision note (required when returning or rejecting)"
                            name="note"
                            required={false}
                          />
                        </div>
                        <p className="small muted">
                          Approved overlaps are blocked. Pending conflicts require a
                          university-authorized decision; submission order does not grant priority.
                        </p>
                      </WorkflowForm>
                    )}
                  <RecordHistory
                    events={db.audit.filter((event) => event.target === r.id)}
                    staff={user.role === "approver"}
                    users={user.role === "approver" ? db.users : []}
                  />
                </article>
              ),
            };
          })}
        />
      </>
    );
  }
  if (section === "announcements") {
    const announcements = db.announcements.filter(
      (a) => staff || a.department === "All departments" || a.department === profile?.department,
    );
    return (
      <>
        <PageHeading title="Office announcements" />
        {staff && (
          <details className="create-panel">
            <summary>
              Publish an announcement
              <ArrowUpRight size={18} />
            </summary>
            <WorkflowForm
              intent="announcement"
              label="Publish announcement"
              draftKey={`${user.id}:announcement`}
            >
              <Field label="Title" name="title" />
              <Select
                label="Audience"
                name="department"
                options={["All departments", ...departments]}
              />
              <Textarea label="Announcement" name="body" />
            </WorkflowForm>
          </details>
        )}
        <SearchList
          placeholder="Search announcements…"
          items={announcements.map((a) => ({
            key: a.id,
            search: `${a.title} ${a.body}`,
            content: (
              <article className="surface announcement-full">
                <div className="between">
                  <span className="tiny-overline">CFO UPDATE</span>
                  <span className="small muted">{formatDate(a.createdAt)}</span>
                </div>
                <h2>{a.title}</h2>
                <p>{a.body}</p>
                <small>{a.department}</small>
              </article>
            ),
          }))}
        />
      </>
    );
  }
  if (section === "notifications") {
    const notifications = db.notifications.filter((n) => n.userId === user.id);
    return (
      <>
        <PageHeading
          title="Your updates"
          action={<WorkflowForm intent="readNotifications" label="Mark all as read" secondary />}
        />
        <div className="surface">
          {notifications.length ? (
            notifications.map((n) => (
              <article className={`notification-row ${!n.read ? "unread" : ""}`} key={n.id}>
                <span className="notification-dot" />
                <div>
                  <p>{n.message}</p>
                  <small>
                    {formatDate(n.createdAt)} · {formatTime(n.createdAt)}
                  </small>
                </div>
                <span className="small muted">{n.read ? "Read" : "Unread"}</span>
              </article>
            ))
          ) : (
            <Empty
              title="No updates just yet"
              description="Your activity and request updates will appear here."
            />
          )}
        </div>
      </>
    );
  }
  if (section === "reports") {
    const active = db.registrations.filter((r) => reportMatches(r, db, filters));
    return (
      <>
        <PageHeading
          title="Formation reports"
          description={
            staff
              ? "Understand participation, requirements, and attendance with authorized records."
              : "Read-only aggregate formation summaries. Individual student records are restricted."
          }
        />
        <ReportFilters
          key={JSON.stringify(filters)}
          activities={db.activities.map((a) => ({ id: a.id, title: a.title }))}
          departments={departments}
          groups={
            staff
              ? [
                  ...new Set(
                    db.registrations
                      .map((r) => r.group)
                      .filter((value): value is string => Boolean(value)),
                  ),
                ]
              : []
          }
          values={filters}
          exportAllowed={staff}
        />
        <div className="report-metrics">
          {[
            ["Active registrations", active.length],
            ["Requirements accepted", active.filter((r) => r.status === "Accepted").length],
            [
              "Awaiting corrections",
              active.filter((r) => r.status === "Requires correction").length,
            ],
            [
              "Attendance checkpoints",
              db.attendance.filter(
                (a) => a.timeIn && a.timeOut && active.some((r) => r.id === a.registrationId),
              ).length,
            ],
          ].map(([label, count]) => (
            <div className="surface" key={label}>
              <span>{label}</span>
              <strong>{count}</strong>
            </div>
          ))}
        </div>
        <section className="surface report-table">
          <h2>Activity participation</h2>
          <div className="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>Activity</th>
                  <th>Registrations</th>
                  <th>Accepted</th>
                  <th>Time-in</th>
                  <th>Time-out</th>
                </tr>
              </thead>
              <tbody>
                {db.activities
                  .filter(
                    (a) =>
                      (!filters.activity || a.id === filters.activity) &&
                      (!filters.from || a.date >= filters.from) &&
                      (!filters.to || a.date <= filters.to) &&
                      ((!filters.department && !filters.status && !filters.group) ||
                        active.some((r) => r.activityId === a.id)),
                  )
                  .map((a) => {
                    const rs = active.filter((r) => r.activityId === a.id);
                    return (
                      <tr key={a.id}>
                        <td>
                          {a.title}
                          <small>{formatDate(a.date, true)}</small>
                        </td>
                        <td>{rs.length}</td>
                        <td>{rs.filter((r) => r.status === "Accepted").length}</td>
                        <td>
                          {
                            db.attendance.filter(
                              (e) => rs.some((r) => r.id === e.registrationId) && e.timeIn,
                            ).length
                          }
                        </td>
                        <td>
                          {
                            db.attendance.filter(
                              (e) => rs.some((r) => r.id === e.registrationId) && e.timeOut,
                            ).length
                          }
                        </td>
                      </tr>
                    );
                  })}
              </tbody>
            </table>
          </div>
        </section>
        <p className="boundary-note">
          <ShieldCheck size={17} />
          Attendance checkpoints do not determine completion. Financial transactions are not
          verified by these reports.
        </p>
      </>
    );
  }
  if (section === "settings")
    return (
      <>
        <PageHeading
          title="Office settings"
          description="Use university-approved branding and facilities."
        />
        <OfficeSettings db={db} />
      </>
    );
  if (section === "accounts")
    return (
      <>
        <PageHeading
          title="Accounts & permissions"
          description="Manage existing pilot accounts. New university account provisioning requires an approved process."
        />
        <SearchList
          placeholder="Search account name, ID Number, or role…"
          items={db.users.map((u) => ({
            key: u.id,
            search: `${u.name} ${u.universityId} ${u.role}`,
            content: (
              <article className="surface record-card">
                <div className="record-top">
                  <div>
                    <h2>{u.name}</h2>
                    <p>
                      {u.universityId} · {u.role}
                    </p>
                  </div>
                  <Status>{u.active ? "Active" : "Deactivated"}</Status>
                </div>
                {u.id !== user.id ? (
                  <WorkflowForm intent="account" id={u.id} label="Update permissions">
                    <div className="form-grid">
                      <Select
                        label="Role"
                        name="role"
                        value={u.role}
                        options={[
                          "student",
                          "staff",
                          "approver",
                          "representative",
                          "admin",
                          "viewer",
                        ]}
                      />
                      <Select
                        label="Active account"
                        name="active"
                        value={String(u.active)}
                        options={["true", "false"]}
                      />
                    </div>
                    <p className="small muted">
                      Changing access revokes this account’s existing sessions and creates an audit
                      entry.
                    </p>
                  </WorkflowForm>
                ) : (
                  <p className="muted small">Your own permissions cannot be changed here.</p>
                )}
              </article>
            ),
          }))}
        />
      </>
    );
  if (section === "audit")
    return (
      <>
        <PageHeading title="Audit history" />
        <SearchList
          placeholder="Search action, target, or actor…"
          dates
          dimensions={[
            {
              key: "action",
              label: "Action",
              options: [...new Set(db.audit.map((event) => event.action))].map((value) => ({
                value,
                label: value,
              })),
            },
          ]}
          items={db.audit.map((event) => ({
            key: event.id,
            date: manilaDay(event.at),
            values: { action: event.action },
            search: `${event.action} ${event.target} ${nameFor(event.actorId)}`,
            content: (
              <article className="surface audit-row">
                <span className="soft-icon">
                  <ShieldCheck size={19} />
                </span>
                <div>
                  <h3>{event.action}</h3>
                  <p>
                    {nameFor(event.actorId)} · {event.target}
                  </p>
                  {event.detail && <pre>{event.detail}</pre>}
                </div>
                <time>
                  {formatDate(event.at, true)}
                  <br />
                  {formatTime(event.at)}
                </time>
              </article>
            ),
          }))}
        />
      </>
    );
  notFound();
}

function ActivityEditForm({ activity: a, userId }: { activity: Activity; userId: string }) {
  return (
    <details className="correction-details">
      <summary>Edit activity details</summary>
      <WorkflowForm
        intent="editActivity"
        id={a.id}
        label="Save details and notify participants"
        draftKey={`${userId}:activity:${a.id}`}
      >
        <input type="hidden" name="status" value="Draft" />
        <div className="form-grid">
          <Field label="Activity title" name="title" value={a.title} />
          <Select
            label="Activity type"
            name="type"
            value={a.type}
            options={["Retreat", "Recollection", "Liturgy", "Formation program"]}
          />
          <Field label="Date" name="date" type="date" value={a.date} />
          <Field label="Time (Philippine time)" name="time" type="time" value={a.time} />
          <Field label="Venue" name="venue" value={a.venue} />
          <Field label="Registration deadline" name="deadline" type="date" value={a.deadline} />
          <Select
            label="Intended participants"
            name="department"
            value={a.department}
            options={["All departments", ...departments]}
          />
          <Field
            label="Participant capacity"
            name="capacity"
            type="number"
            min={1}
            max={10000}
            value={a.capacity}
          />
        </div>
        <Field
          label="Year levels (comma-separated; blank for all)"
          name="years"
          value={a.years?.join(", ")}
          required={false}
        />
        <Field
          label="Sections (comma-separated; blank for all)"
          name="sections"
          value={a.sections?.join(", ")}
          required={false}
        />
        <Textarea
          label="Required document names (one per line; optional)"
          name="requiredDocuments"
          value={a.requiredDocuments?.join("\n")}
          required={false}
        />
        <Textarea label="Description" name="description" value={a.description} />
        <Textarea label="Requirements" name="requirements" value={a.requirements} />
      </WorkflowForm>
    </details>
  );
}
function FacilityCorrection({
  request: r,
  facilities,
  userId,
}: {
  request: FacilityRequest;
  facilities: string[];
  userId: string;
}) {
  return (
    <details className="correction-details">
      <summary>Correct and resubmit request</summary>
      <WorkflowForm
        intent="requestFacility"
        id={r.id}
        label="Resubmit request"
        draftKey={`${userId}:facility:${r.id}`}
      >
        <div className="form-grid">
          <Select
            label="Facility"
            name="facility"
            value={r.facility}
            options={facilities.includes(r.facility) ? facilities : [r.facility, ...facilities]}
          />
          <Field
            label="Expected participants"
            name="participants"
            type="number"
            min={1}
            value={r.participants}
          />
          <Field
            label="Start (Philippine time)"
            name="start"
            type="datetime-local"
            value={manilaInput(r.start)}
          />
          <Field
            label="End (Philippine time)"
            name="end"
            type="datetime-local"
            value={manilaInput(r.end)}
          />
        </div>
        <Textarea label="Event purpose" name="purpose" value={r.purpose} />
      </WorkflowForm>
    </details>
  );
}
