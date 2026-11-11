# Stage 7: Critic Review

**Target:** `docs/egm/rental-viewing-result-statuses/design.md` (FeatureRange, lines 1–146)

## Reviewer Status

| Reviewer | Status |
| --- | --- |
| `design` | completed |
| `structural` | completed |
| `style` | completed |

## Prioritized Findings

### Association Rules for Reservation and Rental Completion Are Missing — Severity: Major

* **Reviewer Lens:** `design`
* **Affected Scope:** Lines 20–21, 71, 84–96
* **Discrepancy Description:** `حجز` and `تم التأجير` refer to a specific customer and unit, but the design does not define what happens if the viewing has no linked recommendation or the request has no linked customer. The plan validates the result vocabulary but does not address missing associations.
* **Rationale & Recommendation:** A result may assert a customer/unit relationship the records cannot support. Specify whether those results require a known customer and linked unit, or how their meaning is retained when an association is missing.

### Risk and Limits Coverage Is Incomplete — Severity: Major

* **Reviewer Lens:** `structural`
* **Affected Scope:** Lines 126–131, Non-Functional Requirements
* **Discrepancy Description:** The section lists compatibility, audit integrity, and department isolation, then says no additional performance or security requirements apply. It does not explicitly assess scalability limits or explain why other risk categories do not apply.
* **Rationale & Recommendation:** Add a concise risks-and-limits assessment naming applicable risks and mitigations and stating why other categories do not apply.

### Problem Statement Overstates the Current Gap — Severity: Nit

* **Reviewer Lens:** `style`
* **Affected Scope:** Lines 13–15
* **Discrepancy Description:** “Staff cannot record the agreed rental outcomes consistently” may imply the current form has no way to record outcomes; it currently has general choices and free-text fields.
* **Rationale & Recommendation:** Say that the current structured list cannot represent the agreed outcomes consistently.

### Resale Consultation Boundary Is Repeated — Severity: Nit

* **Reviewer Lens:** `style`
* **Affected Scope:** Lines 25, 31, 39, 65, 118, 145
* **Discrepancy Description:** The resale consultation boundary repeats across scope, open questions, alternatives, and future improvements.
* **Rationale & Recommendation:** State the boundary once and reserve Future Improvements for the decision to make after consultation.

### Blanket Performance and Security Statement Adds No Constraint — Severity: Nit

* **Reviewer Lens:** `style`
* **Affected Scope:** Line 131
* **Discrepancy Description:** “No additional performance or security requirements are introduced” does not state a verifiable requirement or decision.
* **Rationale & Recommendation:** Drop the sentence or replace it with a concrete constraint.
