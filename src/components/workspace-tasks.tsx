import Link from "next/link";
import type { Database, User } from "@/lib/types";

export function WorkspaceTasks({ user, db }: { user: User; db: Database }) {
  const profile = db.profiles.find((item) => item.userId === user.id);
  const mine = db.registrations.filter((item) => item.userId === user.id);
  const corrections = mine.filter((item) => item.status === "Requires correction");
  const tasks: { title: string; detail: string; href: string; action: string }[] = [];
  const add = (title: string, detail: string, href: string, action: string) =>
    tasks.push({ title, detail, href: `/dashboard${href}`, action });
  let title = "Your next steps";
  if (user.role === "student") {
    if (!profile || ["Not submitted", "Correction requested"].includes(profile.status)) {
      add(
        "Complete your student profile",
        profile?.note || "Check your details and send them to CFO for verification.",
        "/profile",
        "Update profile",
      );
    }
    for (const record of corrections)
      add(
        db.activities.find((item) => item.id === record.activityId)?.title ??
          "Correct your submission",
        record.note || "Read the staff feedback and replace the requested document.",
        `/submissions?record=${encodeURIComponent(record.id)}`,
        "Fix submission",
      );
    if (!corrections.length)
      add(
        "Check your activity requirements",
        "Open your registration to see documents, review progress, and staff feedback.",
        "/submissions",
        "My submissions",
      );
    add(
      "Find your next activity",
      "Browse dates, venues, and requirements before registering.",
      "/activities",
      "Browse activities",
    );
  } else if (user.role === "staff") {
    title = "Your work queue";
    const pending = db.registrations.filter((item) => item.status === "Submitted").length;
    const reviewing = db.registrations.filter((item) => item.status === "Under review").length;
    if (reviewing)
      add(
        `${reviewing} submissions under review`,
        "Continue reviews already in progress.",
        "/submissions?filter=Under+review",
        "Continue reviews",
      );
    const profiles = db.profiles.filter((item) => item.status === "Pending verification").length;
    add(
      `${pending} new submissions`,
      "Open a submission, review its documents, and save your decision.",
      "/submissions?filter=Submitted",
      "Review submissions",
    );
    add(
      `${profiles} profiles to verify`,
      "Check student details and return clear feedback when corrections are needed.",
      "/profiles?filter=Pending+verification",
      "Verify profiles",
    );
    add(
      "Record attendance",
      "Choose an activity and record participation. Students do not need a phone.",
      "/attendance",
      "Open attendance",
    );
    add(
      "Manage activities",
      "Create an activity or update an existing schedule.",
      "/activities?create=true",
      "Create activity",
    );
  } else if (user.role === "representative") {
    title = "Your facility bookings";
    for (const request of db.facilities.filter(
      (item) => item.userId === user.id && item.status === "Returned for correction",
    ))
      add(
        request.purpose,
        request.note || "Update your request using the approver’s feedback.",
        `/facilities?record=${encodeURIComponent(request.id)}`,
        "Correct request",
      );
    add(
      "Book a university facility",
      "Check availability, then enter the event details and schedule.",
      "/facilities?create=true",
      "Request a facility",
    );
    add(
      "Track your requests",
      "See decisions and upcoming approved bookings.",
      "/facilities",
      "View requests",
    );
  } else if (user.role === "approver") {
    title = "Requests needing a decision";
    const pending = db.facilities.filter((item) => item.status === "Pending");
    add(
      `${pending.length} pending requests`,
      "Open each request, check conflicts, then approve or return it with feedback.",
      "/facilities?filter=Pending",
      "Review requests",
    );
    add(
      "Check approved bookings",
      "Review the facility schedule before making a decision.",
      "/facilities?filter=Approved",
      "View bookings",
    );
  } else if (user.role === "admin") {
    title = "Administration shortcuts";
    add(
      "Manage accounts",
      "Search by name or University ID, then open an account to manage access.",
      "/accounts",
      "Find an account",
    );
    add(
      "Office settings",
      "Maintain office branding and available facilities.",
      "/settings",
      "Open settings",
    );
    add(
      "Review system changes",
      "Find recorded actions, who performed them, and when.",
      "/audit",
      "View audit history",
    );
  } else {
    title = "Find the report you need";
    add(
      "Prepare a report",
      "Choose an activity, department, status, or date range. Review the results before downloading.",
      "/reports",
      "Open reports",
    );
  }
  return (
    <section className="task-section" aria-labelledby="task-title">
      <h2 id="task-title">{title}</h2>
      <div className="task-list">
        {tasks.map((task, index) => (
          <article className="task-row" key={task.href}>
            <div>
              <h3>{task.title}</h3>
              <p>{task.detail}</p>
            </div>
            <Link className={`button ${index === 0 ? "" : "secondary"}`} href={task.href}>
              {task.action}
            </Link>
          </article>
        ))}
      </div>
    </section>
  );
}
