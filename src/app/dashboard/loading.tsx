export default function Loading() {
  return (
    <div className="workspace-loading" role="status" aria-live="polite">
      <span>Loading workspace…</span>
      <div className="loading-line" />
      <div className="loading-block" />
      <div className="loading-block" />
    </div>
  );
}
