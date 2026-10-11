import Link from "next/link";
export default function NotFound() {
  return (
    <main className="error-page">
      <span className="tiny-overline">CHRISTIAN FORMATION OFFICE</span>
      <h1>This page isn’t available.</h1>
      <p>It may not exist or may fall outside your account’s permissions.</p>
      <Link className="button" href="/dashboard">
        Return to your workspace
      </Link>
    </main>
  );
}
