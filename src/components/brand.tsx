import { Cross } from "lucide-react";
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
      <span className="brand-symbol">
        {branding?.logo ? (
          <img
            src={`/api/branding/logo?v=${encodeURIComponent(branding.updatedAt ?? "")}`}
            alt="Office logo"
            width={32}
            height={36}
          />
        ) : (
          <Cross strokeWidth={1.5} size={23} />
        )}
      </span>
      <span>
        <strong>{compact ? "La Salette" : (branding?.officeName ?? "Christian Formation")}</strong>
        <small>UNIVERSITY OF LA SALETTE, INC.</small>
      </span>
    </div>
  );
}
