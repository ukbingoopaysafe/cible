---
name: Load Skill
description: Load and follow a skill from .agents/skills/ by name
invokable: true
---

Load the skill named `{{ input }}` and follow it for the current task.

Steps:
1. Read `.agents/skills/{{ input }}/SKILL.md`.
2. If it references files in its `references/`, `scripts/`, or `agents/`
   subfolders, read the ones relevant to this task.
3. Follow the skill's steps in order for the work at hand.
4. Report which skill you loaded and what steps you're taking.

If the skill folder does not exist, list the available skills by running:
`Get-ChildItem .agents\skills -Directory | Select-Object Name`
and ask which one I meant.