# Lead Source and First Contact Logic

* **Author:** ukbingoopaysafe
* **Date:** 2026-11-12
* **Status:** Design approved by the owner; EGM review pending.

## Overview

Cible currently stores one optional source on each Rental or Resale request. The owner clarified that a phone call or direct visit does not explain how the client learned about Cible; those values describe contact methods. The review recommendation is to make the source field answer only “How did the client learn about Cible?”, add the requested «الإدارة العليا» option and a separate referrer-name field, and defer a separate first-contact field unless a defined workflow needs it. If a request starts through a broker before the client or the client's source is known, leave the source unset until it is known; do not assume the linked property broker is the referrer.

This change could make future source analysis clearer, but the repository does not show a current source-attribution dashboard. Existing exports show the selected source and its “other” description. Treat misleading analytics as a risk when these mixed values are interpreted together, not as a verified problem in an existing dashboard.

## Reviewer Status

All seven fresh-context Goldfish reviews completed. Each reviewer read its own persona before analysis.

| Reviewer lens | Status | Main contribution |
| --- | --- | --- |
| `design_critic` | completed | Broker-first requests, unknown semantics, activity-channel duplication, form labels, and referrer-name behavior. |
| `codebase_details` | completed | Current schema, shared form and service, separate department catalogs, exports, audit behavior, and migration constraints. |
| `zoom_out` | completed | Lower-complexity alternative, existing decision scope, and the need to justify a separate first-contact field. |
| `comprehension` | completed | Attribution rules, request-level versus customer-level meaning, referral wording, and export clarity. |
| `implementation_readiness` | completed | Required owner decisions, migration and rollback contract, and acceptance conditions. |
| `structural` | completed | Stable source classification, department catalog consistency, and handling referrer names in audit logs and exports. |
| `inventive_catalyst` | completed | The trade-off between meaningful attribution and a simple intake form, with lower-complexity options. |

## Analysis

### What the repository confirms

- The seeded list includes phone call, direct visit, employee referral, customer or acquaintance referral, social media, electronic ad, website, and WhatsApp (`database/migrations/2026_10_24_160000_seed_section_lead_sources.php:10-18`). One optional `lead_source_id` and one `lead_source_other` value are stored on each request (`database/migrations/2026_10_24_140000_create_section_request_tables.php:108-118,147-157`).
- Rental and Resale use separate source catalogs, while a shared form and service handle both sections (`app/Filament/Support/RequestFormSchema.php:49-52,192-203`; `app/Services/RequestService.php:820-840`). The current decision record also says those catalogs are independent (`docs/DECISIONS.md:71`).
- «أخرى» already reveals a required description. The form and service treat it separately from the selected source (`app/Filament/Support/RequestFormSchema.php:199-203`; `app/Services/RequestService.php:369-378`).
- Contact activities already record a channel such as phone, WhatsApp, or in-person. They are manually logged interactions; the code does not guarantee that the earliest activity is the client's first contact (`app/Enums/ContactActivityChannel.php:5-21`; `app/Services/ContactActivityService.php:25-36,52-74`).
- The source catalog supports manager-added options, but currently classifies only “other” and active/inactive state. It has no source type or referrer-name rule (`database/migrations/2026_10_24_140000_create_section_request_tables.php:59-77`; `app/Services/LeadSourceService.php:20-46`).
- The request exports show the source and “other” description, but not separate awareness and contact dimensions (`app/Exports/RentalRequestExporter.php:41-42`; `app/Exports/ResaleRequestExporter.php:40-41`). The initial reporting decisions do not include new attribution analytics (`docs/DECISIONS.md:77,84`).

### Main objection and recommended direction

Adding a first-contact field now would add a second answer before the business has defined what “first contact” means. It may duplicate activity history, add form work, and store a fact that later disagrees with the first logged activity. Existing activities record communication channels but do not guarantee the first real contact. No report currently requires this second dimension.

The lower-complexity option is to make the existing source answer only “how did the client learn about Cible?”, leave it optional, remove contact methods such as phone call, direct visit, and WhatsApp from new source choices, and defer a separate first-contact field until a workflow or report requires it. Existing activity channels may help staff remember a conversation, but they cannot be treated as a reliable first-contact record without a defined intake rule.

If the owner confirms that both facts are needed, collect them independently. Before adding the second field, define whether it means the first inbound inquiry, the first exchange with the eventual customer, or the first interaction recorded in Cible; decide whether it is an intake-time snapshot or derived from an activity; and specify what happens when the values disagree.

For awareness, decide whether a single answer means the first source, the primary or most influential source, or the source reported by the customer. Define what to do when several channels contributed, when an existing client returns, and when a broker submits a request before the customer is identified. The current field is request-level even though “how the client learned about the company” can sound customer-level.

### Source and referrer behavior

The «الإدارة العليا» choice and a field for the specific person's name are owner-requested. A name field should also appear for any source that means a person referred the client. The review recommendation is to treat «الإدارة العليا» as its own source choice and require a name for person-based sources, while providing an honest way to record a referral when its person's name is unavailable. Confirm the referral wording means the person introduced Cible to the client, not that Cible recommended a property to the client. Keep the referrer distinct from the employee who owns the request; existing acceptance criteria allow those to differ (`docs/ACCEPTANCE_TESTS.md:73-75`).

