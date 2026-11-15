# Stage 6 — Comprehension Test (Round 3)

## What this proposes

Store one optional, client-identified awareness source on each Rental or Resale request, keeping the departments’ catalogs separate. Give each source a stable type that controls form fields and validation: referrals require a name or an explicit unknown choice, while «أخرى» requires its own description. Preserve historical values, leave unknown sources unset, and never infer a referral from a property broker or contact method. The plan also covers catalog management, migration, exports, audit privacy, tests, and production rollout.

## How the existing system works, per this document

The existing source field mixes awareness sources such as ads with contact methods such as calls and visits. It shows a description field for «أخرى», but has no referrer field or stable type for manager-added sources. Rental and Resale have separate catalogs and permissions, and historical values must remain intact rather than being reinterpreted without evidence.

## Clarity and self-containment findings

### Major

#### `comprehension-r3-1` — Specify the active state for the built-in «أخرى» source

**Affected scope:** “Migration and Historical Values”; Detailed Implementation, item 1; request form behavior.

The migration maps the built-in «أخرى» row to `other`, but does not specify whether it must be active afterward. It also says to preserve the active state of other known rows. If «أخرى» is inactive in production, it may remain unavailable in the form, even though the design treats it as a selectable built-in option and prevents managers from deactivating it.

Specify the intended migration state for this row.

## Reviewer Status

| Reviewer | Status | Findings |
| --- | --- | --- |
| `comprehension` | Completed | 1 Major |
