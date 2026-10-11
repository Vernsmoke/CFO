import Link from "next/link";
import { redirect } from "next/navigation";
import { ArrowLeft } from "lucide-react";
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
          <h1>
            A space to grow.
            <br />A place to belong.
          </h1>
        </div>
      </section>
      <section className="login-panel">
        <Link href="/" className="back-link">
          <ArrowLeft size={16} /> Back to home
        </Link>
        <div className="login-content">
          <h2>Sign in</h2>
          <LoginForm
            demoPassword={process.env.DEMO_MODE === "true" ? process.env.DEMO_PASSWORD : undefined}
          />
        </div>
      </section>
    </main>
  );
}
