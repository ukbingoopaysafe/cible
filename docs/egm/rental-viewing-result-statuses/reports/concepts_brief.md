# Viewing Result Statuses

* **Author:** ukbingoopaysafe
* **Date:** 2026-11-10

## Overview

Update the rental viewing result choices to: `حجز`, `غير مناسبة`, `يرغب بمعاينة شقة أخرى`, `تم التأجير`, and `انتظار الرد`.

The author defined `حجز` as the customer reserving the unit, `تم التأجير` as completing the rental of the unit associated with that viewing to the same customer, and `انتظار الرد` as waiting for the customer's response. `يرغب بمعاينة شقة أخرى` records the customer's intent only; it does not schedule a viewing or ask for an apartment or date. A reason remains required for `غير مناسبة`.

## Analysis

This result is recorded on a viewing. The existing product requirements say that a deal is created explicitly from a request that has already been closed successfully (`PRD-040`). Therefore, choosing a viewing result should not automatically close a request or create a deal. Those remain separate, explicit actions.

The current viewing form is defined in a shared request resource used by both rental and resale. The author chose to apply the new choices to rental only and keep the current resale choices until consulting resale employees. The implementation must keep the two departments' choices separate so a shared form does not change resale by accident.

The author's choice avoids imposing rental-specific language on resale staff before their workflow is understood. Temporarily mirroring the rental choices would reduce branching, but it could teach resale staff terms that do not fit their work. Delaying the rental change too would hold up a workflow the rental employees have already reviewed.

The form choices, server-side validation, viewing history labels, and audit labels need to stay aligned. New submissions should use only the approved vocabulary for their department. Arabic phrases are display labels; stored values should remain stable identifiers. Existing saved outcomes should remain readable in history and audit records, including the `from` and `to` values in audit events. Reading or editing an old viewing must not silently rewrite its result. Rewriting old values is unnecessary.

Each viewing keeps its current result; changing it replaces that result, and the audit record retains the previous and new values. `انتظار الرد` is a recorded outcome, distinct from a viewing with no result. The current workflow already calls for setting the next follow-up after a viewing, so use that existing action rather than adding a new alert, metric, or date rule to the result form.

The result belongs to an individual viewing, which may be linked to a recommended unit. `تم التأجير` refers to the unit associated with that viewing being rented to the same customer.

## Goals vs. Non-Goals

* **In-Scope Goals:**
  * Use the approved rental choices when recording a viewing result.
  * Require a reason for `غير مناسبة`.
  * Keep `يرغب بمعاينة شقة أخرى` as a saved result only.
  * Wait for the customer's response for `انتظار الرد`.
  * Keep historical outcomes readable in viewing history and audit records.
  * Preserve old results when a viewing is opened or edited; only an explicit result change replaces the result.
  * Use the existing next-follow-up action after a viewing.
  * Keep the form, validation, history, and audit labels consistent.
* **Out-of-Scope Non-Goals:**
  * Scheduling another viewing or selecting another apartment from this result form.
  * Automatically closing a request or creating a deal from a viewing result.
  * Changing resale outcome choices before consulting resale employees.
  * Rewriting historical result values.

## Alternatives Weighed

* **Add scheduling behavior to the third result:** Rejected by the author; the author confirmed that it records a status only. The label was changed to `يرغب بمعاينة شقة أخرى` to make that intent clear.
* **Automatically close a request or create a deal for `حجز` or `تم التأجير`:** Not selected. `PRD-040` requires a user to create a deal explicitly from a successfully closed request, so an automatic transition would conflict with the current workflow.
* **Apply the rental choices to resale temporarily:** Rejected by the author. This avoids an immediate department-specific split, but could impose unreviewed terminology on resale employees.
* **Keep resale's current choices until the team is consulted:** Selected by the author. This avoids changing their workflow before their requirements are known; rental and resale will use separate choice sets for now.

## Key Decisions & Staging for Next Steps

* The rental choices are `حجز`, `غير مناسبة`, `يرغب بمعاينة شقة أخرى`, `تم التأجير`, and `انتظار الرد`.
* `حجز` means the customer reserved the unit; `تم التأجير` means the unit associated with this viewing was rented to that customer.
* `انتظار الرد` means the customer has not yet replied.
* A reason is required for `غير مناسبة`.
* The third choice records intent only; no viewing is scheduled.
* Viewing results should not automatically close a request or create a deal, consistent with `PRD-040`.
* Apply the new list to rental only. Keep resale's current choices until the author consults resale employees.
* Keep existing saved result values readable in history and audit records.
* Keep the current result on each viewing until an explicit change; preserve the old and new values in the audit trail.
* Use the existing next-follow-up action after recording a result.
