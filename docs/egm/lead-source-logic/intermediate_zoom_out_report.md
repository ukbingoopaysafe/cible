# Stage 8 — Intermediate Zoom Out Review

* **Target**: `lead-source-logic.md`
* **Range**: Full file, lines 1–260
* **Reviewer**: `zoom_out` completed.
* **Focus**: System-level impact of the comprehension and critic findings.

## Findings

### Major

#### `zoom_out-2-1` — Legacy source types can return to new request choices

The design excludes call, visit, WhatsApp, and unclassified legacy values from new requests (`lead-source-logic.md:81–84, 223, 228`). However, the form plan admits active sources and managers can reactivate inactive rows (`app/Filament/Support/LeadSourceResource.php:68–71`). The service plan does not explicitly reject `legacy_contact_method` and `unclassified_legacy` on new requests or changed source selections. Reactivating one could restore the contact-method values the design removes and undermine the central source meaning.

**Recommended remedy**: Enforce the allowed types (`awareness`, `person_referral`, `other`) on the server for new or changed selections; preserve unchanged historical assignments and align the form and activation behavior.

#### `zoom_out-2-2` — The governing catalog decision still conflicts with the design

The design removes phone call, direct visit, and WhatsApp from new choices (`lead-source-logic.md:11, 26, 113`), while `docs/DECISIONS.md:71` still lists them as approved initial values. `AT-158` still refers to that list (`docs/ACCEPTANCE_TESTS.md:240`). This remains unresolved after Stage 5.

**Recommended remedy**: Supersede `D-051` with the awareness-versus-contact-method rule and update `AT-158` to match.

### Nit

#### `zoom_out-2-3` — `other` has unclear scope and duplicate state

The plan defines `other` as the built-in «أخرى» choice, permits managers to add new `other`-typed choices, and keeps `is_other` synchronized with `type` (`lead-source-logic.md:80, 84, 193, 218`). That leaves both manager-created behavior and the source of truth for description requirements unclear.

**Recommended remedy**: Reserve `other` for the built-in row and make its type the sole behavior source, or define the behavior for additional `other` options and justify retaining duplicate state.

No other system-level gap was identified by this stage.
