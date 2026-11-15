# Lead Source and Customer Awareness Attribution

* **Author:** ukbingoopaysafe
* **Status:** Approved after the five-round review cap; implementation and automated verification complete on `main`; same-engine/version production migration rehearsal remains required before rollout.
* **Date:** 2026-11-12

## Overview

Cible stores one optional source per Rental or Resale request: the main source the client names when asked how they learned about Cible. If several channels contributed, staff ask the client to choose one.

Phone calls, direct visits, and WhatsApp describe contact methods, so they will not be offered as new source choices. This first change will not add a second field for first contact. Person-based referrals, including a separate «الإدارة العليا» choice, will ask for the referrer's name or an explicit «الاسم غير معروف» selection. «أخرى» will keep its own source-description field.

The working proposal keeps the source on each request. If a request is created through a broker before the customer's source is known, leave the source unset until it is known; do not infer the source from the linked property broker. Apply the same behavior in Rental and Resale while preserving their separate source catalogs.

## The Problem

The existing source field can contain values such as «مكالمة هاتفية», «زيارة مباشرة», «إحالة من موظف», and «إعلان إلكتروني». A call or visit says how contact occurred, but not how the client learned about Cible. Reporting or reviewing these values as one kind of source therefore mixes different facts.

The current form shows an extra description only for a source marked «أخرى». It has no separate field for the person behind a referral, and manager-added source options do not carry a stable type that can decide which extra field to show. Adding «الإدارة العليا» as a label alone would not make its name-entry behavior reliable.

The system needs source types to control conditional fields, preserve historical values, and leave an unknown source unset. Map the catalog rows for calls, visits, and WhatsApp to inactive `legacy_contact_method`, preserving existing request selections.

## Goals

* Make the source field record the main source the client identifies for how they learned about Cible, separately from contact methods.
* Stop offering phone calls, direct visits, and WhatsApp as new source choices while preserving historical request values.
* Add «الإدارة العليا» as a distinct source choice and require either the referrer's name or an explicit «الاسم غير معروف» selection for new person-based referrals.
* Keep the required source description for «أخرى» independent from the referrer-name field.
* Leave the source unset when it is not known; never infer it from a phone call, visit, or linked property broker.
* Apply the same source behavior in Rental and Resale while retaining the separate catalogs and permissions already used by each department.
* Use stable source classifications to drive conditional fields, including for options managers add later.

## Non-Goals

* Adding a separate first-contact field in this change.
* Building an attribution dashboard or multi-touch marketing analytics.
* Inferring a source from activity history, a contact method, or a linked broker.
* Rewriting historical source values or backfilling unknown sources by guesswork.

## Open Questions

* Whether future reporting needs an explicit «مصدر غير معروف» awareness value distinct from a source that was not asked or recorded. Until that need is approved, keep `lead_source_id` nullable and leave it null when the source is not known or not captured.

## Critical User Journeys (CUJs)

### CUJ 1: Manager adds or classifies a department source

* **Given**: A manager authorized for Rental or Resale is viewing that department's source catalog.
* **When**: The manager adds a source and chooses its stable type, or classifies an inactive unclassified legacy source.
* **Then**: The source is typed and audited in that department only. A newly added source is available to requests in the same department; a classified legacy source remains inactive until separately activated.

### CUJ 2: Staff records an awareness source or personal referral

* **Given**: Staff are creating or editing a Rental or Resale request and know how the client learned about Cible.
* **When**: Staff ask which source the client identifies as the main source and select it; for a person referral, they enter the person's name or explicitly select «الاسم غير معروف».
* **Then**: The request stores the selected source and applicable referral snapshot. The referrer is not added to a customer, broker, or property directory; exports show a captured name or the explicit unknown state, while audit history does not copy the name.

### CUJ 3: Staff creates a broker-first request before learning the customer's source

* **Given**: A request is entered through a broker before the eventual customer or the customer's awareness source is known.
* **When**: Staff save the request without a source and later learn the source.
* **Then**: The source remains null until it is known, and the linked property broker is never inferred as the person who referred the customer. Staff can later update the request with the client-identified source.

### CUJ 4: Staff records «أخرى»

* **Given**: No active typed source describes how the client learned about Cible.
* **When**: Staff select «أخرى» and provide its description.
* **Then**: The required description is saved independently of any referral-name data.