Do not make the behavior depend on matching Arabic display text. Use stable source types or metadata to define which options require a name and which option requires an “other” description. Decide whether managers may add only labels to predefined types or also define new types and their field rules. Keep the two department catalogs separate as current decisions require, while deciding whether their types and reporting labels must remain aligned.

Keep the referrer name separate from the existing “other source” description. The name is personal data: decide whether it appears in exports and whether audit snapshots should store it. Current request audit snapshots redact some free-text fields, including `lead_source_other`; a new name field would need an explicit policy (`app/Services/RequestService.php:422-435,914-920`; `docs/DECISIONS.md:81,91-92`).

### Unknown values and migration

The current foreign key is nullable, so blank can continue to mean that the source was not captured or is not yet known. An explicit “unknown/unreported” option would create a different state. Add one only if staff need to distinguish “asked but the client does not know” from “not asked/not recorded”; if added, keep it optional and define how it appears in exports and reports.

Do not map old phone, visit, or WhatsApp selections to an awareness source. Those values do not establish how someone learned about Cible. Even apparently clear values were entered through a mixed field, and manager-added source labels can have unclassified meanings. Preserve legacy selections and their descriptions; map only values for which the owner approves a defensible rule. Reconcile records by department and old source value before changing report behavior.

Plan an additive migration and rollback contract before implementation. Preserve existing catalog rows and IDs through the rollback window. State how the old application behaves if new attribution data has been entered, whether old and new fields are dual-written or a legacy snapshot is retained, and how new data remains retrievable after rollback. Do not backfill all nulls to “unknown” or make historical requests newly required.

## Goals vs. Non-Goals

* **In-Scope Goals:** Give the source field a clear meaning; satisfy the requested «الإدارة العليا» choice and person-name prompt; retain the independent required “other” description; decide whether first-contact capture is needed; apply any approved behavior to both Rental and Resale; preserve historical data without inventing attribution.
* **Out-of-Scope Non-Goals:** This concept does not approve implementation, an attribution dashboard, multi-touch marketing analytics, automatic inference from activity history, or a new employee/customer directory for referrers.

## Alternatives Weighed

No alternative has been formally rejected by the owner. These are review recommendations for the next decision:

1. **Make the existing source awareness-only and defer first contact.** Recommended lower-complexity starting point. It fixes the central meaning problem while avoiding a duplicate field until the business needs it. Use existing activity history only where available, without assuming it captures first contact.
2. **Capture awareness and first contact separately.** Use this if both are needed at intake or in approved reporting. It preserves both facts but adds entry work and needs a definition for first contact, optionality, reporting, and disagreements with activity history.
3. **Use the earliest logged contact activity as first contact.** This could avoid a new request field, but the current system does not guarantee that the earliest manually logged activity is the actual first contact. Do not treat this as equivalent without an intake and recording rule.
4. **Keep one mixed field and only rename it.** This is the smallest change but leaves a single value unable to distinguish discovery from communication. It does not resolve the attribution ambiguity.
5. **Add an explicit unknown catalog value.** Use only if staff need to distinguish “asked but unknown” from blank/not captured. Otherwise, nullable remains the simpler representation; a sentinel could blur missing-data and known-source counts.

## Key Decisions & Staging for Next Steps

### Owner requests captured in this brief

- Add a distinct «الإدارة العليا» option.
- Show a field for the specific person's name when «الإدارة العليا» is selected.
- Provide a name field for personal referrals or recommendations.
- For a new person-based referral, require either the person's name or an explicit «الاسم غير معروف» choice; keep old referrals with no captured name distinct from explicitly unknown names.
- Store one source per request: the main source the client identifies when asked; if several channels contributed, staff ask the client to identify the main one.
- Do not treat phone calls or direct visits as answers to how the client learned about Cible.
- For a broker-first request whose client source is not yet known, leave the source unset; do not infer it from the linked property broker.
- After applying the migration, preserve its schema and data; handle defects with a forward fix rather than rolling the application or database back.
- Revisit the mixed source logic and retain the current “other” description behavior.

During design drafting, the owner confirmed the name-or-explicit-unknown rule for new person-based referrals, while preserving a separate null state for old records that never captured a name. The owner also approved exporting a recorded referrer name and redacting its literal value from request audit history.

### Design decisions confirmed during drafting

- Keep the source awareness-only and defer a separate first-contact field until a workflow or report requires it.
- For new person referrals, require a name or explicit unknown selection; preserve a null state on historical records where the old workflow did not capture the name.
- Store one source per request: the main source the customer identifies when asked; if several channels contributed, staff ask the customer to identify the main one.
- Keep Rental and Resale catalogs separate while giving their options consistent source types and conditional-field rules.
- Keep an unknown awareness source deferred; preserve old selections and descriptions without inferring source meaning from legacy labels.
- Use stable source classification metadata and consistent conditional-field rules across Rental and Resale.
- Include referrer names in exports and redact their literal values from request audit history.
- Preserve schema and data after applying the migration; fix defects with forward deployments rather than rolling application or database state back.

### Deferred beyond this design

1. Whether future reports need an explicit unknown awareness source distinct from a source that was not asked or recorded, and how the two states should be counted.

The deferred reporting decision does not block the current design because `lead_source_id` remains nullable. No production code changes or tests were performed as part of this concept review.
