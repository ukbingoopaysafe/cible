**Status: resolved**

# Goldfish Annotated Review Report — Round 3

**Target:** [lead-source-logic.md](./lead-source-logic.md) (Detailed Implementation, lines 185–276 at review time)

## Prioritized Findings & AI Suggestions

### Issue 1 — Redact referrer names from audit snapshots [`zoom_out`, `design_critic`, `implementation_readiness`, `codebase_details`] — Severity: Major

* **Affected Scope:** Implementation items 8 and 10; `RequestService::auditSnapshot()` and request lifecycle audit tests.
* **Discrepancy Description:** The design promises to record that a referrer name changed without storing the literal name, but the implementation plan does not instruct adding `lead_source_referrer_name` to the helper's fixed redaction list. The helper currently omits it.
* **Rationale:** An update could place the literal name in audit `before_values` or `after_values`, contrary to the privacy requirement.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** The gap is valid and reproduced by all review stages that examined audit handling. The helper and field are within the planned change scope.
* ***Proposed Edit:***
  * Location: Detailed Implementation items 8 and 10.
  * Change: Explicitly add `lead_source_referrer_name` to the `auditSnapshot()` redaction list, use the existing changed-text marker for filled values, and assert both snapshots omit the literal while retaining the changed field key/marker.

### Issue 2 — Keep the built-in «أخرى» source active after migration [`comprehension`, `zoom_out`, `implementation_readiness`, `codebase_details`] — Severity: Major

* **Affected Scope:** Migration item 1 and migration test item 13.
* **Discrepancy Description:** The plan assigns the built-in row type `other` but preserves its old active state; the test also asks to preserve that state.
* **Rationale:** If the production row is inactive, staff cannot select «أخرى» for new requests after migration.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** This is an availability requirement for a built-in source, not a historical state that should be preserved.
* ***Proposed Edit:***
  * Location: Detailed Implementation items 1 and 13.
  * Change: Force the built-in `is_other = true` row active in both catalogs and test with a fixture where it starts inactive.

### Issue 3 — Remove the repeated instruction for unknown legacy rows [`style`] — Severity: Nit

* **Affected Scope:** Detailed Implementation item 1.
* **Discrepancy Description:** The item states twice that unknown manager-added rows become inactive `unclassified_legacy`.
* **Rationale:** Combining the instructions makes the migration mapping easier to scan while keeping the no-label-inference rule.
* ***AI Suggestion:*** PROPOSE FIX
* ***Justification:*** This is a small clarity repair and does not change behavior.
* ***Proposed Edit:***
  * Location: Detailed Implementation item 1.
  * Change: Keep one statement: classify unknown manager-added rows as inactive `unclassified_legacy` without inferring type from labels.

### Issue 4 — Add a catalog-size cap or search threshold [`design_critic`] — Severity: Nit

* **Affected Scope:** Non-Functional Requirements, Scale.
* **Discrepancy Description:** The form loads all options and the design assumes each department catalog remains small without specifying a cap or search threshold.
* **Rationale:** A growing catalog could make the select difficult to use.
* ***AI Suggestion:*** DECLINE
* ***Justification:*** Stage 8 explicitly confirmed the existing small-catalog assumption is sufficient; adding a numeric cap or speculative search threshold is unsupported by current scale evidence. Treat this as an accepted design assumption, not an open issue.
* ***Proposed Edit:*** None.

## Resolution

The user previously authorized auto-accept for review fixes. The three PROPOSE FIX items were applied to the design. The catalog-size Nit remains recorded as an accepted assumption with no change. No application code was changed and no tests were run.

## Reviewer Status

All requested Round 3 reviewers completed: Stage 5 (`zoom_out`, `inventive_catalyst`), Stage 6 (`comprehension`), Stage 7 (`design_critic`, `structural`, `style`), Stage 8 (`zoom_out`), and Stage 9 (`implementation_readiness`, `codebase_details`, `style`).
