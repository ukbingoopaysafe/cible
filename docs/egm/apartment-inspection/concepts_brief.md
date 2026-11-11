# Apartment Inspection Contact Snapshot

* **Author:** ukbingoopaysafe
* **Date:** 2026-11-10

## Overview

Rental and Resale staff use the same request action to record a field inspection, but each department stores inspections in its own table. The action and form should be labeled **فحص الشقة**. Each new inspection should also record who staff contacted about the unit: the owner or a broker, with that person’s name and phone number.

The contact is a snapshot on the inspection. It does not create a property, listing, unit, or owner catalog entry. This is a narrow exception to the current data model rule that inspection records do not store owner data.

## Analysis

### Shared workflow and contact entry

SectionRequestResource::recordFieldInspectionAction() and RecommendationService::recordInspection() already provide a shared path for both departments. The service maps each department to separate inspection and request models. Extend that path so Rental and Resale keep the same rules and labels. Preserve the existing action key recordFieldInspection; change only the user-facing label and form heading to **فحص الشقة**.

Keep the existing inspection representative, inspection time, photo-match result, and notes. Preserve the current rule that an inspection belongs to a recommendation on an active request and is recorded only after that recommendation is shared with the customer or selected by them.

Add a required contact role choice with Arabic labels **مالك الوحدة** and **وسيط**, followed by required fields labeled **اسم جهة التواصل** and **رقم هاتف جهة التواصل**. Staff enter the name and phone for the person they actually reached. If the owner’s phone is unavailable, they choose the broker role and enter the broker’s details. Reuse the existing phone-entry and normalization patterns (PhoneField and CustomerPhoneNormalizer); do not use a contact-edit form that changes a shared customer or broker record.

### Explicit broker link

The entered role, name, and phone are always saved on the inspection as a point-in-time snapshot. When the selected role is broker and the request already has a broker, the form may offer an unchecked option labeled **ربط بوسيط الطلب**, showing which broker it refers to. Selecting it explicitly links only to that request’s broker. Server-side validation must verify that the link is the exact broker attached to that request in the same department. An owner contact or a different broker stays unlinked. Do not match names, copy current broker details over the entered snapshot, or infer identity from request data.

Keep the optional broker reference separate from the snapshot. Later broker edits must not change inspection contact details; deleting a broker must not delete the inspection or its snapshot. This separation resolves the tension between retaining accurate historical contact details and linking a known request broker. Its cost is intentional duplication and the need to keep the optional link scoped and validated.

### Defects and historical records

Remove the defects field from new inspection entry and stop accepting it in the service. Keep the notes field as the place for current inspection notes. Do not merge old defect text into notes, since that can overwrite notes or change the meaning of historical data.

The existing observed_defects column contains saved inspection history in both departments. Retain those values and show them read-only in inspection history under a clear legacy label such as **عيوب سابقة**. Do not drop the column or backfill contact details from request records. New contact columns should allow older inspections to remain without a contact; the service requires role, name, and phone only for new submissions.

### Data, audit, and operations

The current schema and behavior are described in docs/DATA_MODEL.md section 4.5 and docs/WORKFLOWS.md section 3.4. Update those descriptions to document the inspection-only contact snapshot while keeping the prohibition on a central unit or owner catalog. The inspection tables are separate, so any new contact fields and department-specific optional broker references must be added consistently to both with a forward migration. Keep the migration additive so older rows and a prior application version can continue to read and write during rollout. A rollback must not discard snapshots already recorded.

Inspection contact names, phones, and notes are personal or free-text data. Keep them out of audit metadata and logs. The existing inspection audit event can record the contact role, whether a broker link was used, and the linked broker ID. Keep the snapshot visible within request history under the existing request and department access rules. The user decided that contact data stays permanently in the request record; do not add a separate expiry or global contact search.

This adds no external service or unusual-scale workload. The main operational risks are wrong identity links, loss of old defect history, and privacy exposure from permanent phone snapshot retention. Focus verification on server-side link constraints, preserved history, and equal behavior in both departments. Existing workflow tests exercise Rental inspection; add corresponding Resale coverage for the new fields and link rules.

## Goals vs. Non-Goals

* **In-Scope Goals:** Use **فحص الشقة** for the action and form heading in both departments; require a contact role, name, and phone for new inspections; preserve the entered contact as an inspection-only snapshot; permit an explicit optional link only to the request’s existing broker; remove new defects entry while preserving old defect history; retain inspection representative, time, photo-match result, and notes.
* **Out-of-Scope Non-Goals:** Creating a central owner, property, listing, or unit catalog; creating a new broker record from inspection entry; fuzzy name matching or automatic identity linking; replacing the snapshot with mutable broker-profile data; changing the existing inspection eligibility rules or photo-match workflow.

## Alternatives Weighed

* **Rejected by the author:** A shared owner, property, listing, or unit database. The contact belongs to the inspection record only.
* **Rejected by the author:** Fuzzy name matching to link a broker. Staff enter the contact and explicitly choose whether to link the request’s existing broker.
* **Rejected for this concept:** Saving only a broker foreign key. Broker data can change and does not prove who staff reached during the inspection.
* **Rejected for historical safety:** Dropping observed_defects or folding it into notes. Keeping it as read-only legacy history preserves the original meaning and existing notes.
* **Rejected for identity safety:** A generic broker-directory picker. It could link an unrelated broker; only the broker already attached to the request is eligible.

## Key Decisions & Staging for Next Steps

1. Keep the action and service shared across Rental and Resale, with department-specific storage and broker constraints.
2. Treat contact role, name, and phone as required for new records and as an inspection snapshot. Keep the optional request-broker link separate and explicitly selected.
3. Retain the internal Filament action key and the existing inspection representative, time, photo-match, and notes fields.
4. Stop new defects capture, but preserve and display existing defect values as read-only history. Use a forward, additive migration for the new contact fields; do not infer old contact details.
5. Add parity checks for both departments, including required fields, owner contacts without broker links, explicit request-broker links, rejection of other broker IDs, and legacy defect visibility.
6. Retain contact snapshots permanently as part of request history, using the existing request and department access rules.

## Goldfish Reviewer Status

| Lens | Status |
| --- | --- |
| design_critic | completed |
| codebase_details | completed |
| zoom_out | completed |
| comprehension | completed |
| implementation_readiness | completed |
| structural | completed |
| inventive_catalyst | completed |

All seven lenses completed. Findings converged on the inspection-only snapshot, strict optional broker linking, legacy defect preservation, and Rental/Resale parity. No tests were run and no production code was changed.