## Technical Plan

### Source Catalog Types

Add a stable type to each source option in both department-specific catalogs. The type, rather than the Arabic display label, determines how the request form behaves:

| Type | Meaning | Request form behavior |
| --- | --- | --- |
| `awareness` | A source that explains how the client learned about Cible, such as an advertisement, social content, or the website. | No additional field. |
| `person_referral` | A person introduced Cible to the client, including an employee, a customer or acquaintance, or «الإدارة العليا». | Show the person's name field and an explicit «الاسم غير معروف» choice. A new or changed selection of this type requires exactly one of them. |
| `other` | The single built-in «أخرى» source. Managers cannot add other sources with this type. | Show and require the existing source-description field. |
| `legacy_contact_method` | A historical choice such as phone call, direct visit, or WhatsApp that describes contact method rather than awareness. | Keep displaying an unchanged selection on historical requests. It remains inactive and cannot be reactivated or newly assigned. |
| `unclassified_legacy` | A manager-added historical source whose meaning cannot safely be inferred from its label. | Preserve it on historical requests and exclude it from new or changed selections. A manager must classify it before it can be activated. |

Managers continue to add source labels within their own department's catalog. They may select only `awareness` or `person_referral`; `other` is reserved for the existing built-in «أخرى» row. The two catalogs remain separate, while the same type meanings and validation rules apply to both. The legacy types are reserved for historical data and cannot be selected for new values.

### Request Data and Form Flow

Keep one optional `lead_source_id` on each request. It represents the main source the client identifies when staff ask. If several channels contributed, staff ask the client to identify the main one rather than guessing first or most influential. Add nullable `lead_source_referrer_name` and `lead_source_referrer_unknown` fields to each request table. Retain `lead_source_other` for the «أخرى» description.

Require exactly one of a name or explicit unknown when creating a request with a `person_referral` source, changing a request's source to `person_referral`, or explicitly changing referral details for that source. If both referrer fields are null on a historical request, preserve that state during unrelated edits.

Show the name and unknown choice only for `person_referral`, and require the existing description only for `other`. When staff change the selected source, clear the old dependent form values immediately so they can enter details for the new type. On submission, discard stored values belonging to the previous source, keep submitted values valid for the new type, clear incompatible values, and then validate.

The shared `RequestFormSchema` reads selectable options from the selected department's catalog and uses each option's type to control visibility. It offers only active `awareness`, `person_referral`, and built-in `other` values for new requests. On edit, it retains the request's currently selected inactive value for display; it never offers an inactive or legacy type for a new or changed selection.

`RequestService` enforces eligibility and active state on the server for new or changed selections. It permits the exact currently stored source to remain unchanged, including a historical inactive legacy source, while unrelated request fields are edited. Rental and Resale keep their separate model and foreign-key boundaries.

```text
Manager adds a source label
        │
        └── selects awareness or person_referral in the department catalog
                         │
                         ▼
Request form loads active, eligible sources for that department
        │
        ├── awareness ─────────────── no dependent field
        ├── person_referral ────────── referrer name OR explicit unknown choice
        └── other ──────────────────── required source-description field
                         │
                         ▼
RequestService enforces eligible types and validates dependent values
                         │
                         ▼
Request row stores source ID and the applicable referral or other details
```

### Migration and Historical Values

Add a tracked migration for source types and request-level referrer snapshots in both departments. Backfill known sources, set «أخرى» active, preserve unknown manager-added rows as inactive and unclassified, and keep historical referral fields null. Preserve source IDs, request links, and the legacy `is_other` value; enforce non-null `type` only after backfill. The exact mappings, assertions, and forward-only rollback behavior are specified in Detailed Implementation item 1.

Keep the migration as a tracked file for the owner to apply after pulling `main` on production. The rollback and application compatibility policy is defined under Non-Functional Requirements.

### Related Components

