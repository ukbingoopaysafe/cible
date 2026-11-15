# Consolidated EGM Review Report

* **Target**: [lead-source-logic.md](./lead-source-logic.md)
* **Preset**: Consolidated report with all review rubrics
* **Review stages**: 5 Initial Zoom Out, 6 Comprehension, 7 Critic Review, 8 Intermediate Zoom Out, 9 Implementation Readiness
* **Review status**: All requested reviewers completed. One Stage 9 claim about Laravel's nullable boolean cast was checked against the installed framework source and excluded as inaccurate; `castAttribute()` returns null before the boolean cast for SQL NULL values.
* **Code/tests**: Reviewers were read-only; no project code was changed and no tests were run.

## Prioritized Findings

### 🔴 Blocker

None.

### 🟡 Major

#### R1 — Prevent legacy contact and unclassified types from being selected on new or changed requests

**Evidence:** Stage 7 `design_critic-1-1`; Stage 8 `zoom_out-2-1`. The design says legacy contact methods and unclassified rows are unavailable to new requests, but the form rule admits active rows, managers can reactivate inactive rows, and the service plan does not explicitly reject these types. A reactivated phone, visit, or WhatsApp option could restore the original mixed meaning.

**Action:** Define selectable types as `awareness`, `person_referral`, and `other`; enforce that allowlist in `RequestService` whenever a source is newly selected or changed. Preserve an unchanged historical legacy source on edits. Keep `legacy_contact_method` inactive and non-reactivatable; an `unclassified_legacy` row must first be classified and then separately activated.

#### R2 — Record the new source rule in the governing project decisions and acceptance criteria

**Evidence:** Stage 5 `zoom_out-1-1`; Stage 8 `zoom_out-2-2`. The design removes calls, visits, and WhatsApp from new awareness choices, while `D-051` and `AT-158` still describe the old approved list.

**Action:** Add `docs/DECISIONS.md` and `docs/ACCEPTANCE_TESTS.md` to the implementation target list. Supersede `D-051` with the awareness-only interpretation and update `AT-158` for typed sources, person-name-or-unknown behavior, «أخرى», historical values, and department isolation.

#### R3 — Specify exact migration mappings and preservation rules for every seeded value

**Evidence:** Stage 6 `comprehension-1-1`; Stage 9 `implementation_readiness-1-1` and `codebase_details-1-1`. The detailed plan says to classify known rows but does not give a complete value-to-type mapping or specify which active states to preserve. The seed migration has eight named values.

**Action:** In the migration plan, map «أخرى» (identified by its existing built-in marker) to `other`; both referral labels to `person_referral`; social media, electronic ad, and website to `awareness`; and phone call, direct visit, and WhatsApp to inactive `legacy_contact_method`. Preserve the existing active state for the other known rows. Preserve source IDs and request links. Set unrecognized manager-added rows to inactive `unclassified_legacy`, without guessing from their names.

#### R4 — Define exact validation for changed referral details and historical nulls

**Evidence:** Stage 6 `comprehension-1-3`; Stage 9 `implementation_readiness-1-2`. The form says a historical referral with no captured name can be preserved unless its details are updated, but the service plan only requires referral data on create or source change. Existing request updates merge submitted values with stored state.

**Action:** Preserve a historical request whose two referral fields are null during unrelated edits. For a new request, a new/changed selection of a `person_referral` source, or a submitted change to referral details, require exactly one of a non-empty referrer name or `lead_source_referrer_unknown = true`; reject both together. Specify UI behavior when toggling name versus unknown and test unrelated edits, neither value, and both values.

#### R5 — Add the audit UI mapping for source classification events

**Evidence:** Stage 9 `codebase_details-1-2`. The detailed plan adds an audited classification event but omits `app/Filament/Support/AuditEventResource.php`, whose current labels cover source creation and activation only.

**Action:** Add `AuditEventResource.php` to the implementation file list, map the classification event to an Arabic label, and assert it in catalog UI coverage.

### 🟢 Nit

#### R6 — Give `other` one meaning and one source of truth

**Evidence:** Stage 5 `zoom_out-1-2` and `zoom_out-1-3`; Stage 6 `comprehension-1-2`; Stage 7 `style-1-1`; Stage 8 `zoom_out-2-3`. The design defines `other` as the built-in «أخرى» row but allows managers to create more `other` rows, and also retains `is_other` synchronized with `type`.

