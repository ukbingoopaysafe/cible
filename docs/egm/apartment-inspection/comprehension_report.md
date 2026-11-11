# Stage 6 — Comprehension Test

## What this proposes

Require each new Rental or Resale inspection to record whether the contacted person is the unit owner or a broker, plus that person's name and normalized phone. Staff may link the inspection to the request's broker only when they confirm it is the same person; the inspection keeps its own contact snapshot. New inspections stop collecting defects, while history retains old defects separately from notes.

## How the existing system works, per this document

Rental and Resale share the `recordFieldInspection` action and `RecommendationService::recordInspection()` service, but keep separate inspection records. The current form records the representative, inspection time, photo-match result, defects, and notes, but no owner or broker contact. A request may have a broker attached who is different from the person staff contacted about the unit.

## Findings

### Country input source is unclear — Severity: Major

* **Affected scope:** Technical Plan → Shared inspection flow (line 69); Detailed Implementation Plan → `RecommendationService.php` and `SectionRequestResource.php` (lines 186–187).
* **Discrepancy and rationale:** The service must receive a country value and the inspection stores it, but the form requirements only specify a required phone field. The design does not say whether staff select the country, the system infers it from the number, or the reusable `PhoneField` supplies it. This leaves the form-to-service input contract unclear.

The reviewer read only the target design as instructed and used no prior-thread context. No tests were run and no files were changed by the reviewer.
