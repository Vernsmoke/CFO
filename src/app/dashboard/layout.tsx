import Link from "next/link";
import { Bell, ChevronRight } from "lucide-react";
import { requireUser } from "@/lib/auth";
import { readDb } from "@/lib/store";
import { Navigation } from "@/components/navigation";
export const dynamic = "force-dynamic";
export default async function DashboardLayout({ children }: { children: React.ReactNode }) {
  const user = await requireUser();
  const db = await readDb();
  const unread = db.notifications.filter((n) => n.userId === user.id && !n.read).length;
  return (
    <div className="app-shell">
      <a href="#workspace-content" className="skip-link">
        Skip to content
      </a>
      <Navigation role={user.role} name={user.name} unread={unread} />
      <div className="workspace">
        <header className="workspace-header">
          <div>
            <span>Christian Formation Office</span>
            <ChevronRight size={13} />
            <strong>Your workspace</strong>
          </div>
          <div className="header-right">
            <span className="term-label">Academic year 2026–2027</span>
            <Link
              className="notification-button"
              href="/dashboard/notifications"
              aria-label={`${unread} unread notifications`}
            >
              <Bell size={19} />
              {unread > 0 && <i />}
            </Link>
            <span className="avatar small-avatar">
              {user.name
                .split(" ")
                .map((n) => n[0])
                .slice(0, 2)
                .join("")}
            </span>
          </div>
        </header>
        <main id="workspace-content" className="workspace-content">
          {children}
        </main>
        <footer className="workspace-footer">
          <span>University of La Salette, Inc. · Christian Formation Office</span>
          <span>
            {process.env.DEMO_MODE === "true"
              ? "Fictional pilot · Local preview"
              : "Faith. Formation. Community."}
          </span>
        </footer>
      </div>
    </div>
  );
}
