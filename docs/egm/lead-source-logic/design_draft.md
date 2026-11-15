# Lead Source and Customer Awareness Attribution

* **Author:** ukbingoopaysafe
* **Status:** In Review (owner-approved; EGM review pending)
* **Date:** 2026-11-12

## Overview

Cible records one optional source on each Rental or Resale request. The current list mixes how a client learned about the company with ways the client contacted it. This design records the main source the client identifies when asked how they learned about Cible; when several channels contributed, staff ask the client which was the main source.

Phone calls, direct visits, and WhatsApp describe contact methods, so they will not be offered as new source choices. This first change will not add a second field for first contact. Person-based referrals, including a separate «الإدارة العليا» choice, will ask for the referrer's name or an explicit «الاسم غير معروف» selection. «أخرى» will keep its own source-description field.

The working proposal keeps the source on each request. If a request is created through a broker before the customer's source is known, leave the source unset until it is known; do not infer the source from the linked property broker. Apply the same behavior in Rental and Resale while preserving their separate source catalogs.

## The Problem

The existing source field can contain values such as «مكالمة هاتفية», «زيارة مباشرة», «إحالة من موظف», and «إعلان إلكتروني». A call or visit says how contact occurred, but not how the client learned about Cible. Reporting or reviewing these values as one kind of source therefore mixes different facts.

The current form shows an extra description only for a source marked «أخرى». It has no separate field for the person behind a referral, and manager-added source options do not carry a stable type that can decide which extra field to show. Adding «الإدارة العليا» as a label alone would not make its name-entry behavior reliable.

The system needs a clear source meaning, conditional fields that follow source meaning rather than Arabic label text, and a safe treatment for requests whose source is not yet known. Existing source values must remain intact; old calls, visits, and WhatsApp entries must not be reinterpreted as awareness sources without evidence.

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

* Whether future reporting needs an explicit «مصدر غير معروف» awareness value distinct from a source that was not asked or recorded. Until that need is approved, keep `lead_source_id` nullable and leave it empty when the source is not known or not captured.

## Critical User Journeys (CUJs)

### CUJ 1: Manager adds or classifies a department source

* **Given**: A manager authorized for Rental or Resale is viewing that department's source catalog.
* **When**: The manager adds a source and chooses its stable type, or classifies an inactive unclassified legacy source.
* **Then**: The source is typed and audited in that department only. A newly added source is available to matching requests; a classified legacy source remains inactive until separately activated.

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
| `person_referral` | A person introduced Cible to the client, including an employee, a customer or acquaintance, or «الإدارة العليا». | Show the person's name field and an explicit «الاسم غير معروف» choice. For new referrals, require a name or the explicit unknown choice. |
| `other` | The built-in «أخرى» source. | Show and require the existing source-description field. |
| `legacy_contact_method` | A historical choice such as phone call, direct visit, or WhatsApp that describes contact method rather than awareness. | Keep displaying it for old requests, but exclude it from new source choices. |
| `unclassified_legacy` | A manager-added historical source whose meaning cannot safely be inferred from its label. | Preserve it on old requests and exclude it from new choices until a manager classifies it. |

Managers continue to add source labels within their own department's catalog. When adding a new value, they must select an allowed type (`awareness`, `person_referral`, or `other`). The two catalogs remain separate, while the same type meanings and validation rules apply to both. The legacy types are reserved for migration and cannot be selected for new values.

### Request Data and Form Flow

Keep one optional `lead_source_id` on each request. It represents the main source the client identifies when staff ask; if several channels contributed, staff ask the client to identify the main one rather than guessing first or most influential. Add a nullable referrer-name snapshot and a nullable `lead_source_referrer_unknown` flag to each request table; retain the existing `lead_source_other` field for the «أخرى» description. For a new person referral, require either the person's name or an explicit unknown choice. A null flag on an old request means the name was not captured under the previous workflow, not that staff confirmed it was unknown. Preserve that legacy state during unrelated edits. When it is a person referral, the shared request form reveals the name field and unknown choice. When it is `other`, the form reveals the existing required description. Changing the selected source clears its dependent values.

