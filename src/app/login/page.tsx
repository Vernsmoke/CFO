import Link from "next/link";
import Image from "next/image";
import { redirect } from "next/navigation";
import { ArrowLeft, CalendarDays, FileCheck2, Bell, ArrowUpRight } from "lucide-react";
import { currentUser } from "@/lib/auth";
import { Brand } from "@/components/brand";
import { LoginForm } from "@/components/forms";
import { readDb } from "@/lib/store";
export default async function LoginPage() {
  if (await currentUser()) redirect("/dashboard");
  const db = await readDb();
  return (
    <main className="login-page">
      <section className="login-story">
        <Link href="/">
          <Brand branding={db.settings} />
        </Link>
        <div className="login-intro">
          <span className="site-eyebrow">Your formation workspace</span>
          <h1>
            A place for faith.
            <br />
            <span>A little less paperwork.</span>
          </h1>
          <p>Everything you need to prepare, participate, and stay connected.</p>
          <div className="login-path">
            <span>
              <CalendarDays size={20} /> Find your activities
            </span>
            <span>
              <FileCheck2 size={20} /> Follow your requirements
            </span>
            <span>
              <Bell size={20} /> Keep up with the office
            </span>
          </div>
        </div>
        <div className="login-formation-note">
          <div>
            <Image
              src="/images/chapel.webp"
              alt="Illustrative chapel interior"
              fill
              sizes="100px"
            />
          </div>
          <span>
            <strong>
              Rooted in faith.
              <br />
              Growing together.
            </strong>
            <small>Illustrative image</small>
          </span>
          <ArrowUpRight size={24} />
        </div>
      </section>
      <section className="login-panel">
        <Link href="/" className="back-link">
          <ArrowLeft size={16} /> Back to home
        </Link>
        <div className="login-content">
          <span className="site-eyebrow">Welcome back</span>
          <h2>Sign in</h2>
          <p className="login-description">Continue to your formation workspace.</p>
          <LoginForm
            demoPassword={process.env.DEMO_MODE === "true" ? process.env.DEMO_PASSWORD : undefined}
          />
        </div>
      </section>
    </main>
  );
}
