# Codebase Details Review — Round 5

* **Target**: [lead-source-logic.md](./lead-source-logic.md)
* **Range**: Detailed Implementation, lines 180–293 at review time
* **Reviewer**: `codebase_details` completed.
* **Execution**: Read-only review; no tests or project code changes.

## Major

### `codebase_details-r5-1` — Preserve submitted fields compatible with a new source selection

**Affected scope:** Request Data and Form Flow; Detailed Implementation items 7–8 and tests 10/16.

Item 8 directs `RequestService` to clear all three dependent fields whenever `lead_source_id` changes, before validation. Taken literally, that removes a newly submitted referrer name or unknown flag when switching to `person_referral`, or a newly submitted description when switching to `other`; the new selection then fails validation.

Current `RequestService::updateDetails()` merges submitted keys over stored request attributes. Existing logic distinguishes a submitted `lead_source_other` from the old stored description when changing source. The plan needs the same distinction for all typed dependent values: discard stale values from the previous selection, preserve values submitted and valid for the selected type, clear only incompatible or omitted stale values, then validate.

Tests should cover switching to `person_referral` with a submitted name and with explicit unknown; switching to `other` with a submitted description; clearing incompatible old values; rejecting missing required values; switching to awareness/no source and clearing all dependent values; and preserving historical nulls on unrelated edits. The existing lifecycle test only covers clearing an «أخرى» description when switching to a non-«أخرى» source.

## No Other Codebase Mismatches

The proposed new enum, type columns, request snapshots, and changes to shared form/service logic are correctly identified as future changes; they are not described as existing behavior. No other discrepancy was verified.

## Reviewer Status

| Reviewer | Status | Findings |
| --- | --- | --- |
| `codebase_details` | Completed | 1 Major |

The reviewer confirmed reading the assigned persona and standard, target, project/document context, relevant Round 4/5 reports, current request service/form, models, migrations, and related tests. No tests were run and no files were edited.