**Action:** Reserve `other` for the built-in row and allow managers to create only `awareness` and `person_referral` options. Make `type` authoritative for the description field. Leave `is_other` as an unused legacy column during this migration; do not synchronize or consult it in runtime behavior. A later schema cleanup can remove it separately.

#### R7 — Make source-change cleanup explicit and remove duplicated rollout prose

**Evidence:** Stage 7 `style-1-2` and `style-1-3`; Stage 9 `style-1-1` and `style-1-2`. The implementation plan varies its description of referral requiredness, uses inconsistent labels for historical null state, does not list all cleared fields, and repeats the forward-only policy.

**Action:** Use “historical request with both referrer fields null” consistently. State that source changes clear `lead_source_referrer_name`, `lead_source_referrer_unknown`, and `lead_source_other` before validating the new type, then require only the field(s) associated with that type. Keep full rollback guidance in one Non-Functional Requirements section; retain only migration-specific mechanics in the migration entry.

#### R8 — State the scale assumption and fix the heading hierarchy

**Evidence:** Stage 7 `structural-1-1` and `structural-1-2`. Non-Functional Requirements does not say whether catalog growth creates a concern, and “Additional Resources” is nested as an H3 beneath it.

**Action:** State that each department catalog is an admin-managed reference list expected to remain small, that requests only query their department's catalog, and that the shared form loads selectable rows through the existing search interface. Promote “Additional Resources” to an H2 peer section.

#### R9 — Assign conditional form and request-detail assertions to a test file

**Evidence:** Stage 9 `codebase_details-1-3`. The verification plan requires form visibility and detail-display coverage, but the listed UI test changes focus on catalog actions and authorization.

**Action:** Name the existing request UI feature test that will cover the conditional fields and request detail states, or add a dedicated test file to the target list.

## Traceability Matrix

| Consolidated action | Raw reviewer findings | Stage |
| --- | --- | --- |
| R1 | `design_critic-1-1`, `zoom_out-2-1` | 7, 8 |
| R2 | `zoom_out-1-1`, `zoom_out-2-2` | 5, 8 |
| R3 | `comprehension-1-1`, `implementation_readiness-1-1`, `codebase_details-1-1` | 6, 9 |
| R4 | `comprehension-1-3`, `implementation_readiness-1-2` | 6, 9 |
| R5 | `codebase_details-1-2` | 9 |
| R6 | `zoom_out-1-2`, `zoom_out-1-3`, `comprehension-1-2`, `style-1-1`, `zoom_out-2-3` | 5, 6, 7, 8 |
| R7 | `style-1-2`, `style-1-3`, `style-1-1`, `style-1-2` | 7, 9 |
| R8 | `structural-1-1`, `structural-1-2` | 7 |
| R9 | `codebase_details-1-3` | 9 |

## Stage Artifacts

* [Stage 5 — Initial Zoom Out](./zoom_out_report.md)
* [Stage 6 — Comprehension](./comprehension_report.md)
* [Stage 7 — Critic Review](./critic_report.md)
* [Stage 8 — Intermediate Zoom Out](./intermediate_zoom_out_report.md)
* [Stage 9 — Implementation Readiness](./implementation_readiness_report.md)

## Contested Claim Excluded from Action Items

`codebase_details` claimed that Laravel's built-in nullable boolean cast turns SQL `NULL` into `false`. The claim was checked against the installed framework: `HasAttributes::castAttribute()` returns null for a null primitive-cast value before the boolean conversion branch (`vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/HasAttributes.php:836–837, 861–863`). The claim is recorded as `CONTEST` in `goldfish_review_report.md`; no custom cast is proposed.

## Resolution

The author chose **auto accept**. All nine proposed design-document fixes were applied to `lead-source-logic.md`; the inaccurate cast claim remains contested. At the end of Round 1, project code and `docs/DECISIONS.md` / `docs/ACCEPTANCE_TESTS.md` had not been changed, and updating those governing documents was listed in the implementation plan. The author later authorized Round 2; its findings and resolution are recorded separately in [the Round 2 consolidated report](./consolidated_review_report_round2.md).
