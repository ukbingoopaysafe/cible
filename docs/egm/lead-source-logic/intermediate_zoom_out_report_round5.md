# Stage 8 — Intermediate Zoom Out Review (Round 5)

* **Target**: `lead-source-logic.md`
* **Range**: Full file, lines 1–293
* **Reviewer**: `zoom_out` completed.
* **Execution**: Read-only review; no tests or edits.

## Major

### `zoom_out-stage8-r5-1` — Preserve submitted values that belong to the newly selected source type

**Affected scope:** Request Data and Form Flow; Detailed Implementation items 7 and 8.

The design says source changes clear dependent fields and that `RequestService` clears `lead_source_referrer_name`, `lead_source_referrer_unknown`, and `lead_source_other` whenever the source ID changes, before validating the new type. A user changing a request to `person_referral` or `other` must submit new dependent values, but a literal implementation of the unconditional clearing also erases those submitted values. That makes the changed-source referral and «أخرى» journeys fail validation.

Clarify that the service discards stale dependent values from the previous source, preserves submitted values valid for the newly selected type, clears only fields incompatible with the new type, and then validates the resulting values. The form may clear old dependent state immediately on selection change so staff can enter the new type's values.

## No Additional Findings

The reviewer confirmed Round 4's production engine/version rehearsal requirement and found no other distinct system-level finding. Code search confirmed reuse of the existing shared form, service, catalog, export, and audit paths.

## Reviewer Status

| Reviewer | Status | Findings |
| --- | --- | --- |
| `zoom_out` | Completed | 1 Major |
