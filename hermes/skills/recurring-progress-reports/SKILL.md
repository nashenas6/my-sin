---
name: recurring-progress-reports
description: "Use when asked for periodic status updates during long work."
version: 1.0.0
author: Sink
license: MIT
metadata:
  hermes:
    tags: [reporting, scheduling, workflow]
    related_skills: []
---

# Recurring Progress Reports

## When to Use

Use when the user asks for status every N minutes or hours while long work continues in the session.

## Procedure

1. Write a read-only status script that prints: recent VCS history, working-tree state, and a done/pending checklist derived from commit messages.
2. Make the script executable and schedule it as a no-agent recurring job at the requested interval, delivered to the originating chat and scoped to the work directory.
3. Confirm the next run time, then keep doing the main work — the reporter is fire-and-forget.
4. Remove or pause the job when the work completes so reports do not outlive the task.

## Rules

- Keep the reporter strictly read-only — a reporting tick that mutates the repo races the main work and corrupts both.
- Derive done/pending from verifiable VCS state (commit messages referencing task IDs, tree status), not from memory of intent — memory of intent drifts, history does not.
- Keep each report compact: done items, current item, remaining items, last commit plus tree state; match the user's language.
- Prefer deterministic script output over an LLM-written summary each tick — it is cheaper and stays consistent across ticks.
