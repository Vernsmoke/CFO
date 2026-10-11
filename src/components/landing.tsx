"use client";

import Image from "next/image";
import Link from "next/link";
import { useState } from "react";
import {
  ArrowRight,
  ArrowUpRight,
  CalendarDays,
  Check,
  FileCheck2,
  UsersRound,
  ClipboardCheck,
  Bell,
  Menu,
  X,
  Cross,
} from "lucide-react";
import { Brand } from "./brand";

const paths = {
  student: [
    {
      icon: CalendarDays,
      title: "Find your next activity",
      detail: "Retreats, recollections, and liturgies",
      label: "Discover",
    },
    {
      icon: FileCheck2,
      title: "Prepare your requirements",
      detail: "Your documents and staff feedback",
      label: "Prepare",
    },
    {
      icon: UsersRound,
      title: "Be present. Grow together.",
      detail: "Staff take care of attendance",
      label: "Participate",
    },
  ],
  staff: [
    {
      icon: CalendarDays,
      title: "Bring the schedule together",
      detail: "Activities, participants, and requirements",
      label: "Organize",
    },
    {
      icon: FileCheck2,
      title: "Give every record attention",
      detail: "Profile verification and document review",
      label: "Review",
    },
    {
      icon: ClipboardCheck,
      title: "Keep the office in sync",
      detail: "Attendance, reports, and activity history",
      label: "Follow through",
    },
  ],
};