The shared `RequestFormSchema` reads the selected department's catalog and uses its type to control visibility. `RequestService` applies the same type-to-field rules during create and update validation, so requests cannot save a referrer name against an unrelated source or an “other” description against a normal source. Rental and Resale keep their separate model and foreign-key boundaries.

```text
Manager adds a source label
        │
        └── selects its stable type in the department catalog
                         │
                         ▼
Request form loads active, selectable sources for that department
        │
        ├── awareness ─────────────── no dependent field
        ├── person_referral ────────── referrer name OR explicit unknown choice
        └── other ──────────────────── required source-description field
                         │
                         ▼
RequestService validates the source type and dependent values
                         │
                         ▼
Request row stores source ID, optional referrer name, and other detail
```

### Migration and Historical Values

Add a migration file that adds the source type to both catalogs and the referrer-name snapshot plus nullable unknown flag to both request tables. Leave the unknown flag null on historical requests so the migration does not invent whether their referrer's name was unavailable. Seed «الإدارة العليا» as a `person_referral` source in each department. Classify the built-in «أخرى» row as `other`, existing person-referral values as `person_referral`, and established awareness values as `awareness`. Mark phone call, direct visit, and WhatsApp as `legacy_contact_method` and deactivate them for new requests. Classify manager-added values only where their meaning is known; put all others in `unclassified_legacy` without changing their IDs or existing request references.

New database changes are delivered as a tracked migration for the owner to apply after pulling `main` on production. The migration must be additive, and its `down()` method must refuse rollback with a clear forward-only message rather than dropping type classifications or referral data. Once the migration is applied, keep the new application version; handle defects through a forward code or database fix. The previous application cannot write the required source type for new catalog rows and does not display the referral snapshot.

### Related Components

* `LeadSourceResource` and `LeadSourceService` add and validate the source type when a manager creates a source, while keeping department authorization and audit behavior.
* `RentalLeadSource` and `ResaleLeadSource` expose the stored type to the shared request form.
* `RequestFormSchema` shows the referrer name/unknown choice or source-description field according to type.
* `RequestService` validates type, source ID, and dependent values on request creation and update. New or newly changed person referrals require a name or explicit unknown choice. Older requests whose name was not captured remain unchanged during unrelated edits. Broker-first requests stay source-null until the client's source is known; the linked property broker is not used as an inferred referrer.
* The Rental and Resale request exporters include the referrer name, displaying «غير معروف» for an explicit unknown choice. Request audit snapshots record that the name changed without copying the name into the audit payload.

### Decisions Confirmed for Detailed Implementation

* For a new person referral, staff must provide the person's name or explicitly select «الاسم غير معروف». Historical referrals with neither value retain a null state meaning the old workflow did not capture the name.
* Store one awareness source per request: the main source the client identifies when asked; when several channels contributed, staff ask the client to identify the main one.
* Managers can classify an inactive `unclassified_legacy` source; the change is audited and the manager may activate it separately.
* Export the referrer name (or explicit «غير معروف» state); audit history records only that the name changed, not the name itself.
* Preserve unknown manager-added source rows as inactive `unclassified_legacy`; do not infer a type from the Arabic label.
* Keep the migration additive and forward-only after use. If application code is rolled back after referral data is recorded, retain the new columns and data; the older UI may not display the name, so restore access with a forward fix rather than dropping the schema.

## Alternatives Considered

The current draft uses a stable type on each source option. The author selected this model at the Technical Plan decision gate.

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
* **Reason for not selecting it**: The author selected a stable source type per option so the same classification can govern the form, validation, and migration of old values.

### Use a Fixed Source List in Code