* `LeadSourceResource` and `LeadSourceService` add only `awareness` and `person_referral` types, classify legacy rows, and keep department authorization and audit behavior. The built-in `other` cannot be deactivated and `legacy_contact_method` cannot be reactivated; a classified `unclassified_legacy` row can be activated as a separate manager action.
* `RentalLeadSource` and `ResaleLeadSource` expose the stored type to the shared request form.
* `RequestFormSchema` shows the referrer name/unknown choice or source-description field according to type.
* `RequestService` validates type, source ID, eligibility, and dependent values on request creation and update. A new or changed selection of `person_referral`, or an explicit change to its referral details, requires exactly one of a name or explicit unknown choice. A historical request with both referrer fields null remains unchanged during unrelated edits. Broker-first requests stay source-null until the client's source is known; the linked property broker is not used as an inferred referrer.
* The Rental and Resale request exporters include the referrer name, displaying «غير معروف» for an explicit unknown choice. Request audit snapshots record that the name changed without copying the name into the audit payload.

## Alternatives Considered

The design assigns one stable type to each source option; the type controls form behavior, validation, and legacy-value migration.

### Keep One Mixed Source Field and Rename It

* **Pros**: Smallest UI and database change.
* **Cons**: The same field would still mix how a client learned about Cible with how the client contacted the company.
* **Reason for not selecting it**: Renaming the field would not answer the source question consistently or prevent contact methods from being recorded as awareness sources.

### Add a Separate First-Contact Field Now

* **Pros**: Could preserve both how the client learned about Cible and how the first inquiry reached the company.
* **Cons**: The business has not defined what counts as first contact; a request-level value could duplicate activity history and disagree with its manually logged events.
* **Status**: Deferred until an approved workflow or report requires this fact and defines how it is captured.

### Derive First Contact from the Earliest Logged Activity

* **Pros**: Avoids adding another request field.
* **Cons**: Contact activities are entered manually, and the earliest recorded event is not guaranteed to be the client's actual first contact.
* **Reason for not selecting it**: The current code does not establish a reliable first-contact rule from activity history.

### Configure Extra Field Actions Instead of a Source Type

* **Pros**: A manager could select a behavior such as “show referrer name” or “show other detail” for each source.
* **Cons**: It captures form behavior without identifying whether a value is an awareness source, a personal referral, or a legacy contact method. Independent action flags could also describe conflicting behaviors.
* **Reason for not selecting it**: Use one stable source type per option so the same classification governs the form, validation, and migration of old values.

### Use a Fixed Source List in Code

* **Pros**: Source types and field behavior would be controlled centrally and could not drift between manager-created values.
* **Cons**: Managers would lose the ability to add department-specific source labels without a code change and deployment.
* **Reason for not selecting it**: Typed manager-managed options preserve the existing ability to add and deactivate department-specific source values without code changes.

### Add an Explicit “Unknown” Awareness Source Value

* **Pros**: Can distinguish “asked but unknown awareness source” from a blank value that was not recorded.
* **Cons**: Adds an awareness category that could be confused with a known source and may distort counts if staff use it instead of leaving an uncaptured source blank.
* **Status**: Defer this until reporting needs require distinguishing an unknown source from one that was not recorded. The separate «الاسم غير معروف» choice applies only to a person-referral name and does not add an unknown awareness source.

## Non-Functional Requirements

* **Privacy and audit**: The referrer's name is stored only on the request and may appear in request exports. Request audit history records that the name changed but redacts the literal value.
* **Department isolation**: A source ID must resolve only through the request's Rental or Resale catalog; manager authorization and audit records remain department-scoped.
* **Scale**: The design assumes each department's manager-maintained catalog remains small. Requests query only their department's searchable catalog, so no separate scaling work is planned.
* **Rollout and rollback**:
  1. **Preflight:** Before scheduling the maintenance window, identify the production database engine and version. Rehearse this exact migration against a disposable copy of the pre-change schema using the same engine and version. Schedule the window only after the rehearsal succeeds, and record the engine/version and result with deployment evidence.
  2. **Deploy:** Enable Laravel maintenance mode with `php artisan down` before pulling the updated application. Pull `main`, apply the tracked migration with `php artisan migrate --force`, and run `php artisan up` only after it succeeds.
  3. **Migration failure:** Keep the application in maintenance mode and resolve the issue with a forward deployment before reopening service.
  4. **After migration:** The migration's `down()` method must stop with a clear forward-only message rather than remove source classifications or referral data. Retain the new schema and data; fix defects with a forward deployment.
  5. **Older application compatibility:** The older application cannot add sources with a required type and does not display referral snapshots.

## Additional Resources

* [Concepts brief](./concepts_brief.md)

## Detailed Implementation

Rental and Resale use the same rules through shared components while retaining separate catalogs, request tables, and foreign keys.

