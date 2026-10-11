"use client";

import {
  createContext,
  useActionState,
  useContext,
  useEffect,
  useId,
  useRef,
  useState,
  startTransition,
  type ReactNode,
} from "react";
import { LoaderCircle, CheckCircle2, AlertCircle, Eye, EyeOff, CalendarDays } from "lucide-react";
import { loginAction, workflowAction } from "@/app/actions";
import type { ActionResult } from "@/lib/types";
import { ConfirmDialog } from "./confirm-dialog";
import { useFormSafety } from "./use-form-safety";

const Errors = createContext<Record<string, string>>({});

export function WorkflowForm({
  intent,
  id,
  children,
  label = "Save changes",
  className = "",
  secondary = false,
  draftKey,
}: {
  intent: string;
  id?: string;
  children?: ReactNode;
  label?: string;
  className?: string;
  secondary?: boolean;
  draftKey?: string;
}) {
  const [state, action, pending] = useActionState(workflowAction, {
    ok: false,
    message: "",
  } as ActionResult);
  const formRef = useRef<HTMLFormElement>(null);
  const confirmed = useRef<HTMLInputElement>(null);
  const confirmationReason = useRef<HTMLInputElement>(null);
  const submitRef = useRef<HTMLButtonElement>(null);
  const confirmationData = useRef<FormData | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [confirmation, setConfirmation] = useState<{
    title: string;
    message: string;
    reason: boolean;
  } | null>(null);
  const safety = useFormSafety(formRef, draftKey);
  const saved = safety.saved;
  useEffect(() => {
    if (state.ok) saved();
    if (confirmed.current) confirmed.current.value = "false";
  }, [state, saved]);
  return (
    <Errors.Provider value={{ ...state.errors, ...errors }}>
      <form
        ref={formRef}
        action={action}
        className={`workflow-form ${className}`}
        aria-busy={pending}
        onInvalidCapture={(event) => {
          event.preventDefault();
          const field = event.target as HTMLInputElement;
          setErrors((previous) => ({ ...previous, [field.name]: field.validationMessage }));
          const first = formRef.current?.querySelector<HTMLElement>(":invalid");
          first?.focus();
        }}
        onInputCapture={(event) => {
          const field = event.target as HTMLInputElement;
          setErrors((previous) => {
            const next = { ...previous };
            next[field.name] = "";
            return next;
          });
          safety.changed();
        }}
        onChangeCapture={safety.changed}
        onSubmit={(event) => {
          setErrors({});
          if (confirmed.current?.value === "true") return;
          const data = new FormData(event.currentTarget);
          let next: typeof confirmation = null;
          if (intent === "withdraw")
            next = {
              title: "Withdraw registration?",
              message: "This removes your active registration. Contact CFO if you need assistance.",
              reason: false,
            };
          if (
            intent === "activityStatus" &&
            ["Cancelled", "Archived", "Postponed"].includes(String(data.get("status")))
          )
            next = {
              title: `${data.get("status")} activity?`,
              message:
                "Participants will receive an in-system update. The reason is recorded in the audit history.",
              reason: true,
            };
          if (intent === "account")
            next = {
              title: "Change account access?",
              message:
                "Existing sessions end immediately. Disabled accounts cannot sign in until re-enabled.",
              reason: data.get("active") === "false",
            };
          if (intent === "settings")
            next = {
              title: "Update office settings?",
              message:
                "New requests use the updated facilities. Existing bookings remain unchanged.",
              reason: false,
            };
          if (intent === "brandingLogo")
            next = {
              title: "Publish office logo?",
              message: "This image becomes public branding on sign-in and workspace pages.",
              reason: false,
            };
          if (next) {
            event.preventDefault();
            confirmationData.current = data;
            setConfirmation(next);
          }
        }}
      >
        <input type="hidden" name="intent" value={intent} />
        <input ref={confirmed} type="hidden" name="confirmed" defaultValue="false" />
        {["activityStatus", "account"].includes(intent) && (
          <input ref={confirmationReason} type="hidden" name="reason" defaultValue="" />
        )}
        {id && <input type="hidden" name="id" value={id} />}
        {children}
        <button
          ref={submitRef}
          className={`button ${secondary ? "secondary" : ""}`}
          disabled={pending}
          type="submit"
        >
          {pending && <LoaderCircle size={16} className="spin" />}
          {pending ? (intent === "attendance" ? "Recording…" : "Saving…") : label}
        </button>
        {state.message && (
          <p className={`form-message ${state.ok ? "success" : "error"}`} role="status">
            {state.ok ? <CheckCircle2 size={16} /> : <AlertCircle size={16} />}
            {state.message}
          </p>
        )}
        {safety.draftNotice && (
          <small className="draft-notice" role="status">
            {safety.draftNotice}
          </small>
        )}
        {confirmation && (
          <ConfirmDialog
            title={confirmation.title}
            label="Confirm action"
            onCancel={() => setConfirmation(null)}
            onConfirm={() => {
              const data = confirmationData.current;
              if (!data) return;
              if (confirmation.reason) {
                const reason = formRef.current?.elements.namedItem(
                  "confirmationReason",
                ) as HTMLTextAreaElement | null;
                if (!reason?.reportValidity()) return;
                data.set("reason", reason.value);
              }
              data.set("confirmed", "true");
              setConfirmation(null);
              // Preserve the reviewed values even if React resets the native form.
              startTransition(() => action(data));
            }}
          >
            <p>{confirmation.message}</p>
            {confirmation.reason && (
              <Textarea label="Reason for change" name="confirmationReason" />
            )}
          </ConfirmDialog>
        )}
      </form>
      {safety.leave && (
        <ConfirmDialog
          title="Discard unsaved changes?"
          label="Discard changes"
          onCancel={safety.keep}
          onConfirm={safety.discard}
        >
          <p>Your changes have not been saved.</p>
        </ConfirmDialog>
      )}
    </Errors.Provider>
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
  const error = useContext(Errors)[name];
  const errorId = useId();
  const input = useRef<HTMLInputElement>(null);
  return (
    <label className="field">
      <span>{label}</span>
      <div className={type === "date" ? "date-input" : "field-input"}>
        <input
          ref={input}
          name={name}
          aria-label={label}
          type={type}
          defaultValue={value}
          required={required}
          placeholder={placeholder}
          max={max}
          min={min}
          autoComplete={autoComplete}
          maxLength={type === "text" ? 200 : undefined}
          aria-invalid={Boolean(error)}
          aria-describedby={error ? errorId : undefined}
        />
        {type === "date" && (
          <button
            type="button"
            className="date-picker-button"
            aria-label={`Choose ${label.toLowerCase()}`}
            onClick={() => {
              try {
                input.current?.showPicker();
              } catch {
                input.current?.focus();
              }
            }}
          >
            <CalendarDays size={18} />
          </button>
        )}
      </div>
      {error && (
        <span id={errorId} className="field-error" role="alert">
          {error}
        </span>
      )}
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
  options: readonly (string | { label: string; value: string })[];
  value?: string;
}) {
  const error = useContext(Errors)[name];
  const errorId = useId();
  return (
    <label className="field">
      <span>{label}</span>
      <select
        name={name}
        aria-label={label}
        defaultValue={value}
        aria-invalid={Boolean(error)}
        aria-describedby={error ? errorId : undefined}
      >
        {options.map((o) => (
          <option
            key={typeof o === "string" ? o : o.value}
            value={typeof o === "string" ? o : o.value}
          >
            {typeof o === "string" ? o : o.label}
          </option>
        ))}
      </select>
      {error && (
        <span id={errorId} className="field-error" role="alert">
          {error}
        </span>
      )}
    </label>
  );
}
export function FileField({
  label,
  accept = "application/pdf,image/png,image/jpeg",
  hint = "PDF, PNG, or JPEG · Maximum 5 MB",
}: {
  label: string;
  accept?: string;
  hint?: string;
}) {
  const error = useContext(Errors).file;
  const errorId = useId();
  return (
    <label className="upload-field">
      <span>{label}</span>
      <small>{hint}</small>
      <input
        type="file"
        name="file"
        aria-label={label}
        accept={accept}
        required
        aria-invalid={Boolean(error)}
        aria-describedby={error ? errorId : undefined}
      />
      {error && (
        <span id={errorId} className="field-error" role="alert">
          {error}
        </span>
      )}
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
  const error = useContext(Errors)[name];
  const errorId = useId();
  return (
    <label className="field">
      <span>{label}</span>
      <textarea
        name={name}
        defaultValue={value}
        rows={3}
        aria-label={label}
        maxLength={2000}
        required={required}
        aria-invalid={Boolean(error)}
        aria-describedby={error ? errorId : undefined}
      />
      {error && (
        <span id={errorId} className="field-error" role="alert">
          {error}
        </span>
      )}
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
        {pending ? "Signing in…" : "Sign in"}
        {pending && <LoaderCircle size={16} className="spin" />}
      </button>
      {state.message && (
        <p className="form-message error" role="alert">
          {state.message}
        </p>
      )}
      {demoPassword && (
        <details className="demo-accounts">
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
      <p className="muted small">Need help signing in? Contact the CFO.</p>
    </form>
  );
}
