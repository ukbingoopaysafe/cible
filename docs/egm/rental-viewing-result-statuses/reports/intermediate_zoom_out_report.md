# Stage 8: Intermediate Zoom Out Review

**Target:** `docs/egm/rental-viewing-result-statuses/design.md`  
**Reviewer:** `zoom_out`  
**Status:** Complete — revisions remain

## Context and Reuse Check

The objective and root problem are clear, and the rental-only vocabulary fits the project’s request-centered workflow. The draft preserves the existing viewing, audit, and follow-up flows; it avoids a new schema, scheduling automation, request closure, and deal creation. This aligns with `PRD-042`, `D-043`, `D-058`, and `D-060`.

Code search found the existing result form in `SectionRequestResource`, validation and transactional audit in `RecommendationService`, and result rendering in the history view and `AuditEventResource`. No shared result catalog exists. The same legacy values are currently labeled in multiple places, with `second_viewing` rendered differently in history and audit. Also, `scheduleViewing` permits a missing `recommendation_id`, and requests may exist without a known customer.

## Findings

### Major — Reservation and Rental Completion Can Assert Unrecorded Associations

**Affected scope:** Goals; CUJ 1; Data Flow; result meanings for `حجز` and `تم التأجير`.

The result meanings refer to a customer and a unit, but the existing workflow allows a viewing without a linked recommendation and a request without a linked customer. The draft does not say whether these outcomes require those associations or how they should be understood when either is absent. Define the business precondition, or narrow the meaning so it does not assert a relationship the records cannot support.

### Major — Detailed Implementation Recipe Remains at Design Altitude

**Affected scope:** Detailed Implementation Plan, especially the eleven-file change list and Verification Plan.

The file-by-file edits, test assertions, documentation updates, and exact commands are implementation instructions rather than architectural decisions. This Stage 5 concern remains. Keep the design focused on behavior, boundaries, and compatibility; move the recipe into an implementation plan.

### Major — Per-Layer Result Maps Still Have No Single Source

**Affected scope:** Technical Plan; Alternatives Considered — Shared Department-Aware Result Catalog.

The alternative recognizes drift risk, but the selected approach still puts allowed values and labels in the form, service, history, and audit formatter. The existing code has no catalog to reuse, so this is a new abstraction trade-off rather than a reuse opportunity. Resolve the choice explicitly: use one small department-aware mapping if the repeated maps are too easy to diverge, or retain local maps and identify the consistency rule that keeps UI, validation, history, and audit aligned. The alternatives section alone does not mitigate the drift risk.

### Nit — Legacy Display Labels and Historical Consistency Are Unspecified

**Affected scope:** Data Flow; Non-Functional Requirements; Detailed Implementation Plan items 3–4.

The draft requires legacy values to remain readable but gives no required label map. Existing code supplies labels, but history and audit do not currently use identical wording for `second_viewing`. Name the labels or explicitly require both views to reuse one agreed mapping.

### Nit — Stage 7 Wording and Risk Notes Are Still Unresolved

**Affected scope:** The Problem; Non-Functional Requirements; Open Questions; Future Improvements.

The problem statement still says staff “cannot record” the outcomes, although the current form has general choices and free text; state that its structured list cannot represent the agreed outcomes consistently. The resale consultation boundary is repeated across several sections; keep it once in scope and avoid restating it in the open-question and future-work text. The blanket claim that no performance or security requirements are added is not verifiable. For this small vocabulary change, name the actual bounded risks (legacy readability, department-specific validation, and audit integrity) and say no new scaling path is introduced, or remove the blanket sentence.

## Stage 6/7 Mitigation Status

The Stage 6 legacy-label nit is not resolved. The Stage 7 association and detailed-risk findings are not resolved; the Stage 7 problem wording, resale-boundary repetition, and blanket NFR sentence also remain. The Stage 5 detailed-plan and result-map concerns remain, although the draft has documented the result-catalog trade-off.
