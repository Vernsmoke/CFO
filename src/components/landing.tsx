"use client";
import Link from "next/link";
import Image from "next/image";
import { useRef, useState } from "react";
import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import { useGSAP } from "@gsap/react";
import {
  ArrowRight,
  ArrowUpRight,
  ChevronLeft,
  ChevronRight,
  Sprout,
  HeartHandshake,
  BookOpen,
  Menu,
  X,
} from "lucide-react";
import { Brand } from "./brand";

gsap.registerPlugin(ScrollTrigger, useGSAP);
const journeys = [
  {
    title: "Make room for reflection.",
    body: "Retreats and recollections offer a moment to slow down, listen deeply, and rediscover what matters.",
    icon: Sprout,
    detail:
      "Discover your next formation activity, view its requirements, and register in one place.",
  },
  {
    title: "Find strength in community.",
    body: "Faith takes root in the relationships we build. Gather, celebrate, and grow alongside your fellow Salettinians.",
    icon: HeartHandshake,
    detail:
      "Stay connected through announcements, liturgical celebrations, and shared experiences.",
  },
  {
    title: "Take the next small step.",
    body: "A meaningful journey begins with showing up. Your workspace keeps the practical details simple.",
    icon: BookOpen,
    detail:
      "Submit your profile, upload requirements, and follow staff feedback without a trip to the office.",
  },
];
const guidance = [
  {
    text: "Formation is a journey, and every small step deserves a little space.",
    title: "A simpler way to participate",
    body: "Find activities intended for you, prepare your documents, and follow your registration from your own workspace.",
  },
  {
    text: "Be fully present. We’ll take care of the attendance details.",
    title: "Room for the moment",
    body: "Authorized staff record attendance during activities, so participation never depends on using your phone.",
  },
  {
    text: "Your story deserves care, clarity, and respect.",
    title: "Records handled with purpose",
    body: "Profiles and supporting documents are shared only through authorized access. Official records remain with the responsible university offices.",
  },
];
export function Landing() {
  const root = useRef<HTMLDivElement>(null);
  const [active, setActive] = useState(0);
  const [slide, setSlide] = useState(0);
  const [menu, setMenu] = useState(false);
  useGSAP(
    () => {
      const media = gsap.matchMedia();
      media.add("(prefers-reduced-motion: no-preference)", () => {
        gsap.from(".hero-copy > *", {
          y: 22,
          opacity: 0,
          duration: 0.8,
          stagger: 0.13,
          ease: "power2.out",
          onComplete: () => {
            root.current?.setAttribute("data-motion-ready", "true");
          },
        });
        gsap.fromTo(
          ".reveal-word",
          { opacity: 0.2 },
          {
            opacity: 1,
            stagger: 0.08,
            scrollTrigger: {
              trigger: ".reflection-text",
              start: "top 85%",
              end: "bottom 55%",
              scrub: true,
            },
          },
        );
        gsap.utils.toArray<HTMLElement>(".step-panel").forEach((panel, i) => {
          if (i < 2)
            ScrollTrigger.create({
              trigger: panel,
              start: "top 130px",
              endTrigger: ".steps",
              end: "bottom 620px",
              pin: true,
              pinSpacing: false,
            });
          gsap.from(panel, {
            y: 40,
            opacity: 0.85,
            scrollTrigger: { trigger: panel, start: "top 95%", end: "top 40%", scrub: true },
          });
        });
      });
      if (window.matchMedia("(prefers-reduced-motion: reduce)").matches)
        root.current?.setAttribute("data-motion-ready", "true");
      return () => media.revert();
    },
    { scope: root },
  );
  return (
    <div ref={root} className="landing">
      <header className="public-nav">
        <Link href="/" aria-label="Christian Formation home">
          <Brand />
        </Link>
        <nav className={menu ? "open" : ""} aria-label="Main navigation">
          <a href="#formation" onClick={() => setMenu(false)}>
            Our formation
          </a>
          <a href="#journey" onClick={() => setMenu(false)}>
            Your journey
          </a>
          <Link href="/login" className="button">
            Enter your workspace <ArrowUpRight size={17} />
          </Link>
        </nav>
        <button
          className="icon-button mobile-menu"
          onClick={() => setMenu(!menu)}
          aria-label={menu ? "Close menu" : "Open menu"}
        >
          {menu ? <X /> : <Menu />}
        </button>
      </header>
      <main className="landing-main">
        <section className="public-hero">
          <div className="hero-copy">
            <span className="tiny-overline">CHRISTIAN FORMATION OFFICE</span>
            <h1>
              Rooted in faith.
              <br />
              <em>Growing together.</em>
            </h1>
            <p>
              A shared space for reflection, connection, and the moments that shape who we become.
            </p>
            <div className="hero-actions">
              <Link href="/login" className="button">
                Begin your journey <ArrowUpRight size={18} />
              </Link>
              <a href="#formation" className="text-link">
                Discover formation <ArrowRight size={17} />
              </a>
            </div>
            <div className="hero-signature">
              <span className="signature-line" />
              <span>
                One community.
                <br />
                <strong>Many paths to purpose.</strong>
              </span>
            </div>
          </div>
          <div className="hero-visual">
            <Image
              src="/images/formation.jpg"
              alt="An illustrative landscape inviting a quiet moment of reflection"
              fill
              priority
              sizes="(max-width: 800px) 100vw, 48vw"
            />
            <div className="image-wash" />
            <div className="image-caption">
              <span>PAUSE. REFLECT. RENEW.</span>
              <p>
                There is meaning
                <br />
                in the moments.
              </p>
            </div>
            <span className="photo-disclaimer">Illustrative photograph</span>
          </div>
        </section>
        <div className="formation-marquee" aria-label="Formation programs">
          <div>
            {[0, 1].map((i) => (
              <span key={i} aria-hidden={i === 1}>
                {[
                  "Reflection",
                  "Community",
                  "Recollection",
                  "Purpose",
                  "Retreat",
                  "Celebration",
                ].map((t) => (
                  <span key={t}>
                    {t}
                    <i />
                  </span>
                ))}
              </span>
            ))}
          </div>
        </div>
        <section id="formation" className="formation-section">
          <div className="section-intro">
            <span className="tiny-overline">A LIFE WITH PURPOSE</span>
            <h2>
              More than activities.
              <br />
              <em>Room to become.</em>
            </h2>
            <p>
              Formation meets you where you are and invites you to grow. Explore the spaces that
              bring our community together.
            </p>
          </div>
          <div className="horizontal-accordion">
            {journeys.map((j, index) => {
              const Icon = j.icon;
              return (
                <button
                  key={j.title}
                  className={`journey-panel ${active === index ? "expanded" : ""}`}
                  onMouseEnter={() => setActive(index)}
                  onFocus={() => setActive(index)}
                  onClick={() => setActive(index)}
                  aria-expanded={active === index}
                >
                  <Icon size={29} strokeWidth={1.3} />
                  <h3>{j.title}</h3>
                  <p>{j.body}</p>
                  {active === index && <span>{j.detail}</span>}
                  <ArrowUpRight className="panel-arrow" size={22} />
                </button>
              );
            })}
          </div>
        </section>
        <section className="reflection-section">
          <span className="tiny-overline">A MOMENT TO REMEMBER</span>
          <p className="reflection-text">
            {"Growth doesn’t always begin with a big change. Sometimes it begins with a quiet moment, an open heart, and the courage to take the next step."
              .split(" ")
              .map((word, i) => (
                <span className="reveal-word" key={i}>
                  {word}{" "}
                </span>
              ))}
          </p>
          <span className="reflection-rule" />
        </section>
        <section id="journey" className="journey-section">
          <div className="section-intro">
            <span className="tiny-overline">YOUR PATH, MADE SIMPLER</span>
            <h2>
              Less paperwork.
              <br />
              <em>More presence.</em>
            </h2>
            <p>Everything you need to prepare for formation, thoughtfully brought together.</p>
          </div>
          <div className="steps">
            {[
              {
                number: "01",
                title: "A space of your own",
                body: "Sign in with your University ID Number. Submit your profile for staff verification and discover activities intended for you.",
                link: "Meet your workspace",
              },
              {
                number: "02",
                title: "Prepare with confidence",
                body: "Register for an activity, upload supporting documents, and follow clear staff feedback when corrections are needed.",
                link: "Keep everything together",
              },
              {
                number: "03",
                title: "Show up, wholeheartedly",
                body: "Stay informed about schedules and requirements. During activities, staff handle attendance so you can focus on being present.",
                link: "Be ready for the moment",
              },
            ].map((s) => (
              <article className="step-panel" key={s.number}>
                <span className="step-number">{s.number}</span>
                <div>
                  <h3>{s.title}</h3>
                  <p>{s.body}</p>
                  <Link href="/login">
                    {s.link}
                    <ArrowUpRight size={18} />
                  </Link>
                </div>
                <Sprout size={70} strokeWidth={0.8} className="step-art" />
              </article>
            ))}
          </div>
        </section>
        <section className="guidance-section">
          <div className="guidance-image">
            <Image
              src="/images/reflection.jpg"
              alt="An illustrative natural scene for contemplation"
              fill
              sizes="(max-width: 800px) 100vw, 35vw"
            />
          </div>
          <div className="guidance-copy">
            <span className="tiny-overline">THOUGHTFUL BY DESIGN</span>
            <h2>“{guidance[slide].text}”</h2>
            <strong>{guidance[slide].title}</strong>
            <p>{guidance[slide].body}</p>
            <div className="carousel-controls">
              <button
                className="icon-button"
                aria-label="Previous guidance"
                onClick={() => setSlide((slide + 2) % 3)}
              >
                <ChevronLeft />
              </button>
              <span>{slide + 1} / 3</span>
              <button
                className="icon-button"
                aria-label="Next guidance"
                onClick={() => setSlide((slide + 1) % 3)}
              >
                <ChevronRight />
              </button>
            </div>
          </div>
        </section>
        <section className="public-cta">
          <span className="tiny-overline">THE NEXT STEP IS YOURS</span>
          <h2>
            A little openness.
            <br />
            <em>A world of possibility.</em>
          </h2>
          <Link href="/login" className="button light-button">
            Enter your workspace <ArrowUpRight size={19} />
          </Link>
          <p>Faith. Formation. Community.</p>
        </section>
      </main>
      <footer className="public-footer">
        <Brand />
        <p>
          University of La Salette, Inc.
          <br />
          Christian Formation Office
        </p>
        <Link href="/login">
          Sign in <ArrowUpRight size={15} />
        </Link>
        <small>Local pilot · Fictional records</small>
      </footer>
    </div>
  );
}
