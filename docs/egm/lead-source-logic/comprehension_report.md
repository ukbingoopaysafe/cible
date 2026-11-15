## What this proposes

The design changes the optional request source to capture the main way the client says they learned about Cible. It adds stable source types that control referral-name and “other” fields, preserves historical values, and leaves unknown sources unset. Rental and Resale keep separate catalogs and request tables, with a forward-only migration and shared form and service behavior.

## How the existing system works, per this document

Rental and Resale each have an optional source field, but the available values mix awareness sources with contact methods such as calls and visits. The form shows a description field for «أخرى», but has no referrer-name field or stable type to control conditional fields. Existing source values and department-specific catalogs must be preserved.

## Findings

### `comprehension-1-1` — Historical migration mapping is underspecified in the implementation plan

* **Severity**: Major
* **Affected scope**: Lines 113, 193
* **Discrepancy and rationale**: The migration must classify existing person-referral and established awareness values, but the implementation-plan migration entry does not list those rows or explain how to identify them. An implementer reading only the detailed range cannot tell which historical rows are safe to classify.

### `comprehension-1-2` — Manager-created `other` sources have no defined behavior

* **Severity**: Major
* **Affected scope**: Lines 80, 84, 193, 218
* **Discrepancy and rationale**: `other` is defined as the built-in «أخرى» source, but managers may create sources with type `other`. The document does not say whether these additional sources show and require the description field or how their `is_other` value is handled.

### `comprehension-1-3` — Validation is unclear when editing a historical referral's details

* **Severity**: Major
* **Affected scope**: Lines 223, 228
* **Discrepancy and rationale**: An old referral with no captured name or unknown state keeps that state unless referral details are updated. The service rules require a name or explicit unknown choice for new requests or source changes, but do not say what validation applies when staff update referral details without changing the source.
