export function Brand({
  light = false,
  compact = false,
  branding,
}: {
  light?: boolean;
  compact?: boolean;
  branding?: { officeName: string; logo?: string; updatedAt?: string };
}) {
  return (
    <div className={`brand ${light ? "light" : ""}`}>
      <span className="brand-symbol brand-logo">
        <img
          src={branding?.logo
            ? `/api/branding/logo?v=${encodeURIComponent(branding.updatedAt ?? "")}`
            : "/images/cfo-logo.png"}
          alt="Christian Formation Office logo"
          width={48}
          height={48}
        />
      </span>
      <span>
        <strong>{compact ? "La Salette" : (branding?.officeName ?? "Christian Formation")}</strong>
        <small>UNIVERSITY OF LA SALETTE, INC.</small>
      </span>
    </div>
  );
}
