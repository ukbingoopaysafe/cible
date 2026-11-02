---

name: Agent Skills Bridge
description: Loads the matching SKILL.md from .agents/skills/ for the task at hand
globs: ["**/*"]
alwaysApply: true
---

# Agent Skills

This project ships a library of engineering workflow skills at `.agents/skills/`.
Continue does not read that folder on its own, so this rule is the bridge.

## How to use them

Before starting any non-trivial task, pick the skill whose trigger matches the
task and **read its `SKILL.md`** with your file-reading tool, then follow its
steps. A skill is a process, not a suggestion — follow it in order and don't skip
its verification step.

Path pattern: `.agents/skills/<skill-name>/SKILL.md`

Load at most 1–3 skills per task. Loading all of them wastes context and several
of them conflict with each other (for example `caveman` says "compress output"
while `humanizer` says "add natural context"). Choose deliberately.

## Task → skill map

| When the task is... | Read this skill |
|---------------------|-----------------|
| New feature / project, no spec yet | `spec-driven-development` |
| Requirements unclear, vague ask | `interview-me` |
| Refining a rough idea | `idea-refine` |
| Break a spec into tasks | `planning-and-task-breakdown` |
| Writing code, any feature | `karpathy-guidelines` |
| Implementing a multi-file change | `incremental-implementation` |
| Building or fixing UI (Blade, Tailwind, JS) | `frontend-ui-engineering` |
| UI needs to look production-grade, not AI slop | `impeccable` |
| Design tokens, component design | `design-system` |
| Accessibility / WCAG | `frontend-ui-engineering` |
| Writing or running tests | `test-driven-development` |
| Something broke, failing test, error | `systematic-debugging` |
| Hard bug, perf regression, unknown cause | `diagnosing-bugs` |
| Reviewing a diff / PR | `code-review-and-quality` |
| Code is too complex, refactor for clarity | `code-simplification` |
| Security: user input, auth, OWASP | `security-and-hardening` |
| Performance work | `performance-optimization` |
| Committing, branching, PR | `git-workflow-and-versioning` |
| Doc / ADR / decision record | `documentation-and-adrs` |
| Logging / metrics / tracing | `observability-and-instrumentation` |
| DB schema / migration | `migration` |
| API / interface design | `api-and-interface-design` |
| About to claim "done" | `verification-before-completion` |

## Cross-cutting behaviors (apply always)

These come from `using-agent-skills` and apply no matter which skill is active:

- **Surface assumptions** before implementing anything non-trivial. State them,
  then proceed.
- **Manage confusion actively** — if requirements conflict or are unclear, stop
  and ask instead of guessing.
- **Push back when warranted** — if an approach has a concrete downside, say so
  and propose an alternative. Don't be a yes-machine.
- **Enforce simplicity** — prefer the boring, obvious solution. Cleverness is
  expensive.
- **Maintain scope discipline** — touch only what the task requires. No unsolicited
  refactoring or "cleanup" of adjacent code.
- **Verify, don't assume** — a task isn't done until there's evidence (passing
  tests, build output, runtime behavior). "Seems right" is not done.

## What NOT to do

Do not try to satisfy every skill in the library at once. The `.agents/rules.md`
file's "apply all 148" list is not actionable and several entries contradict each
other. Use the map above, load what fits, and move on.