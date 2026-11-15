# Stage 6 — Comprehension Test (Round 4)

* **Target**: `lead-source-logic.md`
* **Range**: Full file, lines 1–276
* **Reviewer**: `comprehension` completed.
* **Execution**: Read-only review; no tests or edits.

## What this proposes

Keep one optional source on each Rental or Resale request to record the main way the client says they learned about Cible. Stable source types control whether the form asks for a referrer name or explicit unknown, an «أخرى» description, or no extra field. Contact methods stop being available for new selections while historical values remain displayable; first-contact capture is deferred, and a broker is never assumed to be the referrer.

## How the existing system works, per this document

The existing source field mixes contact methods such as phone calls and direct visits with awareness sources such as referrals and ads. The field is optional, and «أخرى» has a separate description. Rental and Resale have separate catalogs, but source options do not yet have stable types to control conditional fields.

## Findings

None. The Round 3 resolutions are clear, including the name-or-explicit-unknown rule for new referrals, preservation of historical nulls on unrelated edits, and clearing dependent fields when the selected source changes.

## Reviewer Status

| Reviewer | Status | Findings |
| --- | --- | --- |
| `comprehension` | Completed | No findings |

## Read Confirmation

The reviewer confirmed reading its persona, the feature-spec standard, the complete 276-line target, and the concepts brief. No tests were run and no files were edited.
