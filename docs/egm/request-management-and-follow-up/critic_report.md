# Stage 7 — Critic Review

**Target:** `design.md`, FeatureRange lines 1–176
**Context read:** project README and current-state, decisions, PRD, workflows, data model, acceptance, operations, and feature concepts brief.
**Reviewer status:** `egm-design` completed; `egm-structural` completed; `egm-style` completed.

## Findings

### 🟡 Major — Notification delivery needs explicit backlog and volume behavior

**Affected scope:** Notification design and Non-Functional Requirements (approximately lines 95–119 and 169–175).

The design requires minute-by-minute catch-up and escalation to every authorized department manager, but does not state expected workload bounds, how due work is claimed in bounded batches, or what operators should monitor when backlog grows. Persistent notifications and downtime recovery can amplify work after scheduler downtime.

**Reviewer:** `egm-structural`

### 🟡 Major — Legacy follow-up migration and lifecycle may leave history ambiguous

**Affected scope:** Follow-up cases and migration plan (approximately lines 91 and 172).

The reviewer asks for explicit handling of activities that cannot be assigned to a specific request, legacy cases after a customer later gets a request, and customers linked to already-closed requests. The target does describe some of these transitions; this finding needs cross-stage reconciliation against the exact migration and service clauses before being treated as an open defect.

**Reviewer:** `egm-design`

### 🟡 Major — Household history preservation needs a precise migration invariant

**Affected scope:** Request fields and migration plan (approximately lines 79 and 174).

The reviewer asks that the old Rental household value be preserved without inferring a category from `false`, and that new category nullability and validation be explicit. The target already says to keep the old boolean as historical data, avoid inference, and make the new category nullable; final synthesis should determine whether an explicit “do not drop or rewrite the column” clause is still needed.

**Reviewer:** `egm-design`

### 🟡 Major — Test campaign sources need stable identifiers

**Affected scope:** Test-campaign source cleanup and migration plan (approximately lines 81 and 174).

The design says to deactivate only the exact test-campaign rows, preserving historical references, but does not give their catalog names or stable keys. An implementer cannot safely identify the intended rows from the design alone.

**Reviewer:** `egm-design`; also raised by `egm-comprehension`.

### 🟡 Major — Existing acceptance guidance may conflict with the closure rule

**Affected scope:** Unsuccessful closure requirement and project acceptance documentation (approximately line 83).

The reviewer reports that existing acceptance scenarios still require free-text closure reason and treat classification as optional, while the proposed behavior reverses those requirements. The plan should explicitly supersede or update the conflicting acceptance scenarios before implementation. The decision record identifies `D-091`, but the design does not cite it in the closure section.

**Reviewer:** `egm-design`

### 🟡 Major — Rejection rationale should not rely on unattributed author decisions

**Affected scope:** Alternatives Considered (approximately lines 137–167).

Several rationales say “Rejected by author” or “owner selected.” The feature-spec standard disallows attributing a rejection to a maintainer without a linked discussion, issue, or PR. Cite the applicable decision record or state the technical and scope trade-off directly without relying on personal attribution.

**Reviewer:** `egm-design`

### 🟡 Major — Escalation recipient terms conflict

**Affected scope:** Goals; notification architecture and detailed implementation plan (approximately lines 26, 97, and 227).

The goal says “the responsible manager,” but the implementation sends the alert to every manager authorized for the department. These are different recipient scopes and may produce different notification volumes. State whether all authorized managers are intentionally notified or define a specific responsible recipient.

**Reviewer:** `egm-style`; the Stage 5 inventive review also noted the ambiguity.

### 🟢 Nit — Problem statement repeats its effect summary

**Affected scope:** The Problem (approximately lines 13–17).

The concluding “Without these changes” sentence repeats the concrete gaps and speculates about their impact. Keep the specific workflow gaps and remove or shorten the repeated summary.

**Reviewer:** `egm-style`

### 🟢 Nit — Notification persistence is described twice

**Affected scope:** In-app notifications and Selected notification persistence (approximately lines 97–98 and 127–129).

The later subsection repeats the channel, table, key, owner, due date, and read-state details. Keep one canonical description and use the other section only for additional decisions.

**Reviewer:** `egm-style`

### 🟢 Nit — Overview wording is broad

**Affected scope:** Overview (approximately line 9).

“Shared request progression and budget language” is less precise than “shared request stages and budget labels.” “Work that needs attention” could name the notification events listed later.

**Reviewer:** `egm-style`

### 🟢 Nit — Stage display labels are incomplete

**Affected scope:** Technical Plan and implementation sequence (approximately lines 83 and 186).

The stored values are named and the initial label is given, but the approved Arabic labels for recommendation, viewing, and negotiation are not stated.

**Reviewer:** `egm-comprehension`

### 🟢 Nit — `D-084` requirement is not self-contained

**Affected scope:** Implementation sequence, step 7 (approximately line 236).

The design says to retain table names required by `D-084` without identifying the names or linking to the decision. A fresh implementer cannot determine the requirement from the target alone.

**Reviewer:** `egm-comprehension`

## Reviewer notes

- `egm-design`: completed; six findings.
- `egm-structural`: completed; one finding, with no heading or Markdown-structure issue.
- `egm-style`: completed; one major and three nits.
- No tests were run.