export function Landing() {
  const [audience, setAudience] = useState<"student" | "staff">("student");
  const [menu, setMenu] = useState(false);
  return (
    <div className="landing site" data-motion-ready="true">
      <a href="#main-content" className="skip-link">
        Skip to content
      </a>
      <header className="site-nav">
        <Link href="/" aria-label="Christian Formation home">
          <Brand />
        </Link>
        <nav id="site-navigation" className={menu ? "is-open" : ""} aria-label="Main navigation">
          <a href="#students" onClick={() => setMenu(false)}>
            For students
          </a>
          <a href="#office" onClick={() => setMenu(false)}>
            For the office
          </a>
          <Link href="/login" className="site-sign-in">
            Sign in
          </Link>
          <Link href="/login" className="button">
            Sign in <ArrowUpRight size={18} />
          </Link>
        </nav>
        <button
          className="icon-button site-menu"
          aria-controls="site-navigation"
          aria-expanded={menu}
          aria-label={menu ? "Close menu" : "Open menu"}
          onClick={() => setMenu(!menu)}
        >
          {menu ? <X /> : <Menu />}
        </button>
      </header>
      <main id="main-content">
        <section className="site-hero site-container">
          <div className="site-hero-copy">
            <span className="site-eyebrow">
              <span /> Christian Formation Office
            </span>
            <h1>
              Your formation.
              <br />
              <span>All in one place.</span>
            </h1>
            <p>
              The University of La Salette’s workspace for formation activities, documents,
              attendance, and facility requests. Sign in with your University ID to get started.
            </p>
            <Link href="/login" className="button">
              Sign in to your workspace <ArrowRight size={20} />
            </Link>
            <div className="site-hero-note">
              <Cross size={18} />
              <span>Need help signing in? Contact the Christian Formation Office.</span>
            </div>
          </div>
          <div className="journey-demo">
            <div className="journey-demo-top">
              <span className="site-eyebrow">A clearer path forward</span>
              <ArrowUpRight size={21} />
            </div>
            <div className="audience-switch" aria-label="Preview a formation journey">
              <button aria-pressed={audience === "student"} onClick={() => setAudience("student")}>
                For students
              </button>
              <button aria-pressed={audience === "staff"} onClick={() => setAudience("staff")}>
                For CFO staff
              </button>
            </div>
            <ol className="journey-flow" aria-live="polite">
              {paths[audience].map(({ icon: Icon, title, detail, label }, index) => (
                <li key={title}>
                  <span className="flow-number">0{index + 1}</span>
                  <div className="flow-card">
                    <span className="flow-icon">
                      <Icon size={23} strokeWidth={1.6} />
                    </span>
                    <div>
                      <small>{label}</small>
                      <strong>{title}</strong>
                      <p>{detail}</p>
                    </div>
                  </div>
                </li>
              ))}
            </ol>
            <div className="journey-demo-bottom">
              <span className="flow-dot" /> One workspace, from preparation to participation.
            </div>
          </div>
        </section>
        <div className="site-programs" aria-label="Formation programs">
          <div className="site-container">
            <span>Space for every part of your journey</span>
            <strong>Retreats</strong>
            <strong>Recollections</strong>
            <strong>Liturgies</strong>
            <strong>Formation programs</strong>
          </div>
        </div>
        <section id="students" className="site-students site-container">
          <div className="site-section-heading">
            <span className="site-eyebrow">For students</span>
            <h2>
              Less figuring things out.
              <br />
              More showing up.
            </h2>
            <p>Your next step should be easy to find.</p>
          </div>
          <div className="student-feature-layout">
            <article className="formation-story">
              <div>
                <span className="site-eyebrow">Faith. Formation. Community.</span>
                <h3>
                  A little preparation.
                  <br />A lot more presence.
                </h3>
                <p>
                  Give your attention to the experience. Your workspace keeps the practical details
                  together.
                </p>
                <Link href="/login" className="text-link">
                  Find your next activity <ArrowUpRight size={19} />
                </Link>
              </div>
              <div className="formation-story-image">
                <Image
                  src="/images/retreat.webp"
                  alt="Illustrative retreat garden with a cross and stone arches"
                  fill
                  sizes="(max-width: 800px) 90vw, 40vw"
                />
                <small>Illustrative image</small>
              </div>
            </article>
            <div className="student-feature-list">
              {[
                {
                  icon: CalendarDays,
                  title: "Know what’s coming up",
                  body: "View activities for your department, with schedules, venues, and requirements in one place.",
                },
                {
                  icon: FileCheck2,
                  title: "Keep your progress in view",
                  body: "Upload documents, see review decisions, and follow clear feedback when a correction is needed.",
                },
                {
                  icon: Bell,
                  title: "Stay in the loop",
                  body: "Find office announcements and reminders alongside your registrations.",
                },
              ].map(({ icon: Icon, title, body }, index) => (
                <article key={title}>
                  <span className="feature-index">0{index + 1}</span>
                  <div>
                    <Icon size={25} strokeWidth={1.5} />
                    <h3>{title}</h3>
                    <p>{body}</p>
                  </div>
                </article>
              ))}
            </div>
          </div>
        </section>
        <section id="office" className="site-office">
          <div className="site-container office-layout">
            <div>
              <span className="site-eyebrow">For the people behind formation</span>
              <h2>
                Care for the community.
                <br />
                Keep the details together.
              </h2>
              <p>
                A shared working space for the Christian Formation Office, from reviewing
                requirements to recording participation.
              </p>
              <Link href="/login" className="button">
                Open your office workspace <ArrowRight size={20} />
              </Link>
            </div>
            <div className="office-checklist">
              {[
                ["Activities & participants", "Plan formation and see who is taking part."],
                ["Profiles & document reviews", "Keep decisions and correction feedback together."],
                ["Attendance & reports", "Record participation and find the records you need."],
              ].map(([title, body]) => (
                <div key={title}>
                  <Check size={19} />
                  <span>
                    <strong>{title}</strong>
                    <p>{body}</p>
                  </span>
                </div>
              ))}
            </div>
          </div>
        </section>
        <section className="site-closing site-container">
          <Cross size={32} strokeWidth={1.3} />
          <h2>Your next step starts here.</h2>
          <p>Sign in with your University ID Number to get started.</p>
          <Link href="/login" className="button">
            Go to sign in <ArrowUpRight size={19} />
          </Link>
        </section>
      </main>
      <footer className="site-footer">
        <div className="site-container">
          <Brand light />
          <div className="university-identity">
            <img
              src="/images/uls-logo.png"
              alt="University of La Salette, Inc. seal"
              width={80}
              height={80}
            />
            <div>
              <span>University of La Salette, Inc.</span>
              <small>Santiago City, Philippines</small>
            </div>
          </div>
          <small>Local pilot · Fictional records</small>
        </div>
      </footer>
    </div>
  );
}
