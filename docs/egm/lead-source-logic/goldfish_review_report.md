# Goldfish Annotated Review Report

**Status: resolved**

**Target:** `docs/egm/lead-source-logic/lead-source-logic.md` (full file, lines 1–276)

**Resolution:** The author chose auto accept. All nine `PROPOSE FIX` suggestions below were applied to the target design document. The one `CONTEST` item remains contested and was not applied. No application code, decision-log entry, or acceptance-test file was changed; those documentation updates are listed as implementation targets in the design plan.

**Counts:** 9 accepted and applied; 1 contested; 0 escalated; 0 declined; 0 remaining proposed fixes.

## Prioritized Findings & AI Suggestions

### R1 — Legacy source types can re-enter new request choices [`design_critic`, `zoom_out`] — Severity: Major

* **Affected Scope:** Lines 81–84, 223, 228 (`lead-source-logic.md`)
* **Discrepancy Description:** The design excludes legacy contact methods and unclassified legacy rows from new choices, but the detailed form plan admits active rows and managers can reactivate inactive rows. The service plan does not explicitly reject legacy types on new or changed selections.
* **Rationale:** Reactivation could make phone, visit, WhatsApp, or unclassified values available for new requests, restoring the mixed meaning the design aims to remove.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The feature's central invariant must be enforced by server-side validation, independent of catalog active state. Existing requests still need to retain an unchanged legacy value.
* ***Proposed Edit:***
  * Location: Detailed Implementation entries for `LeadSourceResource`, `LeadSourceService`, `RequestFormSchema`, and `RequestService`.
  * Change: State that only active `awareness`, `person_referral`, and the built-in `other` are eligible for new or changed source selections; enforce both type and active-state rules in `RequestService`. Keep `legacy_contact_method` inactive and non-reactivatable. An `unclassified_legacy` row must be classified before it can be activated. Preserve the exact current selection, including an inactive historical value, while editing other request details.

### R2 — Governing source decision and acceptance test remain stale [`zoom_out`] — Severity: Major

* **Affected Scope:** Lines 190–253, target file list and implementation plan
* **Discrepancy Description:** `D-051` still approves phone call, direct visit, and WhatsApp as initial source values, and `AT-158` still tests the old catalog.
* **Rationale:** The repository's governing policy and acceptance contract would conflict with the feature after implementation.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The design should make the approved policy update part of the implementation handoff.
* ***Proposed Edit:***
  * Location: `Target Files to Create/Modify` and `Verification Plan`.
  * Change: Add `docs/DECISIONS.md` (Modify) to record a superseding decision for `D-051`, explaining that the field records awareness rather than contact method. Add `docs/ACCEPTANCE_TESTS.md` (Modify) to update `AT-158` for typed sources, referral name-or-unknown behavior, «أخرى», legacy preservation, and department isolation.

### R3 — Migration mapping and active-state preservation are underspecified [`comprehension`, `implementation_readiness`, `codebase_details`] — Severity: Major

* **Affected Scope:** Lines 193, 253 (`lead-source-logic.md`)
* **Discrepancy Description:** The detailed migration entry says to classify known rows without enumerating the label-to-type mappings or specifying active-state preservation. The existing seed migration lists the concrete default choices.
* **Rationale:** The implementer cannot safely classify historical rows or know whether to preserve a seeded row's current availability.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The exact mapping is deterministic for built-in rows and must be explicit before altering historical catalog behavior.
* ***Proposed Edit:***
  * Location: Migration file entry in Detailed Implementation.
  * Change: Specify: the built-in row currently marked `is_other = true` → `other`; «إحالة من موظف» and «إحالة من عميل أو معرفة» → `person_referral`; «وسائل التواصل الاجتماعي», «إعلان إلكتروني», «الموقع الإلكتروني» → `awareness`; «مكالمة هاتفية», «زيارة مباشرة», «واتساب» → inactive `legacy_contact_method`; newly seeded «الإدارة العليا» → active `person_referral`. Preserve existing active state for the other known rows. Preserve IDs and foreign keys. Any unrecognized manager-added row → inactive `unclassified_legacy` without label-based guesses.

### R4 — Referral detail updates need a precise one-of validation rule [`comprehension`, `implementation_readiness`] — Severity: Major

* **Affected Scope:** Lines 223, 228, 238 (`lead-source-logic.md`)
* **Discrepancy Description:** The form says historical null referral values remain until details are updated, but the service rule does not define validation when referral details change on the same source. Partial request updates merge submitted and stored values.
* **Rationale:** It is unclear when to preserve both-null legacy state and when to require new referral information; the UI can also submit contradictory name and unknown values.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** A single explicit transition rule prevents losing history while ensuring new referral data is deliberate.
* ***Proposed Edit:***
  * Location: `RequestFormSchema`, `RequestService`, and their feature-test entries.
  * Change: Consistently call the legacy case “a historical request with both referrer fields null.” Preserve it on unrelated edits. For a new request, a new or changed `person_referral` selection, or an explicit change to referral details, require exactly one of a non-empty name or `lead_source_referrer_unknown = true`; reject both together. Selecting unknown clears the name; entering a name clears unknown. Add tests for unrelated edits preserving null, neither value on referral change, both values, name only, and unknown only.

