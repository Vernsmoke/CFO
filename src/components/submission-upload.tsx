"use client";
import { FileField, WorkflowForm } from "./forms";
import { Status } from "./ui";
type UploadDocument = {
  id: string;
  filename: string;
  requirement?: string;
  status?: string;
  note?: string;
};
export function SubmissionUpload({
  registrationId,
  requirements,
  documents,
}: {
  registrationId: string;
  requirements: string[];
  documents: UploadDocument[];
}) {
  const rows = requirements.map((requirement) => ({
    requirement,
    document: documents.find((item) => item.requirement === requirement),
  }));
  const extra = documents.filter((item) => !requirements.includes(item.requirement ?? ""));
  return (
    <section className="upload-checklist" aria-label="Document checklist">
      <h3>Your document checklist</h3>
      <p className="muted small">
        Upload each requirement below. CFO staff will review it and leave feedback here.
      </p>
      {rows.map(({ requirement, document }) => (
        <UploadRow
          key={requirement}
          registrationId={registrationId}
          requirement={requirement}
          document={document}
        />
      ))}
      {extra.map((document) => (
        <UploadRow
          key={document.id}
          registrationId={registrationId}
          requirement={document.requirement ?? ""}
          document={document}
        />
      ))}
      {!requirements.length && <UploadRow registrationId={registrationId} requirement="" />}
    </section>
  );
}
function UploadRow({
  registrationId,
  requirement,
  document,
}: {
  registrationId: string;
  requirement: string;
  document?: UploadDocument;
}) {
  return (
    <article className="upload-checklist-row">
      <div className="between">
        <h4>{requirement || document?.filename || "Supporting document"}</h4>
        <Status>{document?.status ?? (document ? "Pending review" : "Not uploaded")}</Status>
      </div>
      {document && <p className="small">Current file: {document.filename}</p>}
      {document?.note && <p className="notice-inline">Staff feedback: {document.note}</p>}
      {document?.status === "Accepted" ? (
        <p className="small muted">CFO has accepted this document. No replacement is needed.</p>
      ) : (
        <WorkflowForm
          intent="upload"
          id={registrationId}
          label={document ? "Replace and submit for review" : "Upload and submit for review"}
        >
          <input type="hidden" name="requirement" value={requirement} />
          <input type="hidden" name="replacesId" value={document?.id ?? ""} />
          <FileField
            label={
              document
                ? `Choose a replacement for ${requirement || document.filename}`
                : `Choose ${requirement || "a supporting document"}`
            }
          />
        </WorkflowForm>
      )}
    </article>
  );
}
