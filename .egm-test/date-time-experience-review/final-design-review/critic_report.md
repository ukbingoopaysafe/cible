# Stage 7 — Critic Review (FeatureRange 1–113)

All three requested fresh-context Goldfish reviewers completed. Each received the feature-spec standard and approved GoldfishContext with `fork_turns: "none"`. No reviewer modified files or ran tests/builds.

## `egm-design` — `/root/egm_final_stage7_design` — completed

### design-1-1 — Major: 12-hour selection control

**Affected scope:** Target lines 63, 67, 99; installed `DateTimePicker.php` line 338.

The installed picker’s hour input accepts 0–23 and has no AM/PM control. Changing `displayFormat` can show a 12-hour value, but does not change how users select a time. The spec offers a custom wrapper only if a compatibility check finds a limitation; the installed source already shows one. Select and describe a 12-hour input path that converts to the existing `Y-m-d H:i` state, including noon and midnight. The conditional fallback is a reasonable approach, but it needs to be made concrete before the design is finalized.

### design-1-2 — Major: regional picker locale mapping

**Affected scope:** Target lines 47–48, 57, 63; installed picker JS lines 37, 89, 958–969.

The resolver is meant to preserve regional tags such as `ar-EG` and pass the locale to Filament. The installed picker looks up exact locale keys; it bundles `ar` and `en`, but not `ar-EG` or `en-GB`. An `ar-EG` value therefore falls back to English calendar labels. Add a picker-specific mapping to supported locale bundles, while retaining the full regional locale for server formatting and date order.

### design-1-3 — Major: typed export conversion path

**Affected scope:** Target lines 71–73, 80; `FormatsSpreadsheetData.php` lines 12–24, 81; `RentalRequestExporter.php` lines 62, 87–88.

The spec relies on a “shared exporter row-conversion seam” to turn temporary CSV strings back into typed cells, but the listed shared trait has no row conversion or XLSX cell-formatting hook; it contains column helpers, text sanitization, and completion messaging. Name the actual Filament/OpenSpout integration point and specify how the locale option reaches it across chunks and retries. Preserve the typed-cell goal, but establish a feasible writer path and an allowlist that distinguishes date-only values from timestamps.

**Read limitations reported:** None of the requested paths were inaccessible. Some long combined outputs were truncated; targeted reads/searches were used for date/time-relevant sections.

## `egm-structural` — `/root/egm_final_stage7_structural` — completed

### structural-1-1 — Major: export conversion hook is unclear

**Affected scope:** Target “Exports,” lines 71–73; “Existing code touchpoints,” line 80.

The design says to extend a shared “row-conversion seam,” but the named `FormatsSpreadsheetData` trait currently formats export-column values and text; it does not convert rows or identify where Filament’s CSV-to-XLSX path can rehydrate typed date cells. Name the writer/job extension point and the data passed through it so an engineer can follow this central part of the design.

**Additional structural assessment:** All mandatory feature-spec sections are present, heading levels are consistent, and no Markdown formatting defects were found. No assigned file was reported inaccessible; several long context documents had truncated output, with focused date/time searches used instead.

## `egm-style` — `/root/egm_final_stage7_style` — completed

### style-1-1 — Major: locale precedence wording

**Affected scope:** Target lines 9, 17, 42, 57, 96–98.

“Primary preferred locale” suggests preference weights determine priority, but the resolver explicitly uses the first `Accept-Language` range regardless of `q` values. Define the rule as “first listed range” throughout, or specify how weights affect selection.

### style-1-2 — Major: PHP Intl requirement is conditional

**Affected scope:** Target Architecture, line 57.

The presenter “will use” PHP Intl, but the runtime requirement is conditional (“if this formatter is selected”). State whether Intl is the chosen dependency and make its deployment requirement unconditional, or present it as an option.

### style-1-3 — Major: spreadsheet compatibility scope is undefined

**Affected scope:** Target Exports and Alternatives, lines 73, 90, 101, 108, 111.

“Supported” or “target” spreadsheet applications are not named, so the export verification condition has no defined scope. Specify the applications and versions the acceptance checks cover.

### style-1-4 — Major: queued options claim needs source verification

**Affected scope:** Target Exports, line 71.

The statement that Filament passes the options payload through preparation, chunk, and XLSX jobs is a specific implementation claim. Cite or verify it against the pinned Filament version, or phrase it as an assumption to confirm.

### style-1-5 — Nit: subjective and repeated UX copy

**Affected scope:** Target Date/Time Picker UX and Visual Direction, lines 27–29.

“Distinctive, elegant, modern, effortless,” and “polished” are subjective and do not define reviewable outcomes. The passage repeats focus, contrast, keyboard, screen-reader, and narrow-width requirements already stated in Goals and Verification. Retain concrete interaction and accessibility criteria and remove duplicated and subjective copy.

### style-1-6 — Nit: inconsistent decision labels

**Affected scope:** Target Alternatives Considered, lines 96, 99–101.

The decision column mixes “Selected” and “Preferred.” Since the design is marked finalized, use one term to show which approaches are commitments.

**Read limitations reported:** Persona, standard, target FeatureRange, and several context files were read in full. The reviewer used relevant excerpts for long context files whose full outputs were truncated; no requested paths were inaccessible.

## Stage 7 summary

Eight raw Major findings and two Nits were returned across the three critics; the export conversion concern appears independently in design and structural reviews. Review findings are recorded as reported. No repairs were applied to the finalized design.