* **Pros**: Source types and field behavior would be controlled centrally and could not drift between manager-created values.
* **Cons**: Managers would lose the ability to add department-specific source labels without a code change and deployment.
* **Reason for not selecting it**: The author selected typed manager-managed options, retaining the existing ability to add and deactivate source values.

### Add an Explicit “Unknown” Awareness Source Value

* **Pros**: Can distinguish “asked but unknown awareness source” from a blank value that was not recorded.
* **Cons**: Adds an awareness category that could be confused with a known source and may distort counts if staff use it instead of leaving an uncaptured source blank.
* **Status**: Deferred until reporting needs require this distinction. The separate «الاسم غير معروف» choice applies only to a person-referral name and does not add an unknown awareness source.

## Non-Functional Requirements

* **Privacy and audit**: The referrer's name is stored only on the request and may appear in request exports. Request audit history records that the name changed but redacts the literal value.
* **Department isolation**: A source ID must resolve only through the request's Rental or Resale catalog; manager authorization and audit records remain department-scoped.
* **Rollout and rollback**: Apply the tracked migration after pulling the updated application. The migration is forward-only; once applied, retain the new schema and data and fix defects with a forward deployment. The older application cannot add sources with a required type and does not display the new referral snapshot.

### Additional Resources

* [Concepts brief](./concepts_brief.md)

## Detailed Implementation

Rental and Resale use the same rules through shared components while retaining separate catalogs, request tables, and foreign keys.

### Target Files to Create/Modify

#### 1. `database/migrations/2026_11_12_000000_add_typed_lead_sources_and_referrers.php` (Create)

* **Type / Scope**: Laravel schema and data migration.
* **Proposed Changes**: Add a non-null `type` string (32 characters) to both lead-source tables, a nullable `lead_source_referrer_name` string (255 characters), and a nullable `lead_source_referrer_unknown` boolean to both request tables. Classify known seeded rows without changing IDs or request references; set phone call, direct visit, and WhatsApp rows to inactive `legacy_contact_method`; preserve unknown manager-added rows as inactive `unclassified_legacy`; insert or update `الإدارة العليا` as active `person_referral` in each catalog. Leave the unknown flag null on historical requests. Keep `is_other` synchronized with the `other` type. The migration is tracked for production application after pulling `main`. Implement `down()` as an explicit forward-only guard that stops rollback with a clear message; do not drop typed classifications or referral fields. After this migration is applied, do not roll application code back to a release that lacks the required type/referrer behavior. If a defect occurs, preserve the schema and data and deploy a forward fix.

#### 2. `app/Enums/LeadSourceType.php` (Create)

* **Type / Scope**: Shared source-type enum.
* **Proposed Changes**: Define `awareness`, `person_referral`, `other`, `legacy_contact_method`, and `unclassified_legacy`, with labels for the manager's catalog UI. Keep legacy values unavailable for new manager-created choices.

#### 3. `app/Models/RentalLeadSource.php` and `app/Models/ResaleLeadSource.php` (Modify)

* **Type / Scope**: Department-specific Eloquent source models.
* **Proposed Changes**: Allow and cast the new `type` value so shared form and service code can use the same type rules in both departments.

#### 4. `app/Models/RentalRequest.php` and `app/Models/ResaleRequest.php` (Modify)

* **Type / Scope**: Department-specific request models.
* **Proposed Changes**: Cast the nullable `lead_source_referrer_unknown` snapshot as a nullable boolean for consistent reads in both departments.

#### 5. `app/Filament/Support/LeadSourceResource.php` (Modify)

* **Type / Scope**: Shared manager catalog interface.
* **Proposed Changes**: Add the type selector to source creation, show each source's type, and provide a classification action for `unclassified_legacy` rows. Keep the existing department-specific access checks and active/inactive actions.

#### 6. `app/Services/LeadSourceService.php` (Modify)

* **Type / Scope**: Catalog creation, classification, and audit service.
* **Proposed Changes**: Validate manager-selected types against `awareness`, `person_referral`, and `other`; reserve both legacy values for migration/classification; synchronize `is_other`; record source creation and classification through department-scoped audit events.

