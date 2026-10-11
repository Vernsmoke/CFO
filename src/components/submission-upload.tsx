"use client";
import { useState } from "react";
import { FileField, WorkflowForm } from "./forms";
export function SubmissionUpload({
  registrationId,
  requirements,
  documents,
}: {
  registrationId: string;
  requirements: string[];
  documents: { id: string; filename: string; requirement?: string }[];
}) {
  const [replacement, setReplacement] = useState("");
  const [requirement, setRequirement] = useState(requirements[0] ?? "");
  return (
    <WorkflowForm intent="upload" id={registrationId} label="Upload and submit for review">
      {documents.length > 0 && (
        <label className="field">
          <span>Upload version</span>
          <select
            name="replacesId"
            value={replacement}
            onChange={(event) => {
              setReplacement(event.target.value);
              const document = documents.find((item) => item.id === event.target.value);
              if (document) setRequirement(document.requirement ?? "");
            }}
          >
            <option value="">New document</option>
            {documents.map((document) => (
              <option key={document.id} value={document.id}>
                Replace {document.filename}
              </option>
            ))}
          </select>
        </label>
      )}
      {requirements.length > 0 ? (
        <label className="field">
          <span>Document requirement</span>
          <select
            name="requirement"
            value={requirement}
            onChange={(event) => setRequirement(event.target.value)}
            disabled={Boolean(replacement)}
          >
            {requirements.map((value) => (
              <option key={value}>{value}</option>
            ))}
          </select>
          {replacement && <input type="hidden" name="requirement" value={requirement} />}
        </label>
      ) : (
        <input type="hidden" name="requirement" value={requirement} />
      )}
      <FileField label="Choose a supporting document" />
    </WorkflowForm>
  );
}
