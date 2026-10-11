import { Cross } from "lucide-react";
export function Brand({ light = false, compact = false }: { light?: boolean; compact?: boolean }) {
  return (
    <div className={`brand ${light ? "light" : ""}`}>
      <span className="brand-symbol">
        <Cross strokeWidth={1.5} size={23} />
      </span>
      <span>
        <strong>{compact ? "La Salette" : "Christian Formation"}</strong>
        <small>UNIVERSITY OF LA SALETTE, INC.</small>
      </span>
    </div>
  );
}
