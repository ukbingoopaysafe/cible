# Stage 5: Initial Zoom Out Review

**Target:** `docs/egm/rental-viewing-result-statuses/design.md` (lines 1–226)

## Reviewer Status

| Reviewer | Status |
| --- | --- |
| `zoom_out` | completed |
| `inventive_catalyst` | completed |

## Findings

### Detailed Implementation Plan Lowers the Review Altitude — Severity: Major

* **Reviewer Lens:** `zoom_out`
* **Affected Scope:** Lines 147–226
* **Discrepancy:** The design includes an eleven-file implementation recipe, detailed test assertions, decision-ledger updates, and exact verification commands in addition to the architecture and behavior description.
* **Rationale & Recommendation:** These details do not change the architectural decision and make the feature design harder to review at a high level. Keep the architecture and compatibility requirements in the design document; move the file-by-file recipe and commands into a later implementation plan.

### Repeated Status Maps May Drift Across Layers — Severity: Major

* **Reviewer Lens:** `inventive_catalyst`
* **Affected Scope:** Lines 96, 102–106, 154, 160, 166, 172
* **Discrepancy:** The selected approach branches in the form, service, history, and audit formatter, leaving each layer to maintain allowed values, labels, legacy values, and department-specific behavior.
* **Rationale & Recommendation:** The maps can diverge, causing the form to submit values the service rejects or leaving saved values unreadable. Consider a small department-aware result catalog. Its trade-off is an added dependency and a requirement for every caller, especially the audit formatter, to supply the correct department; tests must cover both departments.
