# Goldfish Annotated Review Report

**Target:** `apartment-inspection-contact-capture.md` (full file; Stage 7 lines 1–174 and Stage 9 lines 175–215)  
**Status:** resolved

**Resolution:** The user accepted all 16 proposed fixes. All 16 were applied to the target design; 0 contested, 0 escalated, 0 declined, and 0 remain unaddressed. Product files and project documentation on `main` were not changed.

## Prioritized Findings & AI Suggestions

### Issue 1 — Owner-data decision trail remains contradictory [`design_critic`, `zoom_out`] — Severity: Major

* **Affected Scope:** Lines 39–41, 88–90, and 190–193 (`apartment-inspection-contact-capture.md`).
* **Discrepancy Description:** The design permits a permanent owner/broker contact snapshot on inspection history, but the plan proposes narrowing only `D-060`; `D-015` and related model/workflow text still prohibit or limit owner data in inspection records.
* **Rationale:** This is a valid conflict in canonical project documentation. The user-approved scope is an inspection-only snapshot, so the design should record that narrow exception consistently.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** User decisions in the drafting process authorize the inspection-only snapshot. The plan should update both decision boundaries and related docs, while retaining the no-central-owner/unit-catalog rule.
* ***Proposed Edit:*** In the implementation plan, replace the `DECISIONS.md` row with a requirement to append a decision clarifying the narrow inspection-history exception to both `D-015` and `D-060`; update PRD, DATA_MODEL, and WORKFLOWS references to match. State explicitly that no central owner/property/listing/unit entity is introduced.

### Issue 2 — Old application writers can create inspections without contacts [`zoom_out`, `implementation_readiness`] — Severity: Major

* **Affected Scope:** Lines 88, 148–153, 183, and 186.
* **Discrepancy Description:** Contact columns remain nullable and the concepts brief permits an older application version to keep writing. An old writer can create a new inspection without the required contact snapshot.
* **Rationale:** This contradicts the new-inspection requirement and is not prevented by a forward-only migration.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The feature can keep legacy rows nullable while blocking writes from old versions after the requirement takes effect.
* ***Proposed Edit:*** Add rollout and rollback steps requiring the additive migration first, followed by an atomic cutover to the new writer (or a temporary write-disable for inspection actions during cutover). Keep inspection writes disabled if application rollback reactivates an old writer. Preserve nullable fields for historical rows and state when required-contact validation becomes effective.

### Issue 3 — Migration refusal and data-preservation behavior are underspecified [`implementation_readiness`, `style`] — Severity: Major

* **Affected Scope:** Lines 183 and 202–209.
* **Discrepancy Description:** `down()` must refuse to drop the new fields, but the plan does not define how operators see that refusal or what rollback leaves behind. It also has no automated test proving stored snapshots and existing defect values survive.
* **Rationale:** Permanent contact history is a retention invariant; a manual confirmation alone does not test it.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** Explicit failure behavior and a migration test make the forward-only guarantee observable and verifiable for both departments.
* ***Proposed Edit:*** State that `down()` aborts with a clear error before removing either table's contact columns, that the expanded schema remains after application rollback, and that operators must not roll back this data-bearing migration. Add a migration test that attempts rollback for both department tables and asserts the columns, saved contact values, and existing `observed_defects` values remain.

### Issue 4 — Phone and broker-link payload-to-storage mapping is incomplete [`comprehension`, `codebase_details`] — Severity: Major

* **Affected Scope:** Lines 69, 71–73, and 183–187.
* **Discrepancy Description:** The target names `PhoneField` and `CustomerPhoneNormalizer` but does not state the country-selector/default behavior, submitted form/service keys, raw-to-normalized mapping, or broker-link flag. A target-only reader cannot tell where the required country value comes from.
* **Rationale:** The form, service, and database contract otherwise requires the implementer to choose field names and transformations. Existing code confirms a country selector/default, but the target does not explain it.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** This is needed for self-contained review and consistent service and migration implementation.
* ***Proposed Edit:*** Specify inputs `contact_role` (`owner`/`broker`), `contact_name`, `phone`, `phone_country`, `link_request_broker`, and `shown_request_broker_id`; state that the country selector defaults to Egypt and staff select/confirm the correct country; normalize `phone` plus `phone_country` to `contact_phone_e164` and `contact_phone_country`. Define role/name/phone/country limits (role up to 16 characters, name up to 255, E.164 up to 16 characters, two-letter country code) and reject invalid or missing values.

