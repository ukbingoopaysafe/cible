# Stage 9 — Implementation Readiness Test

* **Target**: `lead-source-logic.md`
* **Range**: DetailedRange, lines 184–260
* **Reviewers**: `implementation_readiness`, `codebase_details`, and `style` completed.
* **Execution**: Read-only review; no tests were run.

## Findings

### Major

#### `implementation_readiness-1-1` / `codebase_details-1-1` — Migration instructions omit the exact source mapping and active-state rules

The migration entry says to classify known seeded rows but does not repeat the label-to-type mapping or state what happens to existing `is_active` values (`lead-source-logic.md:193, 253`). The seed migration lists the eight standard values (`database/migrations/2026_10_24_160000_seed_section_lead_sources.php:10–18`). The migration plan must specify the type, active state, and «أخرى» handling for each known row, then define the inactive `unclassified_legacy` fallback for unknown manager-added rows.

#### `implementation_readiness-1-2` — Updates to referral details on an unchanged source have no exact validation rule

The form preserves a historical referral with null name and unknown state unless its details are updated (`lead-source-logic.md:223`). The service rule requires a name or explicit unknown for a new request or source change, but does not define what happens when the user edits referral details without changing the source (`:228, 238`). `RequestService` merges stored and submitted values during partial updates (`app/Services/RequestService.php:264–276`), so validation must distinguish unchanged null history from explicitly submitted changes.

**Recommended remedy**: Preserve both null referral fields during unrelated edits. If referral details are submitted or the source is newly selected/changed to `person_referral`, require exactly one of a non-empty name and `lead_source_referrer_unknown = true`; reject both together. Add tests for unrelated edits, neither value, and both values.

#### `codebase_details-1-2` — Classification audit event needs an `AuditEventResource` label

The plan adds an audited source-classification action (`lead-source-logic.md:218`), but omits `app/Filament/Support/AuditEventResource.php` from the target file list. Its current maps cover `lead_source.created` and `lead_source.activation_changed`, not the new classification event (`app/Filament/Support/AuditEventResource.php:105–106, 154–155`).

**Recommended remedy**: Add `AuditEventResource.php` to the modification list, map the new event key to an Arabic label, and assert the label in UI coverage.

### Nits

#### `style-1-1` — Historical referral states use inconsistent wording

The plan alternates between “old” and “older” records and between “name and unknown state were never captured” and “null name/unknown state” (`lead-source-logic.md:223, 228`).

**Recommended remedy**: Use one phrase throughout, such as “historical request with both referrer fields null.”

#### `style-1-2` — Source-change cleanup does not name the fields

The service plan says to clear “all source-dependent details” without listing the affected fields (`lead-source-logic.md:228`).

**Recommended remedy**: Name `lead_source_referrer_name`, `lead_source_referrer_unknown`, and `lead_source_other`, with clearing rules for each type transition.

#### `codebase_details-1-3` — Request-form presentation tests are not assigned to a test target

The verification plan expects conditional form visibility and detail display to be tested, but the listed `FilamentUiAccessTest.php` changes focus on source-catalog creation/classification and authorization (`lead-source-logic.md:243, 253`).

**Recommended remedy**: Assign the form visibility and detail-display assertions to `FilamentUiAccessTest.php` or name a separate request-form feature test.

## Contested Reviewer Claim

`codebase_details` reported that Laravel's built-in boolean cast converts SQL `NULL` to `false`, which would erase the distinction between “not recorded under the old workflow” and an explicit unknown choice. This claim is not upheld: the project's `HasAttributes::castAttribute()` returns null at lines 836–837 for null primitive-cast values before reaching the boolean branch at lines 861–863. No custom cast is required for null preservation on this framework version.
