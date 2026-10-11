"use client";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState } from "react";
import {
  LayoutDashboard,
  CalendarDays,
  FileCheck2,
  UserRound,
  UsersRound,
  ClipboardCheck,
  Building2,
  Megaphone,
  ChartNoAxesCombined,
  History,
  Bell,
  Shield,
  Menu,
  X,
  LogOut,
  ArrowUpRight,
  type LucideIcon,
} from "lucide-react";
import { Brand } from "./brand";
import { logoutAction } from "@/app/actions";
import type { Role } from "@/lib/types";

export const roleLabels: Record<Role, string> = {
  student: "Student workspace",
  staff: "CFO staff workspace",
  representative: "Department workspace",
  approver: "Approver workspace",
  admin: "Administration",
  viewer: "Reporting workspace",
};
const entries: { title: string; path: string; icon: LucideIcon; roles: Role[] }[] = [
  {
    title: "Overview",
    path: "",
    icon: LayoutDashboard,
    roles: ["student", "staff", "representative", "approver", "admin", "viewer"],
  },
  {
    title: "Formation activities",
    path: "/activities",
    icon: CalendarDays,
    roles: ["student", "staff"],
  },
  { title: "My submissions", path: "/submissions", icon: FileCheck2, roles: ["student"] },
  { title: "My profile", path: "/profile", icon: UserRound, roles: ["student"] },
  { title: "Student profiles", path: "/profiles", icon: UsersRound, roles: ["staff"] },
  { title: "Submission review", path: "/submissions", icon: FileCheck2, roles: ["staff"] },
  { title: "Attendance", path: "/attendance", icon: ClipboardCheck, roles: ["staff"] },
  {
    title: "Facilities",
    path: "/facilities",
    icon: Building2,
    roles: ["staff", "representative", "approver"],
  },
  { title: "Announcements", path: "/announcements", icon: Megaphone, roles: ["student", "staff"] },
  { title: "Reports", path: "/reports", icon: ChartNoAxesCombined, roles: ["staff", "viewer"] },
  { title: "Accounts & roles", path: "/accounts", icon: Shield, roles: ["admin"] },
  { title: "Audit history", path: "/audit", icon: History, roles: ["staff", "admin"] },
  {
    title: "Notifications",
    path: "/notifications",
    icon: Bell,
    roles: ["student", "staff", "representative", "approver", "admin", "viewer"],
  },
];
export function Navigation({ role, name, unread }: { role: Role; name: string; unread: number }) {
  const pathname = usePathname();
  const [open, setOpen] = useState(false);
  return (
    <>
      <div className="mobile-bar">
        <Brand compact />
        <button
          className="icon-button"
          aria-label={open ? "Close navigation" : "Open navigation"}
          onClick={() => setOpen(!open)}
        >
          {open ? <X /> : <Menu />}
        </button>
      </div>
      {open && (
        <button
          className="sidebar-shade"
          aria-label="Close navigation"
          onClick={() => setOpen(false)}
        />
      )}
      <aside className={`sidebar ${open ? "open" : ""}`}>
        <Link href="/" className="sidebar-brand">
          <Brand compact />
        </Link>
        <div className="workspace-label">{roleLabels[role]}</div>
        <nav aria-label="Workspace navigation">
          {entries
            .filter((e) => e.roles.includes(role))
            .map(({ title, path, icon: Icon }) => (
              <Link
                href={`/dashboard${path}`}
                key={path}
                onClick={() => setOpen(false)}
                className={`nav-link ${pathname === `/dashboard${path}` ? "active" : ""}`}
              >
                <Icon size={19} strokeWidth={1.7} />
                <span>{title}</span>
                {path === "/notifications" && unread > 0 && <b>{unread}</b>}
              </Link>
            ))}
        </nav>
        <div className="sidebar-bottom">
          <div className="support-note">
            <span className="tiny-overline">A MOMENT FOR YOU</span>
            <p>
              Faith grows in the
              <br />
              everyday.
            </p>
            <Link
              href={
                role === "student" || role === "staff"
                  ? "/dashboard/announcements"
                  : "/dashboard/notifications"
              }
            >
              Stay connected <ArrowUpRight size={15} />
            </Link>
          </div>
          <div className="sidebar-user">
            <span className="avatar">
              {name
                .split(" ")
                .map((n) => n[0])
                .slice(0, 2)
                .join("")}
            </span>
            <div>
              <strong>{name}</strong>
              <small>{roleLabels[role]}</small>
            </div>
            <form action={logoutAction}>
              <button className="icon-button" aria-label="Sign out">
                <LogOut size={17} />
              </button>
            </form>
          </div>
        </div>
      </aside>
    </>
  );
}