### Issue 5 — Audit payload keys and encodings are not defined [`style`, `zoom_out`, `implementation_readiness`, `codebase_details`] — Severity: Major

* **Affected Scope:** Lines 92, 186, 189, and 208.
* **Discrepancy Description:** “Role and link metadata” does not establish which fields must be written, whether broker ID is null or absent when unlinked, or how role values are displayed.
* **Rationale:** Audit storage, UI labels, and tests can otherwise implement different payloads. Personal contact name, phone, and notes must remain excluded.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The concepts brief already bounds the permitted audit facts; the detailed plan should make the exact contract executable.
* ***Proposed Edit:*** Define the event metadata keys and allowed values as `contact_role` (`owner`/`broker`), `broker_link_confirmed` (boolean), and `linked_broker_id` (broker ID when linked, null when unlinked). Require Arabic display of `owner`/`broker` as **مالك الوحدة**/**وسيط**. Test exact linked and unlinked metadata and assert no name, phone, or notes are present.

### Issue 6 — Implementation order and checkpoints are missing [`implementation_readiness`, `codebase_details`] — Severity: Major

* **Affected Scope:** Lines 179–209.
* **Discrepancy Description:** The plan lists files and final tests without ordered buildable phases or gates.
* **Rationale:** The implementer must determine the safe sequence and completion criteria for each slice.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** Ordered checkpoints reduce integration risk and align the approved plan with its focused verification steps.
* ***Proposed Edit:*** Divide the plan into: (1) additive schema and model relations; (2) service validation, transaction, and workflow tests; (3) shared form/history/audit UI and UI tests; (4) decisions, workflows, acceptance docs, and legacy baseline; (5) final focused verification and CURRENT_STATE update. State the passing check(s) required at each phase.

### Issue 7 — Defect submission behavior and acceptance case conflict [`implementation_readiness`, `codebase_details`] — Severity: Major

* **Affected Scope:** Lines 186, 194–195; `docs/ACCEPTANCE_TESTS.md` AT-086.
* **Discrepancy Description:** Removing the `observed_defects` validation rule may silently ignore the submitted key rather than reject it. AT-086 still requires new Rental inspections to record defects.
* **Rationale:** The service contract and acceptance criteria would contradict the intended removal of the new defects field.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The user's chosen workflow removes defects from new entries while retaining historic values, and the concepts brief says the service must stop accepting them.
* ***Proposed Edit:*** State that service requests containing `observed_defects` are rejected and add a forged/stale-payload assertion. Update AT-086 for new inspection notes and legacy-only defects; add or retain a distinct acceptance case for read-only display of existing defect values.

### Issue 8 — Test plan does not separate known baseline failures [`implementation_readiness`] — Severity: Major

* **Affected Scope:** Lines 196 and 203–206.
* **Discrepancy Description:** `CURRENT_STATE.md` records 18 failures in the latest full test run, including two broker-entry cases in `FilamentUiAccessTest`, but the plan treats the entire test file as a new pass/fail gate.
* **Rationale:** The planned result cannot distinguish existing failures from regressions.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The test plan should define a usable acceptance criterion without claiming known failures pass.
* ***Proposed Edit:*** Identify the inspection-specific test methods to run and require those to pass; document the existing failure baseline and do not report the entire file or suite as passing. Alternatively, explicitly include resolving the existing failures in scope.

### Issue 9 — Broker identity may change after staff confirm it [`inventive_catalyst`, `design_critic`, `zoom_out`] — Severity: Nit

* **Affected Scope:** Lines 71, 96–99, and 186.
* **Discrepancy Description:** A boolean confirmation can be submitted after the request's broker changes, causing the service to link a different broker than the one staff saw.
* **Rationale:** The saved ID should match the identity staff confirmed at inspection time.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** An atomic recheck makes the confirmation meaningful during concurrent reassignment.
* ***Proposed Edit:*** Submit `shown_request_broker_id`; require the service to lock/re-read the request in the save transaction, compare its current broker ID with that submitted ID, and reject for reconfirmation if it changed.

### Issue 10 — Form labels and role mapping are implicit [`implementation_readiness`, `codebase_details`] — Severity: Nit

* **Affected Scope:** Line 187 and the contact role's audit display at line 189.
* **Discrepancy Description:** The plan says “role choice,” “name,” and `PhoneField` without specifying the agreed Arabic labels or role-value mapping.
* **Rationale:** This leaves UI copy and stored role translation to the implementer.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The concepts brief supplies the intended labels and values.
* ***Proposed Edit:*** Name the fields **مالك الوحدة**/**وسيط**, **اسم جهة التواصل**, and **رقم هاتف جهة التواصل**, map them to `owner`/`broker`, and label the link control **ربط بوسيط الطلب**.

