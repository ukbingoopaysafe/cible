# Stage 7 — Critic Review

* **Target**: `lead-source-logic.md`
* **Range**: FeatureRange, lines 1–183
* **Reviewers**: `design_critic`, `structural`, and `style` completed.

## Findings

### Major

#### `design_critic-1-1` — Reactivated legacy source types can become selectable for new requests

The design says legacy contact methods and unclassified rows are excluded from new choices (`lead-source-logic.md:81–84`), but the detailed form plan only says to show active sources (`:146`) and the service plan does not explicitly restrict new selections to `awareness`, `person_referral`, or `other` (`:151`). Managers can reactivate inactive sources. A reactivated `legacy_contact_method` could therefore appear for new requests unless the type is checked on the server.

**Recommended remedy**: Define the allowed types for new requests and newly changed source selections, enforce the predicate in `RequestService`, and specify how activation of legacy types is handled while preserving unchanged historical selections.

#### `style-1-1` — Manager-created `other` sources conflict with the built-in «أخرى» definition

The type table defines `other` as the built-in «أخرى» source, while the manager workflow permits choosing `other` for new sources (`lead-source-logic.md:76–84`). The form and migration entries do not define whether manager-created `other` sources require descriptions or how they relate to `is_other` (`:146, 151`).

**Recommended remedy**: Reserve `other` for the built-in «أخرى» row, or define how additional `other`-typed options behave and are validated.

### Nits

#### `style-1-2` — Referral requiredness wording varies

The document alternates between “new person referral” and “new or newly changed person referrals” (`lead-source-logic.md:88, 122, 127`). This leaves unclear whether changing an existing request to a person-referral source triggers the name-or-unknown requirement.

**Recommended remedy**: Use one rule throughout, such as “a new or changed selection of a `person_referral` source requires a name or explicit unknown choice,” and state the exception for unchanged historical null states.

#### `style-1-3` — Rollback guidance is repeated

The forward-only policy and older-application limitation recur across several sections (`lead-source-logic.md:115, 132, 178`).

**Recommended remedy**: Keep the full policy in the rollout section and trim repeated statements, retaining only section-specific migration details.

#### `structural-1-1` — Scalability limits are not addressed

The non-functional section covers privacy, department isolation, and rollout but does not state whether scale creates a material risk (`lead-source-logic.md:174–178`).

**Recommended remedy**: State any relevant scale bound and mitigation, or briefly explain why this feature adds no material scalability concern.

#### `structural-1-2` — “Additional Resources” is nested under non-functional requirements

The “Additional Resources” heading is an H3 nested under `Non-Functional Requirements`, although it is a peer document section (`lead-source-logic.md:180–182`).

**Recommended remedy**: Promote it to an H2 or move the link outside the non-functional section.
