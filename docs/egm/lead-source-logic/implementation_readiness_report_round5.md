# Implementation Readiness Review — Round 5

* **Target**: [lead-source-logic.md](./lead-source-logic.md)
* **Range**: Detailed Implementation, lines 180–293 at review time
* **Reviewer**: `implementation_readiness` completed.
* **Execution**: Read-only review; no tests or project code changes.

## Major Findings

### `implementation_readiness-r5-1` — Do not clear newly submitted values valid for the changed source

**Affected scope:** Detailed Implementation items 7–8; Request Data and Form Flow.

The form and service instructions say to clear dependent fields on source change. The service rule does not distinguish stale stored values from newly submitted values valid for the new type. An unconditional clear before validation can erase a newly entered referrer name or explicit unknown when changing to `person_referral`, or erase the new description when changing to `other`.

**Required change:** Clear prior form state on selection change so staff can enter the new details. On the server, discard stored values belonging to the old source, preserve submitted values valid for the selected new type, clear only incompatible values, then validate. Test `person_referral` transitions with both name and explicit-unknown cases, `other` with a new description, invalid missing values, and transitions to `awareness` or no source that clear all dependent values.

### `implementation_readiness-r5-2` — Add implementation phases with validation checkpoints

**Affected scope:** Detailed Implementation and Verification Plan.

The plan lists files and overall verification but does not divide work into phases or specify validation gates for intermediate phases. The implementation-readiness standard requires ordered phases that leave the codebase buildable and testable.

**Required change:** Group the exact file list into ordered phases and specify a focused test/assertion checkpoint for each: schema/types/migration; catalog management/audit; request form/persistence; exports/acceptance records; integrated and production compatibility verification.

## Reviewer Status

| Reviewer | Status | Findings |
| --- | --- | --- |
| `implementation_readiness` | Completed | 2 Majors |

The reviewer confirmed reading the assigned persona and standard, target, form-flow and rollout context, README, and Round 5 reports. No tests were run and no files were edited.
