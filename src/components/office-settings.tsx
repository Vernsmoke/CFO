import { Field, FileField, Textarea, WorkflowForm } from "./forms";
import { Brand } from "./brand";
import type { Database } from "@/lib/types";
import { configuredFacilities } from "@/lib/policy";
export function OfficeSettings({ db }: { db: Database }) {
  return (
    <div className="settings-stack">
      <section className="surface form-surface">
        <h2>Office and facilities</h2>
        <WorkflowForm intent="settings" label="Save office settings">
          <Field
            label="Office name"
            name="officeName"
            value={db.settings?.officeName ?? "Christian Formation Office"}
          />
          <Textarea
            label="Available facilities (one per line)"
            name="facilities"
            value={configuredFacilities(db).join("\n")}
          />
        </WorkflowForm>
      </section>
      <section className="surface form-surface">
        <h2>Office logo</h2>
        <div className="logo-preview">
          <Brand branding={db.settings} />
        </div>
        <WorkflowForm intent="brandingLogo" label="Publish logo">
          <FileField
            label="Choose an approved PNG or JPEG logo"
            accept="image/png,image/jpeg"
            hint="Maximum 1 MB. This logo will be public."
          />
        </WorkflowForm>
      </section>
    </div>
  );
}
