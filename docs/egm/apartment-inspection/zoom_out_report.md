# Stage 5 — Initial Zoom Out Review

**Target:** `apartment-inspection-contact-capture.md` (full file, lines 1–215)

## Reviewer Status

| Reviewer | Status |
| --- | --- |
| `zoom_out` | completed |
| `inventive_catalyst` | completed |

## Findings

### D-015 and D-060 boundaries are not both reconciled — Severity: Major

* **Reviewer lens:** `zoom_out`
* **Affected scope:** Overview and Goals; Technical Plan → Inspection storage and history; Detailed Implementation Plan, `docs/DECISIONS.md` row (around line 190).
* **Discrepancy:** The design adds owner contact data to inspection records, but the planned decision update narrows only `D-060`. Existing `D-015` says owner data belongs in a resale deal when needed, while `DATA_MODEL.md` §4.5 says inspections do not store owner data.
* **Rationale and recommendation:** Leaving `D-015` unreconciled makes the inspection-only exception conflict with an approved product boundary. Amend the decision record to clarify that contact snapshots are a narrow exception for inspections only; do not create a central owner or property entity.

### Mixed-version rollout can create new inspections without contact snapshots — Severity: Major

* **Reviewer lens:** `zoom_out`
* **Affected scope:** Concepts brief → Data, audit, and operations; Design → Technical Plan → Inspection storage and history; Non-Functional Requirements → Rollout and rollback.
* **Discrepancy:** The Concepts Brief permits an earlier application version to keep writing during the additive rollout. The new columns remain nullable and the contact requirement is enforced by the new form and service, so an older application version can still create an inspection without a contact.
* **Rationale and recommendation:** This conflicts with requiring contact details for every new inspection. Specify an atomic cutover or a compatibility step that captures contact data before enforcing the requirement; otherwise, explicitly allow and account for null-contact inspections during rollout.

### Broker confirmation can go stale before submission — Severity: Nit

* **Reviewer lens:** `inventive_catalyst`
* **Affected scope:** Technical Plan → Shared inspection flow (line 71) and Broker link persistence (line 98).
* **Discrepancy:** The form shows the request's broker but sends only a boolean confirmation. If the request broker changes before submission, the service could link the newly assigned broker even though staff confirmed the identity that was displayed when the form opened.
* **Rationale and recommendation:** Bind the confirmation to the exact broker shown and reject the submission if the request broker has changed before saving. This adds stale-form handling and may require staff to reconfirm.

## Review Notes

Both reviewers read the complete target and all supplied context files. They reported no inaccessible paths, used fresh contexts, made no edits, and ran no tests.