### Target Files to Create/Modify

#### 1. `database/migrations/2026_11_12_000000_add_typed_lead_sources_and_referrers.php` (Create)

* **Type / Scope**: Laravel schema and data migration.
* **Proposed Changes**:
  * Add a nullable 32-character `type` column to both lead-source tables and nullable `lead_source_referrer_name` (255 characters) and `lead_source_referrer_unknown` boolean fields to both request tables.
  * Backfill every catalog row before enforcing non-null `type`:
    * Map the built-in `is_other = true` row to `other` and force it active.
    * Map «إحالة من موظف» and «إحالة من عميل أو معرفة» to `person_referral`.
    * Map «وسائل التواصل الاجتماعي», «إعلان إلكتروني», and «الموقع الإلكتروني» to `awareness`.
    * Map «مكالمة هاتفية», «زيارة مباشرة», and «واتساب» to inactive `legacy_contact_method`.
    * Preserve existing active state for all other known rows. Add or update «الإدارة العليا» as active `person_referral`. Classify unknown manager-added rows as inactive `unclassified_legacy` without inferring their type from their labels.
  * Confirm no catalog row has a null type, then alter `type` to non-null.
  * Preserve catalog IDs and request foreign keys. Leave historical referral fields null. Leave `is_other` unchanged and unused by runtime behavior.
  * Keep this migration's `down()` behavior and production sequence as specified under Non-Functional Requirements.

#### 2. `app/Enums/LeadSourceType.php` (Create)

* **Type / Scope**: Shared source-type enum.
* **Proposed Changes**: Define `awareness`, `person_referral`, `other`, `legacy_contact_method`, and `unclassified_legacy`, with these exact Arabic catalog labels: `awareness` «مصدر تعرّف», `person_referral` «إحالة من شخص», `other` «أخرى», `legacy_contact_method` «وسيلة تواصل تاريخية», and `unclassified_legacy` «مصدر قديم غير مصنف». Keep `other` reserved for the built-in source and keep both legacy types unavailable for manager-created choices.

#### 3. `app/Models/RentalLeadSource.php` and `app/Models/ResaleLeadSource.php` (Modify)

* **Type / Scope**: Department-specific Eloquent source models.
* **Proposed Changes**: Allow and cast the new `type` value so shared form and service code can use the same type rules in both departments.

#### 4. `app/Models/RentalRequest.php` and `app/Models/ResaleRequest.php` (Modify)

* **Type / Scope**: Department-specific request models.
* **Proposed Changes**: Cast the nullable `lead_source_referrer_unknown` snapshot as a nullable boolean for consistent reads in both departments.

#### 5. `app/Filament/Support/LeadSourceResource.php` (Modify)

* **Type / Scope**: Shared manager catalog interface.
* **Proposed Changes**: Add the type selector to source creation for `awareness` and `person_referral`, show each source's type, and provide a classification action from `unclassified_legacy` to one of those types. Prevent deactivation of the built-in `other` row and prevent activation of `legacy_contact_method`; classification and activation of an unclassified row remain separate manager actions. Keep the existing department-specific access checks.

#### 6. `app/Services/LeadSourceService.php` (Modify)

* **Type / Scope**: Catalog creation, classification, and audit service.
* **Proposed Changes**: Allow managers to create only `awareness` and `person_referral` options, reserving `other` for the existing built-in row and both legacy values for historical records. Permit classification of `unclassified_legacy` only to `awareness` or `person_referral`, with activation remaining a separate action. Reject activation of `legacy_contact_method` and deactivation of the built-in `other`. Use `type` as the sole runtime source for description behavior; do not synchronize or consult the legacy `is_other` column. Record source creation and classification through department-scoped audit events.

#### 7. `app/Filament/Support/RequestFormSchema.php` (Modify)

