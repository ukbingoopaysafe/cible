# Consolidated EGM Review: Rental Viewing Result Statuses

**Target:** `docs/egm/rental-viewing-result-statuses/design.md`  
**Execution:** Consolidated full review, Stages 5–9  
**Status:** Findings remain open; the review made no edits to the design or product code.

## Overall Assessment

The rental-only scope and separation from follow-up, appointment scheduling, request closure, and deal creation are clear. The review found no Blocker. Six Major findings need decisions or document changes before implementation; four Nits improve precision and consistency.

The author selected department branching in the existing files, with resale behavior preserved pending consultation. The recommendations below retain that choice and ask for an explicit consistency rule rather than silently changing the architecture to add a shared catalog.

## 🔴 Blocker

None.

## 🟡 Major

### M1. Define the customer and unit requirements for reservation and rental completion

`حجز` and `تم التأجير` describe an outcome involving a particular customer and unit. The existing workflow allows a viewing without a linked recommendation and a request without a known customer. The design must state whether these results require those associations, what to do when an association is absent, and how the service and tests enforce the rule.

### M2. Separate design decisions from a phased implementation recipe

The design includes a file-by-file change list, test assertions, document edits, and exact verification commands. That level of detail makes the design harder to review at the architectural level. Keep behavior, boundaries, architecture, and compatibility requirements in the design; put the file recipe and commands in an implementation plan organized into ordered, testable phases with focused verification and completion checkpoints.

### M3. State how the department-specific result maps stay aligned

The selected approach keeps rental and resale branches in the existing form, service, history, and audit files. The design does not say how their allowed values and labels will stay consistent. Keep the selected architecture, state the consistency rule, and add a test that checks identifiers and labels across the form, validation, history, and audit.

### M4. Clear stale rental reasons for every result other than `unsuitable`

The service plan only clears the reason when changing away from `unsuitable`. A legacy result such as `not_interested` may already carry a reason. Specify that saving any other rental result clears `rejection_reason`, regardless of its previous value, and test that legacy transition.

### M5. Preserve the meaning of legacy history reasons

The proposed history label `سبب عدم المناسبة` must not relabel reasons saved under legacy `not_interested` results, which currently use `سبب عدم الاهتمام`. Keep the old label for the legacy result and use the new label for `unsuitable`; test both.

### M6. Add a concise risk-and-limits assessment

Name the relevant risks and mitigations, including legacy readability, department-specific validation, and audit integrity. State whether this change adds any new scaling path and explain why other risk categories do not apply. Remove the blanket statement that no performance or security requirements are introduced unless it is replaced with a concrete constraint.

## 🟢 Nit

### N1. Specify canonical Arabic labels for legacy result identifiers

Legacy values must remain readable, but their exact labels are not stated. History and audit already use different labels for `second_viewing`. Define the canonical legacy label map and use it in both displays; specify how event and department context reaches both audit value columns.

### N2. Narrow the problem statement

The current form has general outcomes and free-text fields. Say that its structured list cannot represent the agreed rental outcomes consistently, rather than suggesting staff cannot record outcomes at all.

### N3. Reduce repeated resale consultation wording

State once that resale choices remain unchanged until resale employees are consulted. Avoid repeating the boundary in open questions and future work.

### N4. Replace “the right list” with testable wording

In implementation item 6, name the expected five rental labels, existing resale labels, and Arabic labels in history and audit.

## Traceability Matrix

| Consolidated item | Raw review finding(s) |
| --- | --- |
| M1 | Stage 7 `design` association finding; Stage 8 `zoom_out` association finding; Stage 9 `implementation_readiness` and `codebase_details` association findings |
| M2 | Stage 5 `zoom_out` implementation-altitude finding; Stage 8 `zoom_out` implementation-altitude finding; Stage 9 `implementation_readiness` and `codebase_details` phase/checkpoint findings |
| M3 | Stage 5 `inventive_catalyst` result-map drift finding; Stage 8 `zoom_out` result-map finding; Stage 9 `implementation_readiness` result-map finding |
| M4 | Stage 9 `codebase_details` stale-reason finding |
| M5 | Stage 9 `codebase_details` history-reason-label finding |
| M6 | Stage 7 `structural` risk-and-limits finding; Stage 7 `style` blanket-NFR nit; Stage 8 `zoom_out` unresolved risk wording |
| N1 | Stage 6 `comprehension` legacy-label nit; Stage 8 `zoom_out` legacy-label nit; Stage 9 `implementation_readiness` and `codebase_details` legacy-label findings |
| N2 | Stage 7 `style` problem-statement nit; Stage 8 `zoom_out` wording finding |
| N3 | Stage 7 `style` repeated-boundary nit; Stage 8 `zoom_out` wording finding |
| N4 | Stage 9 `style` vague-rationale nit |

## Review Artifacts

* Stage 5: `zoom_out_report.md`
* Stage 6: `comprehension_report.md`
* Stage 7: `critic_report.md`
* Stage 8: `intermediate_zoom_out_report.md`
* Stage 9: `implementation_readiness_report.md`
