## design_draft.md

- Browser acceptance remains unverified: no isolated Chrome DevTools browser is configured in this session, so the six viewport sizes, modal scroll stability, AM/PM selection, keyboard/dismissal behavior, unsupported-browser fallback, and Firefox 125+/Safari 17+ release gates have not been exercised in a real browser. Run that matrix in an authenticated test session before treating visual fit and interaction behavior as verified; PHP tests and the Vite build do not prove those browser outcomes.
