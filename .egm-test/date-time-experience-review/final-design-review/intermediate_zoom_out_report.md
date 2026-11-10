# Stage 8 — Intermediate Zoom-Out Review

**Reviewer:** `egm-zoom-out` (`/root/egm_final_stage8_zoom_out`)
**Context:** Full target, Stage 5 report, Stage 6 comprehension report, Stage 7 critic report, and approved project context
**Isolation:** Spawned with `fork_turns: "none"`
**Completion:** Native subagent returned findings.

## Findings

- **Severity: Major** — **Affected scope:** Overview and Goals; Exports; Detailed Implementation Plan. The design makes locale-aware XLSX conversion part of the global rollout, including queued locale snapshots and typed-cell formatting. The stated feedback concerns scheduling confusion and inconsistent presentation; export-specific use or pain is not established. The new export requirements make the work implementable, but do not establish that this added compatibility burden is needed to solve the reported problem. Defer localized typed-cell conversion until export users confirm that need.

- **Severity: Nit** — **Affected scope:** Technical Plan → Request and component behavior; `theme.css` touchpoint. The app already has `.bidi-isolate` and `[dir="ltr"]` isolation rules. State explicitly that formatted values reuse these hooks; add only picker-specific direction behavior that the existing rules cannot provide.

- **Severity: Nit** — **Affected scope:** Detailed Implementation Plan. The design still lists individual files, test class names, and a step-by-step implementation order. Those details belong in an execution plan; keeping them here makes the architecture harder to scan.

The earlier ambiguity about export feasibility is substantially resolved: Filament exposes `Exporter::makeXlsxRow()` and carries exporter options into the XLSX job, while Cible already has a shared exporter concern. The design should name that seam directly.

## Read limitations

No assigned paths were blocked. The reviewer reported that `docs/DECISIONS.md` was not read; `docs/CURRENT_STATE.md` and `docs/ACCEPTANCE_TESTS.md` were only partially read because of output truncation. `DateTimePicker.php`, `RequestFormSchema.php`, `SectionRequestResource.php`, `CustomerResource.php`, and `CustomerFollowUpResource.php` were inspected through relevant excerpts or searches rather than read fully.
