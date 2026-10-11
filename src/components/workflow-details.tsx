import type { Audit, DocumentRecord, User } from "@/lib/types";
import { formatDate, formatTime, Status } from "./ui";
import { Field, Select, WorkflowForm } from "./forms";
export function RecordHistory({
  events,
  staff = false,
  users = [],
}: {
  events: Audit[];
  staff?: boolean;
  users?: User[];
}) {
  return (
    <details className="record-history">
      <summary>History ({events.length})</summary>
      {events.length ? (
        <ol>
          {events.map((event) => (
            <li key={event.id}>
              <strong>{event.action}</strong>
              <small>
                {formatDate(event.at)} · {formatTime(event.at)}
                {staff
                  ? ` · ${users.find((user) => user.id === event.actorId)?.name ?? "Office"}`
                  : ""}
              </small>
              {event.detail && <p>{event.detail}</p>}
            </li>
          ))}
        </ol>
      ) : (
        <p className="muted">No recorded actions yet.</p>
      )}
    </details>
  );
}
export function DocumentReview({
  document,
  staff,
  editable,
}: {
  document: DocumentRecord;
  staff: boolean;
  editable: boolean;
}) {
  return (
    <div className={`document-record ${document.supersededBy ? "superseded" : ""}`}>
      <div className="between">
        <a href={`/api/documents/${document.id}`} target="_blank" rel="noreferrer">
          {document.filename}
        </a>
        <Status>
          {document.supersededBy ? "Replaced" : (document.status ?? "Pending review")}
        </Status>
      </div>
      {document.requirement && <p className="small muted">{document.requirement}</p>}
      <small>
        {formatDate(document.uploadedAt)} · {formatTime(document.uploadedAt)}
      </small>
      {document.note && <p className="notice-inline">{document.note}</p>}
      {staff && editable && !document.supersededBy && (
        <details className="correction-details">
          <summary>Review this document</summary>
          <WorkflowForm intent="reviewDocument" id={document.id} label="Save document review">
            <Select
              label="Document decision"
              name="status"
              value={document.status === "Accepted" ? "Accepted" : "Requires correction"}
              options={["Accepted", "Requires correction"]}
            />
            <Field
              label="Document feedback (required for corrections)"
              name="note"
              required={false}
              value={document.note}
            />
          </WorkflowForm>
        </details>
      )}
    </div>
  );
}
