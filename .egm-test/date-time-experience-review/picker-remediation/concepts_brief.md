# Date and Time Picker Popup Remediation

* **Author:** ukbingoopaysafe
* **Date:** 2026-11-10

## Overview

The calendar popup still breaks inside Filament modals: its weekday labels and calendar description overlap, the panel can be clipped, and the modal may move while the popup opens. The current code moves Filament's panel to `document.body`, which removes it from the ancestor context used by Filament's calendar styles and forces custom positioning and event-containment patches.

The reviewed direction is to leave the original panel under its Filament picker root and promote it to the browser top layer with the HTML Popover API. The panel stays opaque and viewport-positioned, while the browser lifts it above modal clipping. Filament remains the owner of calendar selection, keyboard navigation, validation, and canonical time state; Popover visibility follows Filament's existing open state.

## Analysis

Filament's installed stylesheet scopes essential panel layout rules beneath `.fi-fo-date-time-picker`. The panel loses that ancestor when `date-time-picker-portal.js` appends it to `document.body`. The latest screenshot shows the resulting visible calendar description, crowded weekday labels, and controls overlapping the form. The current portal also replaces Filament's positioning method and changes `contains()` behavior for modal and picker nodes.

The Popover API places an open element in the browser top layer without moving it in the DOM, so Filament's scoped styles and Alpine ancestry remain. Use `popover="manual"`; Filament's `isPanelOpen` is the source of truth. Opening shows the popover only after Alpine reveals the panel, then places it against the trigger. Closing hides the popover before Filament conceals the panel. Trigger toggle, date selection, Escape, outside click, focus leaving the picker, modal close, Livewire navigation, and field teardown must all leave both states closed. A click inside, including AM/PM and time-option selection, must not close it.

The supported browser floor is Chrome/Edge 114+, Firefox 125+, and Safari 17+. Mozilla's Firefox 125 notes identify that release as full Popover API support; Firefox 114–124 are fallback-only. Older browsers are outside the no-clipping baseline; feature detection must avoid runtime exceptions and keep Filament's original in-tree panel usable. The installed Cible UI plan targets recent Chrome and Firefox on Windows and Safari/iOS when available. On supported browsers, use an 8 CSS-pixel viewport inset, align to the trigger's inline start, prefer below when it fits and above otherwise, then clamp the full panel inside the visible viewport. At viewport heights below 420px, use compact dimensions: 8px panel padding, 32px month/year controls, 22px calendar rows with 2px gaps, 16px weekday line height, and 28px time controls. At 360x320, including a six-week month, the compact panel must measure at most 304px high. Do not add scrolling to the calendar surface. The hour/minute option list may scroll independently when its full option set exceeds the available height.

Success means the popup is opaque and fully visible, has no scrollbar on the calendar surface, keeps the modal's scroll position fixed, and lays out month/year fields, seven weekday columns, calendar days, and time controls consistently. Clicking AM or PM updates the selected period without closing the popup. Hour and minute text entry, keyboard calendar navigation, accessibility labels, and Filament's canonical state continue to work. Date-time state remains `Y-m-d H:i` in `Africa/Cairo`; date-only state remains `Y-m-d`; `12 AM` maps to hour `00`, `12 PM` to `12`, and changing AM/PM changes only the canonical hour.

## Goals vs. Non-Goals

* **In-Scope Goals:** Fix calendar-bearing Filament `DatePicker` and `DateTimePicker` fields (`hasDate=true`), including date-only, date-time, and seconds-enabled variants, wherever they appear in a page or modal. Keep the existing 12-hour hour/minute/period controls and the numeric seconds control when enabled. At 360x320, 360x640, 390x844, 768x1024, 1280x720, and 1440x900 CSS-pixel viewports, keep the complete panel inside the visible viewport with an 8px inset; cap width at `min(16rem, viewport width - 16px)`; use 40px month/year controls in normal density and equal seven-column day rows with no overlapping labels. At 360x320, compact dimensions must fit a six-week month in 304px maximum panel height. Opening the panel must not change modal scroll. Keep the calendar surface free of scrolling; allow only the hour/minute option list to scroll if needed. Preserve 12-hour entry, AM/PM behavior, Filament validation, disabled dates, Cairo timezone, and canonical state. Verify pointer, keyboard, focus, and placement behavior in a real browser.
* **Out-of-Scope Non-Goals:** Change database values, APIs, timezone handling, locale policy, exports, business rules, or Arabic CRM labels; change time-only pickers (`hasDate=false`), which retain Filament's existing floating path; introduce a separate calendar package; redesign other modal content.

## Alternatives Weighed

* **Keep the current body portal and recreate all Filament styles and containment rules.** Rejected for this remediation because the latest screenshot still shows missing scoped layout behavior, and the portal requires custom positioning plus patched containment around modal focus handling.
* **Keep the panel in the modal and raise its z-index or relax overflow.** Rejected because z-index does not escape ancestor clipping, while changing modal overflow can affect its scroll behavior.
* **Use a separate calendar component or package.** Rejected because it would replace Filament's established date navigation, disabled-date rules, keyboard behavior, and state integration for a popup-layout defect.
* **Promote the existing in-tree panel to the browser top layer with the Popover API.** Preferred because it preserves DOM ancestry and escapes overflow clipping. Trade-off: it adds a small state-sync adapter and sets a minimum browser baseline; older browsers use the in-tree Filament fallback without the no-clipping guarantee.

## Key Decisions & Staging for Next Steps

* Keep Filament's picker as the owner of date selection and canonical time state.
* Keep the panel node inside its original picker root; do not reparent it to `document.body` or monkey-patch `contains()`.
* Keep Filament's `isPanelOpen` as the source of truth. Synchronize the manual popover idempotently across open/close paths; remove the blanket panel click-stop guard so normal event ancestry is restored.
* Use a solid light surface, a 16rem maximum width, 40px aligned month/year controls, seven equal date columns, and a scroll-free calendar surface. Position against the visual viewport with an 8px inset, RTL-aware inline alignment, below/above placement, and a compact density mode for short viewports.
* Preserve canonical `Y-m-d` and `Y-m-d H:i` state and `Africa/Cairo`; explicitly test noon/midnight, AM/PM, typed time, disabled dates, and seconds-enabled fields.
* Test supported browsers Chrome/Edge 114+, Firefox 125+, and Safari 17+; Firefox 114–124 are fallback-only. Detect missing Popover support and retain Filament's in-tree fallback without throwing.
* Verify page and modal fields at 360x320, 360x640, 390x844, 768x1024, 1280x720, and 1440x900, including panel bounds, equal grid columns, no calendar overflow, unchanged modal scroll, internal click behavior, Escape, outside click, focus, and Livewire teardown.
* Independent review completed with seven fresh-context Goldfish. The review found no Blocker; the Major lifecycle, browser-floor, viewport, scope, and test gaps above were resolved in this brief.
* Preserve all unrelated worktree changes.