### Issue 11 — History empty-state wording can imply nonexistent entries [`style`] — Severity: Nit

* **Affected Scope:** Line 188.
* **Discrepancy Description:** “In the history heading and empty state, along with the saved contact snapshot and inspection notes” can imply snapshots and notes appear when no inspection exists.
* **Rationale:** The two UI states need distinct descriptions.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** A small wording change removes the ambiguity.
* ***Proposed Edit:*** Specify that **فحص الشقة** labels the history heading and empty-state message; show the saved contact snapshot, notes, and historic defects only on an existing inspection entry.

### Issue 12 — Workflow update targets are not named symmetrically [`style`] — Severity: Nit

* **Affected Scope:** Line 193.
* **Discrepancy Description:** “Rental inspection steps and Resale unit/contact guidance” does not identify the matching workflow sections.
* **Rationale:** This may lead to uneven updates across the two departments.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** Both sections contain the workflow to be changed.
* ***Proposed Edit:*** Name Rental §2.4 and Resale §3.4 and require both to document the same contact snapshot and broker-link rule.

### Issue 13 — Duplicate broker-link rule should have one source of truth [`style`] — Severity: Nit

* **Affected Scope:** Lines 71 and 98.
* **Discrepancy Description:** Eligibility and validation for linking the request broker are repeated in two sections.
* **Rationale:** Repeated rules can drift as the design changes.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** One authoritative statement with a cross-reference is clearer.
* ***Proposed Edit:*** Keep the full eligibility and validation rule in §Shared inspection flow and make §Broker link persistence refer back to it.

### Issue 14 — Open Questions contains an unsourced drafting note [`style`] — Severity: Nit

* **Affected Scope:** Line 41.
* **Discrepancy Description:** “Confirmed during drafting” is vague after “None” and has no decision-record reference.
* **Rationale:** It does not identify an unresolved question or a checkable source.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The user-approved decisions are already summarized in the design; the note can be removed or tied to the decision record.
* ***Proposed Edit:*** Replace the two sentences with `None.` or cite the final decision records that establish each confirmed choice.

### Issue 15 — Additional Resources heading level is inconsistent [`structural`] — Severity: Nit

* **Affected Scope:** Line 162.
* **Discrepancy Description:** `### Additional Resources` appears to be a peer section after Non-Functional Requirements but is nested as an H3.
* **Rationale:** The heading hierarchy should match the document outline.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** This is a contained structural correction if the section is intended as a peer.
* ***Proposed Edit:*** Change the heading to `## Additional Resources` if it is a peer section.

### Issue 16 — Broad deletion statement repeats the precise retention rule [`style`] — Severity: Nit

* **Affected Scope:** Lines 183 and 200.
* **Discrepancy Description:** “No source file or database column will be deleted” is broad and the following defects-column sentence repeats the preservation instruction.
* **Rationale:** The relevant guarantee is specifically to retain existing defect history and contact snapshots.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** A narrower statement is more accurate and removes duplication.
* ***Proposed Edit:*** Remove the broad sentence at line 200 and retain a precise statement that the `observed_defects` column and existing values remain as legacy history.

## Findings Not Carried Forward

* **Workload/scalability assumption:** Stage 8 found the current concepts brief and request-history model sufficient for this feature; no extra scale-specific mechanism is proposed.
* **Alternatives traceability:** Stage 8 found concrete scope/history trade-offs in the alternatives, so the Stage 7 traceability concern does not require a change.
* **Architecture reuse:** Stage 8 found the shared action/service and department-specific tables appropriate; no central entity or new abstraction is required.

## Resolution Summary

All 16 proposed edits are reflected in `apartment-inspection-contact-capture.md`. The target document now defines the inspection-only decision exception, deployment and rollback behavior, migration retention test, form/service input mapping, audit contract, ordered implementation checkpoints, defect-input rejection and acceptance-test updates, known test baseline, broker identity recheck, Arabic labels, history wording, workflow sections, and editorial cleanups.

The target was re-read after editing. Its Markdown heading hierarchy and fenced Mermaid block remain intact. No tests were run because this resolution changed design documentation only.
