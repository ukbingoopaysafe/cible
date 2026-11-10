## What this proposes

The design standardizes date and time presentation across existing CRM pickers, displays, reports, and exports, using the browser’s first `Accept-Language` range to choose Arabic or English presentation while keeping the CRM shell Arabic RTL and preserving Cairo timezone behavior. A request-scoped locale resolver supplies the locale to Filament pickers and a shared server-side presenter; each initial page and Livewire request resolves it anew. Queued XLSX exports capture that locale in their options, then use it to format typed date and time cells consistently across chunks and retries.

## How the existing system works, per this document

The document describes inconsistent date and time presentation, including 24-hour time on existing CRM surfaces, and says the current customer and request export actions produce XLSX files. The proposed rendering flow treats initial page loads and Livewire updates as separate requests: the browser supplies `Accept-Language` on both, and the resolver reads its first range while Laravel’s global locale stays Arabic. For exports, the installed XLSX path reads intermediate CSV rows as strings, so the plan rehydrates allowlisted date and time columns as typed cells and applies formats from the locale snapshot forwarded through Filament’s queued jobs.

## Findings

None.
