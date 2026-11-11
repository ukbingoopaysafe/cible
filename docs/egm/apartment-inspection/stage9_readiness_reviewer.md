# Stage 9 Implementation Readiness Review

**Target:** apartment-inspection-contact-capture.md, lines 175–215  
**Review mode:** Implementation readiness  
**Result:** Not ready to execute without design decisions. The target file boundary and most feature behavior are clear, but sequencing, migration operations, audit contract, and service rejection behavior need decisions.

## Findings

### 1. [Major] Add implementation phases and checkpoints

**Affected scope:** Target lines 179–209, especially the flat file list and verification plan.

The plan lists all touched files, then a final verification list, but does not divide work into ordered phases or define a stable checkpoint for each. The readiness standard requires incremental phases that remain buildable and testable. This leaves the implementer to decide how to order the schema, service, UI, tests, and documentation changes, and when each slice is ready.

**Recommendation:** Add ordered phases with a specific checkpoint per phase. For example: additive schema and model relations; service validation and service tests; shared form/history/audit UI and UI tests; documentation and acceptance updates; final verification and CURRENT_STATE entry. State which tests/checks gate each phase.

### 2. [Major] Define migration rollout and application rollback behavior

**Affected scope:** Target line 183 and lines 202–209.

The concepts brief requires an additive migration that lets the prior application version continue reading and writing during rollout, and requires that rollback not discard snapshots. The target makes the columns nullable and says migration down() must refuse to drop them, but does not state the deployment order or what operators should do when rolling back the application. A down() that refuses to run also makes an ordinary migration rollback fail by design.

**Recommendation:** State that the migration must be applied before deploying code that reads or writes the new fields; the prior application may continue with the nullable columns present; and an application rollback must leave the expanded schema in place. Document the expected down() failure and the recovery path so it is not mistaken for a partial rollback.

### 3. [Major] Test the forward-only migration guarantee

**Affected scope:** Target lines 183 and 202–209; the test-file allowlist at lines 195–197.

The plan requires down() to refuse to drop the new fields and asks for a manual rollback confirmation, but it does not include an automated migration test. This is a data-retention invariant for both inspection tables, and the listed workflow/UI tests do not directly establish it.

**Recommendation:** Add a migration test to the file boundary. For both departments, create an inspection with contact snapshots, invoke the migration rollback path, assert that it is refused, and verify the columns and stored values remain. Also assert that the additive migration leaves existing observed_defects values untouched.

### 4. [Major] Specify whether legacy defects input is rejected

**Affected scope:** Target line 186 and test scope at lines 194–195.

The concepts brief says the service must stop accepting observed_defects. The target says to remove it from validation and persistence. With the current Validator::make(...)->validate() pattern in RecommendationService.php, removing the observed_defects rule omits that key from the validated result but does not reject a caller that still submits it; the value is silently ignored. The target therefore does not establish the stronger “stop accepting” behavior.

**Recommendation:** Explicitly prohibit observed_defects in service input (or state that silent ignore is intentional, if that is the decision) and add an assertion for that behavior. The form-only test that the new defects control is absent does not cover a stale or forged service payload.

### 5. [Major] Define the audit metadata keys and allowed values

**Affected scope:** Target lines 186, 189, and 208.

The plan says to record “role and link metadata,” but does not specify the metadata keys or whether the linked broker ID is included. The concepts brief permits recording the contact role, whether a broker link was used, and the linked broker ID; it forbids contact name, phone, and notes. AuditEventResource also needs exact keys to label the values. Choosing the payload shape and privacy boundary is left to the implementer.

**Recommendation:** Name the exact permitted keys and value encodings in the plan, including whether linked_broker_id is null or absent when unlinked. Add Arabic labels for each chosen key and test both linked and unlinked events for the exact allowed metadata and absence of personal contact values.

### 6. [Major] Account for the known FilamentUiAccessTest baseline failures

**Affected scope:** Target lines 196 and 203–206.

CURRENT_STATE.md records that the last full test run had 18 failures, including two FilamentUiAccessTest cases involving broker entry. The verification plan runs that whole test file but does not distinguish those known failures from regressions in the new inspection workflow, so the planned pass/fail criterion is not currently usable as written.

**Recommendation:** Record the known baseline and identify the exact inspection test method(s) that must pass, or explicitly include resolution of the known failures in scope before requiring the whole file to pass. Do not mark the file or suite as passing if those failures remain.

### 7. [Nit] Carry the approved Arabic field labels into the form steps

**Affected scope:** Target line 187.

The concepts brief already defines the exact role labels (مالك الوحدة / وسيط), contact name and phone labels (اسم جهة التواصل / رقم هاتف جهة التواصل), and link label (ربط بوسيط الطلب). The implementation plan only says “role choice,” “name,” “PhoneField,” and “link control,” leaving UI copy and stored role-to-label mapping implicit.

**Recommendation:** Include those exact labels and the owner/broker value mapping in the form step.

## File access and read status

All supplied paths were accessible. I read the requested target range only; for context files, I read the relevant sections and code paths listed below. I did not edit the target or run tests.

| File | Access/read status |
| --- | --- |
| C:/Users/gensp/.codex/plugins/cache/egm/egm/0.1.1/agents/implementation_readiness.md | Read fully |
| C:/Users/gensp/.codex/plugins/cache/egm/egm/0.1.1/resources/document_standards/implementation-readiness-standard.md | Read fully |
| docs/egm/apartment-inspection/apartment-inspection-contact-capture.md | Read only lines 175–215 |
| docs/egm/apartment-inspection/concepts_brief.md | Read fully |
| docs/DECISIONS.md | Read D-060, D-061, and relevant Q-005/Q-008 entries |
| docs/PRD.md | Read PRD-042 |
| docs/DATA_MODEL.md | Read section 4.5 |
| docs/WORKFLOWS.md | Read sections 3.3–3.4 |
| docs/PERMISSIONS.md | Read inspection permission rows and backend-scope rules |
| docs/ACCEPTANCE_TESTS.md | Read AT-160–AT-162 |
| docs/CURRENT_STATE.md | Read inspection verification history and latest test status |
| app/Services/RecommendationService.php | Read recordInspection() and adjacent validation flow |
| app/Models/RentalFieldInspection.php | Read fully |
| app/Models/ResaleFieldInspection.php | Read fully |
| app/Filament/Support/SectionRequestResource.php | Read recordFieldInspectionAction() |
| app/Filament/Support/AuditEventResource.php | Read inspection event/subject labels and metadata formatting |
| resources/views/filament/modals/request-property-history.blade.php | Read fully |
| database/migrations/2026_10_24_230000_create_section_recommendation_viewing_tables.php | Read fully |
| tests/Feature/RecommendationWorkflowTest.php | Read existing inspection workflow |
| tests/Feature/FilamentUiAccessTest.php | Read existing Rental inspection workflow |
| tests/Feature/RequestActionsMenuStructureTest.php | Read action key/menu assertions |

