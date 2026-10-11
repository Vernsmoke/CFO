"use client";

import { useActionState, useState, type ReactNode } from "react";
import { LoaderCircle, CheckCircle2, AlertCircle, Eye, EyeOff } from "lucide-react";
import { loginAction, workflowAction } from "@/app/actions";
import type { ActionResult } from "@/lib/types";

export function WorkflowForm({
  intent,
  id,
  children,
  label = "Save changes",
  className = "",
  secondary = false,
}: {
  intent: string;
  id?: string;
  children?: ReactNode;
  label?: string;
  className?: string;
  secondary?: boolean;
}) {
  const [state, action, pending] = useActionState(workflowAction, {
    ok: false,
    message: "",
  } as ActionResult);
  return (
    <form action={action} className={`workflow-form ${className}`}>
      <input type="hidden" name="intent" value={intent} />
      {id && <input type="hidden" name="id" value={id} />}
      {children}
      <button className={`button ${secondary ? "secondary" : ""}`} disabled={pending} type="submit">
        {pending && <LoaderCircle size={16} className="spin" />}
        {pending ? "Saving…" : label}
      </button>
      {state.message && (
        <p className={`form-message ${state.ok ? "success" : "error"}`} role="status">
          {state.ok ? <CheckCircle2 size={16} /> : <AlertCircle size={16} />}
          {state.message}
        </p>
      )}
    </form>
  );
}
export function Field({
  label,
  name,
  type = "text",
  value,
  required = true,
  placeholder,
  max,
  min,
  autoComplete,
}: {
  label: string;
  name: string;
  type?: string;
  value?: string | number;
  required?: boolean;
  placeholder?: string;
  max?: number | string;
  min?: number | string;
  autoComplete?: string;
}) {
  return (
    <label className="field">
      <span>{label}</span>
      <input
        name={name}
        type={type}
        defaultValue={value}
        required={required}
        placeholder={placeholder}
        max={max}
        min={min}
        autoComplete={autoComplete}
        maxLength={type === "text" ? 200 : undefined}
      />
    </label>
  );
}
export function Select({
  label,
  name,
  options,
  value,
}: {
  label: string;
  name: string;
  options: readonly string[];
  value?: string;
}) {
  return (
    <label className="field">
      <span>{label}</span>
      <select name={name} defaultValue={value}>
        {options.map((o) => (
          <option key={o}>{o}</option>
        ))}
      </select>
    </label>
  );
}
export function Textarea({
  label,
  name,
  value = "",
  required = true,
}: {
  label: string;
  name: string;
  value?: string;
  required?: boolean;
}) {
  return (
    <label className="field">
      <span>{label}</span>
      <textarea name={name} defaultValue={value} rows={3} maxLength={2000} required={required} />
    </label>
  );
}
export function LoginForm({ demoPassword }: { demoPassword?: string }) {
  const [showPassword, setShowPassword] = useState(false);
  const [state, action, pending] = useActionState(loginAction, {
    ok: false,
    message: "",
  } as ActionResult);
  return (
    <form action={action} className="login-form">
      <Field
        label="University ID Number"
        name="universityId"
        placeholder="e.g. 2024-0001"
        autoComplete="username"
      />
      <div className="field">
        <label htmlFor="login-password">Password</label>
        <div className="password-input">
          <input
            id="login-password"
            name="password"
            type={showPassword ? "text" : "password"}
            required
            autoComplete="current-password"
            maxLength={128}
          />
          <button
            type="button"
            className="password-toggle"
            aria-label={showPassword ? "Hide password" : "Show password"}
            aria-controls="login-password"
            aria-pressed={showPassword}
            onClick={() => setShowPassword(!showPassword)}
          >
            {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
          </button>
        </div>
      </div>
      <button className="button" disabled={pending}>
        {pending ? "Signing in…" : "Sign in to your workspace"}
        {pending && <LoaderCircle size={16} className="spin" />}
      </button>
      {state.message && (
        <p className="form-message error" role="alert">
          {state.message}
        </p>
      )}
      {demoPassword && (
        <details className="demo-accounts" open>
          <summary>Explore the fictional pilot</summary>
          <p>
            Shared pilot password: <code>{demoPassword}</code>
          </p>
          <div className="account-grid">
            {[
              ["Student", "2024-0001"],
              ["CFO staff", "CFO-001"],
              ["Department", "DEP-001"],
              ["Approver", "APR-001"],
              ["Administrator", "ADM-001"],
              ["Viewer", "VIEW-001"],
            ].map(([role, id]) => (
              <button
                type="button"
                key={id}
                onClick={(event) => {
                  const form = event.currentTarget.form!;
                  (form.elements.namedItem("universityId") as HTMLInputElement).value = id;
                  (form.elements.namedItem("password") as HTMLInputElement).value = demoPassword;
                }}
              >
                <span>{role}</span>
                <code>{id}</code>
              </button>
            ))}
          </div>
        </details>
      )}
      <p className="muted small">
        Need account assistance? Contact the Christian Formation Office. An ID Number alone does not
        establish identity.
      </p>
    </form>
  );
}
