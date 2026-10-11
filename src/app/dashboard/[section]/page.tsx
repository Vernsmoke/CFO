import Link from "next/link";
import { notFound } from "next/navigation";
import {
  ArrowUpRight,
  Download,
  FileText,
  ShieldCheck,
  CalendarDays,
  AlertTriangle,
} from "lucide-react";
import { requireUser } from "@/lib/auth";
import { readDb } from "@/lib/store";
import { canSeeActivity, conflicts, departments, facilityNames } from "@/lib/policy";
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
  audit: ["admin", "staff"],
  notifications: ["student", "staff", "representative", "approver", "admin", "viewer"],
};
function manilaInput(value: string) {
  return value ? new Date(Date.parse(value) + 8 * 60 * 60 * 1000).toISOString().slice(0, 16) : "";
}
export default async function SectionPage({ params }: { params: Promise<{ section: string }> }) {
  const { section } = await params;
  const user = await requireUser();
  if (!access[section]?.includes(user.role)) notFound();
  const db = await readDb();
  const staff = user.role === "staff";
  const profile = db.profiles.find((p) => p.userId === user.id);
  const nameFor = (id: string) => db.users.find((u) => u.id === id)?.name ?? "Unknown account";
  const idFor = (id: string) => db.users.find((u) => u.id === id)?.universityId ?? "";
  if (section === "activities") {
    const activities = db.activities.filter((a) =>
      canSeeActivity(user, profile?.department ?? "", a),
    );
    return (
      <>
        <PageHeading
          eyebrow="FAITH IN MOTION"
          title="Formation activities"
          description={
            staff
              ? "Create meaningful experiences and keep every detail together."
              : "Find your next moment of reflection, connection, and growth."
          }
        />
        {staff && (
          <details className="create-panel">
            <summary>
              Create a formation activity <ArrowUpRight size={18} />
            </summary>
            <WorkflowForm intent="createActivity" label="Save activity">
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
                <Select label="Publication status" name="status" options={["Draft", "Published"]} />
              </div>
              <Textarea label="Description" name="description" />
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
          items={activities.map((activity, index) => {
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
              content: (
                <article className="activity-detail surface">
                  <div className={`detail-art tile-${index % 3}`}>
                    <div className="art-arch" />
                    <div className="art-circle" />
                    <span>{activity.type}</span>
                  </div>
                  <div className="activity-detail-body">
                    <div className="between">
                      <span className="tiny-overline">{activity.type}</span>
                      <Status>{activity.status}</Status>
                    </div>
                    <h2>{activity.title}</h2>
                    <p>{activity.description}</p>
                    <ActivityMetadata activity={activity} />
                    <div className="requirement-box">
                      <strong>Prepare for your activity</strong>
                      <p>{activity.requirements}</p>
                      <small>
                        Register by {formatDate(activity.deadline)} · {count} / {activity.capacity}{" "}
                        participants
                      </small>
                    </div>
                    <p className="small muted">{activity.department}</p>
                    {staff ? (
                      <>
                        <ActivityEditForm activity={activity} />
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
                        <Status>{registration.status}</Status>
                        <Link href="/dashboard/submissions" className="text-link">
                          View my submission
                          <ArrowUpRight size={16} />
                        </Link>
                      </div>
                    ) : activity.status === "Published" ? (
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
        <PageHeading
          eyebrow="YOUR PLACE IN THE COMMUNITY"
          title="My student profile"
          description="Keep your formation profile current. Authorized staff review the details you submit."
        />
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
            <p className="small muted">
              This profile supports CFO activities. Official academic records remain under the
              Registrar’s authority.
            </p>
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
              <p className="small muted">
                Submitting changes returns the profile to pending verification. Verification follows
                the university’s approved procedure.
              </p>
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
          description="Review student-submitted details through the authorized university process."
        />
        <SearchList
          placeholder="Search student name, ID Number, or department…"
          filters={["Pending verification", "Verified", "Correction requested"]}
          items={db.profiles.map((p) => ({
            key: p.userId,
            search: `${nameFor(p.userId)} ${idFor(p.userId)} ${p.department}`,
            filter: p.status,
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
          eyebrow={staff ? "CLEAR FEEDBACK, BETTER PREPARATION" : "EVERYTHING IN ONE PLACE"}
          title={staff ? "Submission review" : "My submissions"}
          description={
            staff
              ? "Review documents for CFO requirements and give students actionable feedback."
              : "Follow your registrations, upload requirements, and respond to staff feedback."
          }
        />
        <p className="boundary-note">
          <ShieldCheck size={17} />
          Accepted means CFO requirements are met. Receipt images are supporting documents;
          financial confirmation remains with the cashier.
        </p>
        <SearchList
          filters={["Submitted", "Under review", "Requires correction", "Accepted", "Withdrawn"]}
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
                    {documents.map((d) => (
                      <a
                        href={`/api/documents/${d.id}`}
                        target="_blank"
                        rel="noreferrer"
                        key={d.id}
                      >
                        <FileText size={18} />
                        <span>
                          {d.filename}
                          <small>{formatDate(d.uploadedAt, true)}</small>
                        </span>
                        <Download size={16} />
                      </a>
                    ))}
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
                      <WorkflowForm intent="upload" id={r.id} label="Upload and submit for review">
                        <label className="upload-field">
                          <FileText size={25} />
                          <span>Choose a supporting document</span>
                          <small>PDF, PNG, or JPEG · Maximum 5 MB</small>
                          <input
                            name="file"
                            type="file"
                            accept="application/pdf,image/png,image/jpeg"
                            required
                          />
                        </label>
                      </WorkflowForm>
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
                </article>
              ),
            };
          })}
        />
      </>
    );
  }
  if (section === "attendance") {
    const registrations = db.registrations.filter((r) => r.status !== "Withdrawn");
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
        <SearchList
          placeholder="Search participant, University ID, or activity…"
          items={registrations.map((r) => {
            const a = db.activities.find((a) => a.id === r.activityId);
            const entry = db.attendance.find((e) => e.registrationId === r.id);
            return {
              key: r.id,
              search: `${nameFor(r.userId)} ${idFor(r.userId)} ${a?.title}`,
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
          eyebrow="MAKE SPACE FOR COMMUNITY"
          title="Facility requests"
          description={
            user.role === "approver"
              ? "Review requests and resolve scheduling conflicts before approval."
              : user.role === "representative"
                ? "Request a space and follow your department’s approval status."
                : "View approved facility bookings for office coordination."
          }
        />
        {user.role === "representative" && (
          <details className="create-panel">
            <summary>
              Request a facility <ArrowUpRight size={18} />
            </summary>
            <WorkflowForm intent="requestFacility" label="Submit facility request">
              <div className="form-grid">
                <Select label="Facility" name="facility" options={facilityNames} />
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
          items={requests.map((r) => {
            const collision = user.role === "approver" ? conflicts(r, db.facilities) : [];
            return {
              key: r.id,
              search: `${r.facility} ${r.purpose}`,
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
                    <FacilityCorrection request={r} />
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
        <PageHeading
          eyebrow="STAY IN THE LOOP"
          title="Office announcements"
          description="The latest formation updates, thoughtful reminders, and important details."
        />
        {staff && (
          <details className="create-panel">
            <summary>
              Publish an announcement
              <ArrowUpRight size={18} />
            </summary>
            <WorkflowForm intent="announcement" label="Publish announcement">
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
          description="In-system notifications keep your next steps close at hand."
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
        <p className="small muted">
          These are in-system records. No email or SMS delivery is configured.
        </p>
      </>
    );
  }
  if (section === "reports") {
    const active = db.registrations.filter((r) => r.status !== "Withdrawn");
    return (
      <>
        <PageHeading
          eyebrow="A CLEARER VIEW"
          title="Formation reports"
          description={
            staff
              ? "Understand participation, requirements, and attendance with authorized records."
              : "Read-only aggregate formation summaries. Individual student records are restricted."
          }
          action={
            staff ? (
              <a href="/api/reports" className="button">
                <Download size={16} />
                Export participant CSV
              </a>
            ) : undefined
          }
        />
        <div className="report-metrics">
          {[
            ["Active registrations", active.length],
            ["Requirements accepted", active.filter((r) => r.status === "Accepted").length],
            [
              "Awaiting corrections",
              active.filter((r) => r.status === "Requires correction").length,
            ],
            ["Attendance checkpoints", db.attendance.filter((a) => a.timeIn && a.timeOut).length],
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
                {db.activities.map((a) => {
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
        <PageHeading
          title="Audit history"
          description="Important actions, their authors, and recorded changes in one place."
        />
        <SearchList
          placeholder="Search action, target, or actor…"
          items={db.audit.map((event) => ({
            key: event.id,
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

function ActivityEditForm({ activity: a }: { activity: Activity }) {
  return (
    <details className="correction-details">
      <summary>Edit activity details</summary>
      <WorkflowForm intent="editActivity" id={a.id} label="Save details and notify participants">
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
        <Textarea label="Description" name="description" value={a.description} />
        <Textarea label="Requirements" name="requirements" value={a.requirements} />
      </WorkflowForm>
    </details>
  );
}
function FacilityCorrection({ request: r }: { request: FacilityRequest }) {
  return (
    <details className="correction-details">
      <summary>Correct and resubmit request</summary>
      <WorkflowForm intent="requestFacility" id={r.id} label="Resubmit request">
        <div className="form-grid">
          <Select label="Facility" name="facility" value={r.facility} options={facilityNames} />
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
