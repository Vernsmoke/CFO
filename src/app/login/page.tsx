import Link from "next/link";
import { redirect } from "next/navigation";
import { ArrowLeft, ShieldCheck } from "lucide-react";
import { currentUser } from "@/lib/auth";
import { Brand } from "@/components/brand";
import { LoginForm } from "@/components/forms";
export default async function LoginPage() {
  if (await currentUser()) redirect("/dashboard");
  return (
    <main className="login-page">
      <section className="login-story">
        <Link href="/">
          <Brand light />
        </Link>
        <div>
          <span className="tiny-overline">FAITH. FORMATION. COMMUNITY.</span>
          <h1>
            A space to grow.
            <br />A place to belong.
          </h1>
          <p>
            Your journey of formation begins with a little openness. We’re here to walk with you.
          </p>
        </div>
        <small>
          University of La Salette, Inc.
          <br />
          Christian Formation Office
        </small>
      </section>
      <section className="login-panel">
        <Link href="/" className="back-link">
          <ArrowLeft size={16} /> Back to home
        </Link>
        <div className="login-content">
          <span className="tiny-overline">WELCOME BACK</span>
          <h2>
            Let’s continue
            <br />
            your journey.
          </h2>
          <p className="muted">Sign in with your University ID Number.</p>
          <LoginForm
            demoPassword={process.env.DEMO_MODE === "true" ? process.env.DEMO_PASSWORD : undefined}
          />
          <div className="secure-note">
            <ShieldCheck size={16} /> Your records are protected and accessible by permission.
          </div>
        </div>
      </section>
    </main>
  );
}
