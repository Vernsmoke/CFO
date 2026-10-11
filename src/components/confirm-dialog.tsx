"use client";
import { useEffect, useRef, type ReactNode } from "react";
import { X } from "lucide-react";

export function ConfirmDialog({
  title,
  children,
  onCancel,
  onConfirm,
  label = "Confirm",
}: {
  title: string;
  children: ReactNode;
  onCancel: () => void;
  onConfirm: () => void;
  label?: string;
}) {
  const ref = useRef<HTMLDialogElement>(null);
  useEffect(() => {
    const trigger = document.activeElement as HTMLElement | null;
    const dialog = ref.current!;
    const overflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    dialog.showModal();
    return () => {
      dialog.close();
      document.body.style.overflow = overflow;
      if (trigger?.isConnected) trigger.focus();
    };
  }, []);
  return (
    <dialog
      ref={ref}
      className="confirm-dialog"
      aria-labelledby="confirmation-heading"
      onCancel={(event) => {
        event.preventDefault();
        onCancel();
      }}
    >
      <div className="dialog-heading">
        <h2 id="confirmation-heading">{title}</h2>
        <button type="button" className="icon-button" aria-label="Close dialog" onClick={onCancel}>
          <X size={20} />
        </button>
      </div>
      {children}
      <div className="dialog-actions">
        <button type="button" className="button secondary" autoFocus onClick={onCancel}>
          Keep editing
        </button>
        <button type="button" className="button" onClick={onConfirm}>
          {label}
        </button>
      </div>
    </dialog>
  );
}