### R5 — Classification audit event is missing its UI label target [`codebase_details`] — Severity: Major

* **Affected Scope:** Lines 215–218, 243 (`lead-source-logic.md`)
* **Discrepancy Description:** The plan introduces an audited legacy-source classification event but omits `app/Filament/Support/AuditEventResource.php`, which maps the existing source creation and activation event labels.
* **Rationale:** The new audit event would not receive a human-readable label in the existing audit interface.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** This file is a direct runtime dependency of the requested audited classification behavior.
* ***Proposed Edit:***
  * Location: `Target Files to Create/Modify` and catalog UI verification.
  * Change: Add `app/Filament/Support/AuditEventResource.php` (Modify) to map a new `lead_source.classified` event key to an Arabic label in both department-scoped event maps. Assert the label in `FilamentUiAccessTest.php`.

### R6 — `other` has duplicate state and an undefined manager workflow [`zoom_out`, `comprehension`, `style`] — Severity: Nit

* **Affected Scope:** Lines 76–84, 193, 198, 218 (`lead-source-logic.md`)
* **Discrepancy Description:** The type table defines `other` as the built-in «أخرى» source, yet managers may select `other` for new source labels. The plan also synchronizes `is_other` with `type`.
* **Rationale:** Additional `other` rows have no defined description behavior, and two stored fields can disagree about whether the description is required.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** A dedicated type already identifies the built-in description behavior, so another mutable flag is not needed at runtime.
* ***Proposed Edit:***
  * Location: Source type table and `LeadSourceResource`, `LeadSourceService`, and migration entries.
  * Change: Reserve `other` for the existing built-in «أخرى» row; let managers create only `awareness` and `person_referral` options. Keep the built-in row active and protected from deactivation. Use `type` as the sole runtime source of description behavior. Identify the built-in migration row using its existing `is_other` marker, then leave that legacy column unchanged and unused rather than synchronizing it.

### R7 — Referral wording, field cleanup, and rollback prose need consolidation [`style`] — Severity: Nit

* **Affected Scope:** Lines 115, 132, 223, 228
* **Discrepancy Description:** Referral requiredness is described with varying phrases, historical null states have inconsistent names, cleanup refers vaguely to “all source-dependent details,” and forward-only guidance is repeated.
* **Rationale:** An implementer could apply different rules across the UI, service, and migration.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** Consistent names and an explicit field list reduce ambiguity without changing behavior.
* ***Proposed Edit:***
  * Location: Request form and service entries; migration and rollout entries.
  * Change: Use “new or changed selection of a `person_referral` source” and “historical request with both referrer fields null” throughout. On source changes, explicitly clear `lead_source_referrer_name`, `lead_source_referrer_unknown`, and `lead_source_other` before validating the new type. Keep the full rollback policy in Non-Functional Requirements and only migration mechanics in the migration entry.

### R8 — Non-functional scope and heading hierarchy need one clarification [`structural`] — Severity: Nit

* **Affected Scope:** Lines 174–182
* **Discrepancy Description:** The non-functional section does not state whether catalog scale creates a material concern, and “Additional Resources” is nested as an H3 beneath it.
* **Rationale:** The scale assumption and document hierarchy are unclear to reviewers and implementers.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** A short bounded-load assumption is sufficient; no new scaling system is needed for these department-specific reference lists.
* ***Proposed Edit:***
  * Location: Non-Functional Requirements and Additional Resources.
  * Change: State that each department catalog is a small manager-maintained reference list queried within its department through the existing searchable selection; no separate scale work is required for this scope. Promote “Additional Resources” to a peer H2 section.

### R9 — Assign request-form presentation tests to a specific file [`codebase_details`] — Severity: Nit

* **Affected Scope:** Lines 240–253
* **Discrepancy Description:** The plan promises tests for conditional field visibility and request-detail display but its listed UI test file only names catalog and authorization cases.
* **Rationale:** The form and detail behavior could be implemented without an explicit presentation regression test.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** Conditional display and saved-state presentation are central acceptance behavior.
* ***Proposed Edit:***
  * Location: Target files and Verification Plan.
  * Change: Add `tests/Feature/LeadSourceRequestFormTest.php` (Create) to verify type-driven field visibility, requiredness, source-change clearing, and detail display for a name, explicit unknown, and historical null state.

### C1 — Laravel nullable boolean cast claim is inaccurate [`codebase_details`] — Severity: Major

* **Affected Scope:** Lines 205–208, `lead-source-logic.md`
* **Discrepancy Description:** The reviewer claimed that a Laravel boolean cast turns SQL `NULL` into `false`, erasing the historical null state.
* **Rationale:** The installed framework's `HasAttributes::castAttribute()` returns a null primitive-cast value before it reaches the boolean branch (`vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/HasAttributes.php:836–837, 861–863`). A nullable boolean cast preserves null in this version.
* ***AI Suggestion:*** CONTEST
* ***Justification:*** The installed framework code directly contradicts the claim and was available for inspection. Do not add an unnecessary custom cast. Keep a regression test for null serialization/display because the distinction is part of the data model.
* ***Proposed Edit:***
  * Location: No design change required.
  * Change: None; retain nullable boolean cast and the historical-null tests.