* **Type / Scope**: Shared request create, edit, and detail forms.
* **Proposed Changes**:
  * Load options from the request's department catalog. For new requests, show only active `awareness`, `person_referral`, and built-in `other` values. On edit, retain the selected inactive legacy value for display without offering it as a new selection.
  * Keep the field label `مصدر الوصول`. Add helper text asking staff to record the main source the client identifies; when several channels contributed, use the one the client identifies as main.
  * For `person_referral`, show `اسم الشخص المُحيل` and an explicit «الاسم غير معروف» checkbox. Entering a name clears the checkbox; selecting unknown clears and hides the name input. On create or source change to `person_referral`, require exactly one of a name or explicit unknown.
  * Add a non-persisted `lead_source_referrer_details_touched` flag. Set it false on edit load and true only when staff change the name or unknown control. When false, omit both referral fields from the edit payload so a historical record with both fields null keeps both null, even if the checkbox would otherwise submit `false`. When true, submit both fields and apply the exactly-one rule.
  * Show and require `lead_source_other` only for the built-in `other`; show neither dependent value for `awareness`. When staff change the source, immediately clear the old dependent form state; then allow them to enter values for the new type before submission.
  * In request details, distinguish a saved name, explicit unknown, and historical not-recorded state.

#### 8. `app/Services/RequestService.php` (Modify)

* **Type / Scope**: Request persistence and server-side validation.
* **Proposed Changes**:
  * Add both referrer snapshot fields to create/update editable values and server-side validation.
  * Extend `auditSnapshot()`'s redaction list for `lead_source_referrer_name`. Use the existing changed-text marker whenever the name is filled and retain the field key as the change indicator.
  * Require exactly one of a non-empty name or `lead_source_referrer_unknown = true` when creating a request with a `person_referral` source, changing a request's source to `person_referral`, or explicitly changing referral details while that source remains selected. Reject both values. Selecting unknown clears the name; submitting a name clears the unknown flag.
  * When `lead_source_referrer_details_touched` is false on edit, `RequestFormSchema` omits both referral fields and `RequestService` preserves their stored values, including historical nulls. When referral details are submitted after an explicit change, require exactly one value.
  * Validate that each new or changed source selection uses only an active `awareness`, `person_referral`, or built-in `other`. Permit the exact currently stored source to remain unchanged, even if inactive, during unrelated updates.
  * When the source ID changes, discard dependent values stored for the previous source, preserve submitted values valid for the new type, clear only fields incompatible with it, and then validate. For `person_referral`, preserve a newly submitted name or explicit unknown and clear `lead_source_other`; for `other`, preserve a newly submitted description and clear both referrer fields; for `awareness` or no source, clear all three dependent values. Do not carry forward old values if the corresponding field was omitted from the update. Keep source optional and do not infer a referrer from the linked property broker.

#### 9. `app/Exports/RentalRequestExporter.php` and `app/Exports/ResaleRequestExporter.php` (Modify)

* **Type / Scope**: Rental and Resale request exports.
* **Proposed Changes**: Add the referrer snapshot as an explicit column next to source and «أخرى» detail. Export the name when present, «غير معروف» when the unknown flag is true, and an empty value for historical records whose name was not captured.

#### 10. `tests/Feature/RequestLifecycleTest.php` (Modify)

* **Type / Scope**: Request service integration tests.
* **Proposed Changes**: Cover both departments' type-specific persistence and validation; require exactly one of name or explicit unknown on create, source change to `person_referral`, and explicit referral-detail changes; reject both and missing values. When changing to `person_referral`, assert a newly submitted name or explicit unknown is retained and an old «أخرى» description is cleared. When changing to `other`, assert the newly submitted description is retained and old referrer fields are cleared. When changing to `awareness` or no source, assert all dependent fields are cleared. Preserve historical null referral fields and inactive current sources during unrelated edits. Verify broker-first source-null behavior and audit redaction by asserting `before_values` and `after_values` omit the literal referrer name while retaining the changed field key and existing marker for a filled value.

#### 11. `tests/Feature/FilamentUiAccessTest.php` (Modify)

* **Type / Scope**: Catalog UI and authorization tests.
* **Proposed Changes**: Cover required source-type selection; prevent managers from creating `other` or legacy types; cover manager-only catalog creation/classification, department isolation, rejection of legacy-type activation, protection of the built-in `other` from deactivation, classification auditing, and its Arabic audit-interface label.

#### 12. `tests/Feature/LeadSourceExportTest.php` (Create)

* **Type / Scope**: Export regression tests.
* **Proposed Changes**: Assert both department exporters include the name, «غير معروف», and blank historical state as appropriate under the correct Arabic column label.

#### 13. `tests/Feature/LeadSourceTypeMigrationTest.php` (Create)

