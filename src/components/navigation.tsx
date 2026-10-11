"use client";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useRef, useState } from "react";
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
  const sidebarRef = useRef<HTMLElement>(null);
  const toggleRef = useRef<HTMLButtonElement>(null);
  const mobileBarRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const media = window.matchMedia("(max-width: 800px)");
    if (!media.matches) return;
    const sidebar = sidebarRef.current!;
    const toggle = toggleRef.current;
    const workspace = document.querySelector<HTMLElement>(".workspace");
    const skipLink = document.querySelector<HTMLElement>(".skip-link");
    const background = [workspace, skipLink, mobileBarRef.current].filter(
      (element): element is HTMLElement => element !== null,
    );
    const previousInert = background.map((element) => element.inert);
    const previousOverflow = document.body.style.overflow;
    background.forEach((element) => {
      element.inert = true;
    });
    document.body.style.overflow = "hidden";
    const focusable = () =>
      Array.from(
        sidebar.querySelectorAll<HTMLElement>(
          'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex="0"]',
        ),
      ).filter((element) => element.getClientRects().length > 0);
    focusable()[0]?.focus();
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        event.preventDefault();
        setOpen(false);
      }
      if (event.key !== "Tab") return;
      const targets = focusable();
      const first = targets[0];
      const last = targets[targets.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last?.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
      }
    };
    const onResize = () => {
      if (!media.matches) setOpen(false);
    };
    sidebar.addEventListener("keydown", onKeyDown);
    media.addEventListener("change", onResize);
    return () => {
      sidebar.removeEventListener("keydown", onKeyDown);
      media.removeEventListener("change", onResize);
      background.forEach((element, index) => {
        element.inert = previousInert[index];
      });
      document.body.style.overflow = previousOverflow;
      if (media.matches) toggle?.focus();
    };
  }, [open]);
  return (
    <>
      <div className="mobile-bar" ref={mobileBarRef}>
        <Brand compact />
        <button
          className="icon-button"
          ref={toggleRef}
          type="button"
          aria-expanded={open}
          aria-controls="workspace-navigation"
          aria-label={open ? "Close navigation" : "Open navigation"}
          onClick={() => setOpen(!open)}
        >
          {open ? <X /> : <Menu />}
        </button>
      </div>
      {open && (
        <button
          className="sidebar-shade"
          type="button"
          tabIndex={-1}
          aria-label="Close navigation"
          onClick={() => setOpen(false)}
        />
      )}
      <aside
        id="workspace-navigation"
        ref={sidebarRef}
        className={`sidebar ${open ? "open" : ""}`}
        role={open ? "dialog" : undefined}
        aria-modal={open ? true : undefined}
        aria-label={open ? roleLabels[role] : undefined}
      >
        <div className="mobile-drawer-heading">
          <strong>Navigation</strong>
          <button
            type="button"
            className="icon-button"
            aria-label="Close navigation"
            onClick={() => setOpen(false)}
          >
            <X size={20} />
          </button>
        </div>
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
                aria-current={pathname === `/dashboard${path}` ? "page" : undefined}
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
