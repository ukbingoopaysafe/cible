# Stage 8 — Intermediate Zoom Out Review (Round 2)

* **Target**: `lead-source-logic.md`
* **Range**: Full file, lines 1–276
* **Reviewer**: `zoom_out` completed.
* **Execution**: Read-only review; no code or design edits were made.

## Findings

### Major

#### `zoom_out-stage8-round2-1` — The rollout still permits a code/schema mismatch

**Affected scope:** Non-Functional Requirements, “Rollout and rollback”; production step in the verification plan.

The plan deploys the updated application and then applies the migration. The new code expects the source type and referrer columns, so requests during that interval can fail against the old schema. The forward-only rollback rule does not close this deployment gap.

Specify a serving order that prevents requests from reaching the updated application until the migration completes, or define a backward-compatible rollout.

#### `zoom_out-stage8-round2-2` — Referrer-name exports need a governing decision update

**Affected scope:** CUJ 2, Privacy and audit, exporter changes, and the proposed edits to `D-051` and `AT-158`.

The design adds referrer names to exports, while `D-062` limits exported fields to those approved under `D-030` and `D-048`. The implementation plan currently updates `D-051` and `AT-158` but not `D-062`; the acceptance update also does not explicitly cover exported names and unknown states. The concepts brief's note that the owner approved this change does not update the governing decision record.

Add a superseding `D-062` decision authorizing referrer exports, and update `AT-158` to assert the exported name, explicit unknown state, and blank historical state.

## Resolved Earlier Findings

The reviewer confirmed that the prior actions for typed source eligibility, migration mappings, referral validation, audit labeling, `other` handling, and catalog scale are incorporated in the current design.

## Reviewer Status

| Reviewer | Status | Findings |
| --- | --- | --- |
| `zoom_out` | Completed | 2 Majors |
