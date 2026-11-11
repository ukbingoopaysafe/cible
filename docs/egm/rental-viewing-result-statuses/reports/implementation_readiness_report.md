# Stage 9: Implementation Readiness Test

**Target:** `docs/egm/rental-viewing-result-statuses/design.md` (DetailedRange, lines 147–226)

## Reviewer Status

| Reviewer | Status |
| --- | --- |
| `implementation_readiness` | completed |
| `codebase_details` | completed |
| `style` | completed |

## Prioritized Findings

### Reservation and Rental Completion Lack Association Rules — Severity: Major

* **Reviewers:** `implementation_readiness`, `codebase_details`
* **Affected scope:** Goals and implementation items 1–2 and 5–6 (lines 20–21, 153–161, 175–185).
* **Discrepancy:** `حجز` and `تم التأجير` refer to a customer and a unit, but the service plan validates only the result and reason. A viewing may lack a linked recommendation, and a request may lack a known customer. The proposed tests do not cover either missing association.
* **Recommendation:** Define whether these outcomes require a linked customer and unit, specify what happens when either is absent, and cover the rule in service validation and tests—or revise the outcome meanings and explain how the association is recorded.

### Result Maps Have No Decided Source of Truth — Severity: Major

* **Reviewers:** `implementation_readiness`
* **Affected scope:** Form, service, history, audit, and related tests (lines 153–178).
* **Discrepancy:** The plan assigns department-specific identifiers and labels to four separate layers but neither selects a shared catalog nor states how the maps must stay aligned. Tests cover departments but do not require the allowed identifiers and labels to match across layers.
* **Recommendation:** Keep the selected in-file department branching and state a concrete consistency rule. Add a test that verifies the rental and resale identifiers and labels agree across form, validation, history, and audit.

### No Implementation Phases or Checkpoints — Severity: Major

* **Reviewers:** `implementation_readiness`, `codebase_details`
* **Affected scope:** Detailed Implementation Plan and Verification Plan (lines 147–226).
* **Discrepancy:** The plan lists eleven files and one combined verification sequence, without ordered phases that leave the codebase stable and testable.
* **Recommendation:** Organize implementation work into ordered phases with a focused verification and completion checkpoint for each.

### Rental Results Need Explicit Reason-Clearing Behavior — Severity: Major

* **Reviewer:** `codebase_details`
* **Affected scope:** Technical Plan and implementation items 2 and 5 (lines 96, 160, 178).
* **Discrepancy:** The service plan clears a reason only when changing away from `unsuitable`. A legacy result such as `not_interested` may already have a reason, and omitted fields are not implicitly cleared by the current service update.
* **Recommendation:** Specify that saving any rental result other than `unsuitable` clears `rejection_reason`, regardless of the previous result. Add a test that changes a legacy `not_interested` result with a reason to a new result.

### History Reason Label Must Match the Stored Result — Severity: Major

* **Reviewer:** `codebase_details`
* **Affected scope:** History display change (line 166).
* **Discrepancy:** The plan changes the rental history reason label to `سبب عدم المناسبة`, while legacy `not_interested` rows may contain a reason currently labeled `سبب عدم الاهتمام`. Applying the new label to those rows would change the meaning of historical data.
* **Recommendation:** Keep the legacy label for `not_interested` and use `سبب عدم المناسبة` for `unsuitable`. Test both cases.

### Legacy Arabic Result Labels Are Unspecified — Severity: Nit

* **Reviewers:** `implementation_readiness`, `codebase_details`
* **Affected scope:** History and audit display changes (lines 166, 172).
* **Discrepancy:** The plan says legacy identifiers remain readable but gives no canonical Arabic labels. Existing history and audit displays use different wording for `second_viewing`.
* **Recommendation:** Define canonical Arabic labels for legacy identifiers and require both history and audit to use them. Also specify how the audit formatter receives the event key and department for both value columns.

### Vague UI Test Rationale — Severity: Nit

* **Reviewer:** `style`
* **Affected scope:** Implementation item 6 (line 184).
* **Discrepancy:** “The right list” does not state a verifiable acceptance criterion.
* **Recommendation:** Name the expected behavior: five rental labels, the existing resale labels, and Arabic labels in history and audit.

## Read Confirmation

All three reviewers confirmed reading their assigned persona, the target range, and the requested context and review reports. The implementation-readiness and codebase reviewers also read the implementation-readiness standard. No target or code files were edited and no tests were run.
