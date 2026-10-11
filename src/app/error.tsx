"use client";
export default function ErrorPage({ reset }: { reset: () => void }) {
  return (
    <div className="error-page">
      <h1>We couldn’t load this view.</h1>
      <p>Try again. If the problem continues, contact the office.</p>
      <button className="button" onClick={reset}>
        Try again
      </button>
    </div>
  );
}
