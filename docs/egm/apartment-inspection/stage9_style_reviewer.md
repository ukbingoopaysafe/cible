# Stage 9 Style Review — Apartment Inspection Contact Capture

Mode: REVIEW. No separate style standard was supplied.

## Findings

1. **Severity:** Major  
   **Affected scope:** Detailed Implementation Plan, line 183.  
   **Style rationale:** “Make the migration forward-only” and “`down()` must refuse to drop” do not specify what refusal means when rollback runs. The concepts brief requires that rollback not discard saved snapshots, but the implementation plan leaves the operator-visible behavior unclear.  
   **Recommendation:** Specify the rollback behavior, such as aborting with a clear error, and make clear that it preserves recorded snapshots.

2. **Severity:** Major  
   **Affected scope:** Detailed Implementation Plan, line 186.  
   **Style rationale:** “The request attached to the recommendation” is ambiguous because “request” can mean either the domain request or an HTTP request. “Reject owner contacts or requests without a broker” also does not state what happens when the link option is unchecked or whether another broker ID is rejected.  
   **Recommendation:** State that checking the option links only to the broker currently attached to that Rental or Resale request, that the link stays null when unchecked, and that owner contacts or any other broker ID are rejected.

3. **Severity:** Nit  
   **Affected scope:** Detailed Implementation Plan, lines 186, 189, and 208.  
   **Style rationale:** “Role and link metadata” does not name the audit fields, so the implementation and verification could interpret it differently. The concepts brief identifies the intended fields.  
   **Recommendation:** Name them explicitly: contact role, whether the broker link was used, and the linked broker ID.

4. **Severity:** Nit  
   **Affected scope:** Detailed Implementation Plan, line 188.  
   **Style rationale:** “In the history heading and empty state, along with the saved contact snapshot and inspection notes” can be read as placing the snapshot and notes in the empty state, where no inspection exists.  
   **Recommendation:** Separate the cases: use the label in the history heading and empty-state message; show the snapshot and notes on each inspection entry.

5. **Severity:** Nit  
   **Affected scope:** Detailed Implementation Plan, line 193.  
   **Style rationale:** “Rental inspection steps and Resale unit/contact guidance” does not identify the matching workflow sections and describes their scope asymmetrically. The existing workflow has inspection content in Rental §2.4 and Resale §3.4.  
   **Recommendation:** Name both sections and state that both will document the contact snapshot and broker-link rule.

6. **Severity:** Nit  
   **Affected scope:** Detailed Implementation Plan, line 200.  
   **Style rationale:** “No source file or database column will be deleted” is a broad statement unrelated to the specific history-preservation requirement. The second sentence repeats the `observed_defects` preservation instruction at line 183.  
   **Recommendation:** Remove the broad sentence and retain one precise statement that the existing `observed_defects` column and values remain.

## Access and read status

- `agents/style.md`: read in full; no separate style standard was supplied.
- `apartment-inspection-contact-capture.md`: lines 175–215 read.
- `concepts_brief.md`: relevant sections on the snapshot, exact broker link, audit fields, rollback, and workflow paths read.
- `DECISIONS.md`, `PRD.md`, `DATA_MODEL.md`, `WORKFLOWS.md`, `PERMISSIONS.md`, `ACCEPTANCE_TESTS.md`, and `CURRENT_STATE.md`: searched and relevant matching passages read; no access failures.

No files other than this review report were modified. No tests were run.