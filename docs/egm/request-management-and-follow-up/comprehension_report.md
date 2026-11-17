# Stage 6 — Comprehension Test

## What this proposes

The design keeps Rental and Sale request records separate while standardizing their categories, stages, budgets, and shared workflow. It adds scheduled pause and resume handling, appointment attempts with separate scheduling states and results, and customer follow-up cases tied to individual requests. It also adds broker request history, in-app notifications, and forward-only migrations to preserve existing data.

## How the existing system works, per this document

Rental and Sale requests have separate forms and stored values; Rental has a household flag, while Sale lacks several of the proposed fields. Existing stages and budget labels do not match the intended workflow. Viewings require an agreed time, appointment changes are mixed with results, broker pages show request counts but not the requests, customer follow-up uses one profile per customer, and there is no central in-app notification workflow.

## Findings

### 🟡 Major — Test-campaign lead sources are not identified

**Affected scope:** Technical Plan; implementation sequence, steps 2 and 7.

The plan says to remove or deactivate two “named” or “exact” test-campaign sources but does not give their catalog names or stable keys. An implementer cannot determine which rows the migration should target.

### 🟢 Nit — Three stage display labels are not stated

**Affected scope:** Technical Plan; implementation sequence, step 2.

The document names the four stored stages and gives the initial stage's Arabic label, then says to add Arabic labels for all four. It does not state the approved display labels for recommendation, viewing, and negotiation.

### 🟢 Nit — `D-084` is not explained or linked

**Affected scope:** Implementation sequence, step 7.

The plan says to retain table names required by `D-084`, but the target does not identify those names or link to the decision. A reader cannot determine the requirement from this document alone.

## Reviewer status

`egm-comprehension`: completed. The reviewer read only the persona and target design. No tests were run.
