# Stage 8 — Intermediate Zoom Out Review (Round 3)

* **Target**: `lead-source-logic.md`
* **Range**: Full file, lines 1–276
* **Reviewer**: `zoom_out` completed.
* **Execution**: Read-only review; no code or design edits were made.

## Findings

### Major

#### `zoom_out-stage8-r3-1` — Explicitly redact the referrer name from request audit snapshots

**Affected scope:** Privacy and audit; Detailed Implementation item 8; current `RequestService::auditSnapshot()`.

The design promises that audit history records a referrer-name change without storing the name, and the test plan asks to verify redaction. It does not direct the implementation to update the existing redaction list, which omits `lead_source_referrer_name`. Adding the new field to request updates could therefore put the literal name in audit snapshots.

Add `lead_source_referrer_name` to the existing helper's redaction list. Assert that before and after snapshots omit the literal name while still indicating that the field changed. This is the same finding raised in Stage 5 and Stage 7, so it is counted once.

#### `zoom_out-stage8-r3-2` — Set the built-in «أخرى» row active after migration

**Affected scope:** Migration and Historical Values; Detailed Implementation item 1.

The migration maps the built-in row to `other` but does not specify its active state afterward. The form offers active sources, so an inactive «أخرى» row would remain unavailable to staff.

Set the built-in «أخرى» row active in both catalogs during migration and assert that state in the migration test.

## Resolved Earlier Findings

The reviewer confirmed the Round 2 production sequence, export authorization, touched-state behavior for historical nulls, and nullable-backfill-non-null source-type migration are reflected in the target and governing documents. The Stage 7 catalog-size Nit is addressed by the stated small-catalog assumption; a cap or search threshold would add speculative detail without evidence of growth.

## Reviewer Status

| Reviewer | Status | Findings |
| --- | --- | --- |
| `zoom_out` | Completed | 2 Majors |