#### 7. `app/Filament/Support/RequestFormSchema.php` (Modify)

* **Type / Scope**: Shared request create, edit, and detail forms.
* **Proposed Changes**: Load typed options from the correct department catalog. New requests show active sources. Keep the field label `مصدر الوصول` and add helper text instructing staff to ask for the main source identified by the client; when several channels contributed, record the one the client identifies as main. Editing retains the currently selected inactive legacy source but does not offer other inactive sources for reassignment. For `person_referral`, show `اسم الشخص المُحيل` and an explicit «الاسم غير معروف» checkbox; a new referral requires one of them. Selecting unknown clears and hides the name input. For an old unchanged referral whose name and unknown state were never captured, preserve that null state unless the user updates its referral details. Show and require `lead_source_other` for `other`; show neither dependent value for `awareness`. Clear both referrer values when the source ID changes, and show the saved name, explicit unknown state, or historical not-recorded state in request details.

#### 8. `app/Services/RequestService.php` (Modify)

* **Type / Scope**: Request persistence and server-side validation.
* **Proposed Changes**: Add both referrer snapshot fields to create/update editable values and validation. For a new request or a source change to `person_referral`, require either a non-empty name or `lead_source_referrer_unknown = true`; reject both together. Preserve a null name/unknown state on an older unchanged referral during unrelated edits. Validate the source against the department catalog and enforce type-specific fields on create and update. Clear both referrer fields whenever the source ID changes; clear all source-dependent details when they no longer apply. Keep source optional and do not infer a referrer from the linked property broker.

#### 9. `app/Exports/RentalRequestExporter.php` and `app/Exports/ResaleRequestExporter.php` (Modify)

* **Type / Scope**: Rental and Resale request exports.
* **Proposed Changes**: Add the referrer snapshot as an explicit column next to source and «أخرى» detail. Export the name when present, «غير معروف» when the unknown flag is true, and an empty value for historical records whose name was not captured.

#### 10. `tests/Feature/RequestLifecycleTest.php` (Modify)

* **Type / Scope**: Request service integration tests.
* **Proposed Changes**: Cover both departments' type-specific persistence and validation, requiring either a name or explicit unknown state for new referrals, preserving historical null states, clearing stale details, preservation during unrelated edits, legacy-source handling, broker-first source-null behavior, and audit redaction of the referrer name.

#### 11. `tests/Feature/FilamentUiAccessTest.php` (Modify)

* **Type / Scope**: Catalog UI and authorization tests.
* **Proposed Changes**: Cover required source-type selection, manager-only catalog creation/classification, department isolation, legacy types being unavailable for new options, and audit of classification.

#### 12. `tests/Feature/LeadSourceExportTest.php` (Create)

* **Type / Scope**: Export regression tests.
* **Proposed Changes**: Assert both department exporters include the name, «غير معروف», and blank historical state as appropriate under the correct Arabic column label.

#### 13. `tests/Feature/LeadSourceTypeMigrationTest.php` (Create)

* **Type / Scope**: Upgrade-path migration test.
* **Proposed Changes**: Run the new migration against a disposable pre-change schema fixture and assert known mappings, safe handling of unknown rows, preserved source IDs and request foreign keys, idempotent `الإدارة العليا` setup, inactive contact-method values, null unknown states for historical requests, and a clear refusal to roll back.

### Verification Plan

* **Focused tests**: Run the request lifecycle, catalog UI, export, and migration feature tests listed above.
* **Full suite**: Run the full project test suite after the focused tests pass.
* **Manual review**: On a disposable copy of a pre-change database, confirm the migration preserves all source IDs and request links, and that editing an old request with an inactive source retains its existing selection.
* **Production**: Keep the migration file in the commit. After pulling `main`, the owner applies it with `php artisan migrate --force`.