* **Type / Scope**: Upgrade-path migration test.
* **Proposed Changes**: Run the new migration against a disposable pre-change schema fixture and assert every listed label-to-type mapping, preservation of existing active state for known awareness/referral rows, an active built-in «أخرى» row even when the fixture starts inactive, inactive contact-method and unclassified rows, unchanged source IDs and request foreign keys, idempotent `الإدارة العليا` setup, null historical referral fields, unchanged unused `is_other` values, and a clear refusal to roll back.

#### 14. `app/Filament/Support/AuditEventResource.php` (Modify)

* **Type / Scope**: Shared audit-event presentation.
* **Proposed Changes**: Map the new `lead_source.classified` event to a clear Arabic label in both department-scoped event maps.

#### 15. `docs/DECISIONS.md` and `docs/ACCEPTANCE_TESTS.md` (Updated in this design review)

* **Type / Scope**: Governing project decisions and acceptance criteria.
* **Applied Changes**: Added `D-088` to supersede the source meaning and new-selection rules in `D-051`, while retaining separate department catalogs, manager catalog permissions, and historical values. Added `D-087` to supersede only the request-export field restriction in `D-062` for the referrer snapshot: export a captured name, «غير معروف» for explicit unknown, and a blank value for historical nulls. Updated `AT-070` and `AT-158` for the approved export values; `AT-158` also covers typed sources, referral name-or-explicit-unknown behavior, the built-in «أخرى» detail, legacy history, and department isolation. Both acceptance tests are marked `Not run` until the feature is implemented and verified.

#### 16. `tests/Feature/LeadSourceRequestFormTest.php` (Create)

* **Type / Scope**: Request-form and detail presentation tests.
* **Proposed Changes**: Verify type-driven field visibility and requiredness, mutual exclusion of referrer name and unknown state, and display of a saved name, explicit unknown, or historical request with both referrer fields null. On source selection change, verify the form clears the old dependent state before staff enter details for the new type. Edit a historical request through the full Filament form, change an unrelated field without touching either referral control, and assert both referral columns remain null. Confirm active eligible values are offered, inactive values are not offered for changed selections, and the current selection remains visible when inactive.

### Implementation Phases and Checkpoints

1. **Schema and source types** — Implement migration item 1, enum item 2, source/request model items 3–4, and migration test item 13. **Checkpoint:** the migration feature test passes on the supported test database, preserving source IDs, request foreign keys, historical nulls, built-in «أخرى» activation, and all specified mappings.
2. **Catalog management** — Implement catalog resource/service items 5–6 and audit presentation item 14, with the catalog cases in `FilamentUiAccessTest.php` (item 11). **Checkpoint:** focused catalog authorization, classification, activation, and audit tests pass for both departments.
3. **Request form and persistence** — Implement form item 7, request service item 8, lifecycle test item 10, and form test item 16. **Checkpoint:** focused request tests pass for create/edit validation, old-value clearing with new-value preservation on source changes, untouched historical nulls, inactive current selections, and broker-first requests.
4. **Exports and acceptance records** — Implement exporter item 9, export test item 12, and verify the already-updated decisions and acceptance criteria in item 15. **Checkpoint:** focused export tests pass for both departments and `AT-070`/`AT-158` match the approved outputs.
5. **Integrated verification and production qualification** — Run all focused tests, then the full suite. Before scheduling production maintenance, identify the production database engine/version and successfully rehearse the exact migration on a disposable copy of the pre-change schema using that same engine/version. **Checkpoint:** tests pass, source IDs and request links are verified on the rehearsal database, and the engine/version plus rehearsal result are recorded with deployment evidence. Do not schedule the production migration until this checkpoint passes.

Each phase leaves the application buildable and its completed behavior covered by the stated focused test gate before the next phase begins.

### Verification Plan

* **Focused tests**: Run the request lifecycle, catalog UI, request-form, export, and migration feature tests listed above.
* **Full suite**: Run the full project test suite after the focused tests pass.
* **Manual review**: On the disposable pre-change database used for the production compatibility rehearsal, confirm the migration preserves all source IDs and request links, and that editing a historical request with a legacy source retains its existing selection. Confirm `D-051` is superseded as specified by `D-088`, the export exception in `D-062` matches `D-087`, and `AT-070` and `AT-158` cover the approved behavior.
* **Production**: Keep the migration file in the commit. Complete the database engine/version rehearsal and follow the maintenance-mode rollout sequence under Non-Functional Requirements.
