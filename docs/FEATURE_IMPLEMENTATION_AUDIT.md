# CFO Feature Implementation Audit

> Update, 15 October 2026: Activities now require exact course, year, and section targets, a reviewed matched-student count before publishing, activity-specific Recollection/Retreat forms without signatures, and Mass submission evidence. The Mass camera image is a private submission artifact, not a general student time-in/time-out attendance module.

Audited on 11 October 2026 against the local `main` checkout at `abe570a`. This corrects the earlier blanket claim that all suggested improvements were complete. Application code was not changed during this audit.

## Evidence and verification

Read the pages, shared components, server actions, policies and role configuration. Re-ran four existing enhancement browser scenarios and added four focused audit scenarios using isolated fictional data in Microsoft Edge. All eight targeted checks passed across the audit runs. Some audit assertions deliberately confirm missing behavior; a passing audit test is not a claim that the whole feature is complete.

Local evidence: `.preview/feature-audit-observations.json` and `tests/browser/z-feature-audit.spec.ts`. These are ignored by Git. The prior production build, lint, 9 policy tests and 12 regression tests passed before this audit; those results did not establish full coverage of the proposed feature list.

## The twelve additional suggestions

| Suggestion | Finding | Current behavior and remaining gap |
| --- | --- | --- |
| Individual document review | Implemented for activity submissions | Required activity files are validated during submission, listed with Required or Optional status, privately downloadable, and reviewable by authorized staff. |
| Attendance by activity | Limited to requested Mass proof | Mass activities require a live in-app camera capture stored as private submission evidence. There is no separate student time-in/time-out or roll-call module; CFO Staff work attendance remains separate. |
| Next action after saving | Partial, with a confirmed message bug | Some responses explain the next step, but others are generic. Saving an already Published activity incorrectly says “Publish it when ready for registration.” Registration success replaces the form with Submitted and a submission link; the action’s detailed success message disappears. |
| Separate consequential actions | Partial | Activity editing is separate from status changes; withdrawal is expandable and confirmed. Role editing and account active/disabled status still share one Update permissions form. There is no dedicated disable/re-enable action. |
| Deadline/correction indicators | Implemented in basic form | Activity deadline labels, correction statuses, student dashboard attention notice and staff feedback exist. There is no explicit near-deadline countdown or missing-document progress checklist. Those are refinements, not previously confirmed university rules. |
| Preserve filters while opening records | Implemented for current inline records and Back navigation | Search/filter/page values stay in the URL. Details expand inside the list. Leaving and returning with Back preserved the search and page in the browser audit. Clicking a fresh sidebar link does not restore a separately remembered last-used list URL. Dedicated record detail pages are not built. |
| Useful report filters | Implemented | Activity, department, activity-date range, submission status and group affect displayed aggregates and staff CSV exports. Date filtering is based on the activity date, not attendance or registration timestamps. Aggregate viewers cannot export student records. |
| CFO Staff attendance outcomes | Implemented | CFO Staff record photo-backed time-in and time-out checkpoints. Super Admins can monitor present, late, absent, incomplete, and excused staff by date. |
| Expandable secondary details | Implemented | Descriptions, record histories, previous document versions, document review controls and corrections expand on demand. |
| Year/section targeting | Implemented as exact rules | Course, year, and section are all required. Student lists, direct submissions, publishing confirmation, and notifications use exact profile matches. |
| Participant grouping | Basic implementation; partial broader organization | Staff assign free-text group and facilitator per participant. Group filtering and facilitator text search exist. Section-based organization and a dedicated facilitator filter are absent. Bulk assignment and group management screens are also absent; these would be extensions, not explicit requirements in the original suggestion. |
| Branding/facility settings | Implemented for administrators | Administrators can edit office name/facility choices and publish an approved PNG/JPEG logo. CFO staff and students do not have settings access. Confirmation assumes the administrator has an approved asset; there is no separate university branding approval workflow. |

## Additional confirmed defect

**Resolved locally on 11 October 2026:** The false untouched-form warning described below was fixed after the audit. Empty file inputs now have a stable comparison value, and dirty state compares current fields with the original values rather than retaining a flag after edits are reverted. Original values survive React effect re-runs so restored drafts still receive a warning. Four targeted Edge checks verified untouched submission navigation/sign-out, selected-file protection and clearing, text edits and reversion, and restored drafts. This update does not resolve the other gaps in this audit.

**Unsaved-change warnings can appear without edits.** A fresh student login followed by opening My submissions and signing out produced one Discard unsaved changes dialog even though no fields were edited. Multiple submission forms can cause repeated dialogs. Root cause was not established during this audit; fix and verify it before treating the guard as complete.

## Where controls are located

- Individual document decisions: CFO staff → Submission review → uploaded document → Review this document. Without an upload there is no review control.
- CFO Staff attendance: CFO Staff → My attendance. Super Admin monitoring: Super Admin → Staff attendance.
- Targeting/required document names: CFO staff → Formation activities → Create or Edit activity.
- Report filters/export: CFO staff → Reports. Viewers have aggregate filters but no student CSV export.
- Branding/facility configuration: Administrator → Office settings.

Seeded activities may have only free-text requirements. Named document requirements must be configured before the requirement selector and named-document enforcement apply. Do not infer that a hidden role-specific or data-dependent control is wholly absent.

## Recommended completion order

1. Fix false unsaved-change warnings and the incorrect Published-activity feedback.
2. Add a named-requirement checklist with Missing, Pending review, Requires correction and Accepted states for students and reviewers.
3. Separate account disable/re-enable from role editing, preserving server checks, confirmations and required reasons.
4. Make successful responses consistently identify the next appropriate action.
5. Add section-based organization and, if desired, an explicit facilitator filter. Obtain confirmation before inventing bulk/group-management business rules.

Facility availability is currently a day-based schedule, not a full multi-day calendar. The date input enhancement opens the native browser date picker; it is not a custom Gym-style calendar. Automated reminders, offline attendance and production university integrations remain outside the implemented scope.

No application fixes, commits, pushes or deployment were performed during the audit. Only local audit tests and documentation were added. User approval for the previously suggested improvement scope remains in the conversation; the next agent should use this audit to complete that scope when instructed, without claiming extensions were already promised.

## Bug-fix follow-up — 11 October 2026

The later repair pass resolved the incorrect Published-activity feedback and the disappearing registration confirmation described above. Registration cards now retain their reference and link directly to the matching submission. This does not add the optional missing-document checklist or separate account-status controls.

Additional reproduced defects fixed:

- Rejected server saves reset uncontrolled form fields and selected files. Workflow forms now dispatch without an automatic reset and reset only after a successful result. Failed sign-in also preserves entered credentials for retry. Unsaved-change protection remains active after errors.
- The dashboard counted past Published activities as upcoming and showed unsorted records. It now excludes earlier Philippine calendar dates and sorts by date/time.
- Activity tiles advertised registration as open after the deadline or at capacity. Their labels now account for publication, the Philippine deadline date, and active participant count. Calendar badges use a fixed timezone.

Validation: 29 distinct Edge browser checks passed across the full and targeted runs, including all six role menus at desktop and 320px widths, rejected-save recovery, failed upload retention, published activity feedback, registration references, past dates and full/closed activity labels. An initially ambiguous login-test selector was corrected and rerun successfully. Ten policy tests, source lint, type checking, production compilation and diff whitespace checks passed. Four of the browser checks remain diagnostic audit scenarios rather than claims that optional feature gaps are complete. Existing university/business limitations remain unchanged. No commit, push or deployment was performed.
