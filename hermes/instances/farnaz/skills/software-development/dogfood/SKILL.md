---
name: dogfood
description: "Exploratory QA of web apps: find bugs, evidence, reports."
version: 1.0.0
author: Teknium (teknium1), Hermes Agent
license: MIT
platforms: [linux, macos, windows]
metadata:
  hermes:
    tags: [qa, testing, browser, web, dogfood]
    related_skills: []
---

# Dogfood: Systematic Web Application QA Testing

## Overview

This skill guides you through systematic exploratory QA testing of web applications using the browser toolset. You will navigate the application, interact with elements, capture evidence of issues, and produce a structured bug report.

## Prerequisites

- Browser toolset must be available (`browser_navigate`, `browser_snapshot`, `browser_click`, `browser_type`, `browser_vision`, `browser_console`, `browser_scroll`, `browser_back`, `browser_press`)
- A target URL and testing scope from the user

## Inputs

The user provides:
1. **Target URL** — the entry point for testing
2. **Scope** — what areas/features to focus on (or "full site" for comprehensive testing)
3. **Output directory** (optional) — where to save screenshots and the report (default: `./dogfood-output`)

## Phase 0: Confirm the App Serves, and Get an Authenticated Session

1. **Prove the app answers before blaming your automation.**
   `curl -s -o /dev/null -w '%{http_code}\n' <url>`. A 500 is a server defect, not a
   UI finding: report it, fix the environment, then start the QA pass.
2. **Read the FIRST error, not the last.**
   `grep -nE 'local\.(ERROR|CRITICAL)' storage/logs/laravel.log | head -5`. Laravel's
   later entries are usually cascading `Cannot modify header information - headers
   already sent`, which names the symptom and never the cause. For this stack the
   classic first error is a missing key — "No application encryption key has been
   specified" → `php artisan key:generate` (generate one per clone; never copy a key
   out of another env file or repo).
3. **Behind a login wall, get a session without handling credentials.** Check
   `browser_vault` (action=list) first. If nothing is saved there, do not type or ask
   for a password: for a **local** app, bootstrap the session server-side — see
   `templates/laravel-probe-login-route.php` for the temporary, environment-gated,
   nonce-guarded route, the curl verification, and the cleanup commands.
4. **A new route 404s while `bootstrap/cache/routes-v7.php` exists.** Run
   `php artisan route:clear` before pointing the browser at the probe URL.
5. **Log in as an account that already exists** (admin, so every menu and gate is
   reachable) rather than inventing one. If a throwaway account is unavoidable, give
   it a fixed name prefix so cleanup is one DELETE, and remove it before reporting.
6. **Clean up and prove it.** Delete the probe route, `php artisan route:clear`, then
   `git status --short` — only pre-existing modifications may remain, and the report
   should say so.

## Workflow

Follow this 5-phase systematic workflow:

### Phase 1: Plan

1. Create the output directory structure:
   ```
   {output_dir}/
   ├── screenshots/       # Evidence screenshots
   └── report.md          # Final report (generated in Phase 5)
   ```
2. Identify the testing scope based on user input.
3. Build a rough sitemap by planning which pages and features to test:
   - Landing/home page
   - Navigation links (header, footer, sidebar)
   - Key user flows (sign up, login, search, checkout, etc.)
   - Forms and interactive elements
   - Edge cases (empty states, error pages, 404s)

### Phase 2: Explore

For each page or feature in your plan:

1. **Navigate** to the page:
   ```
   browser_navigate(url="https://example.com/page")
   ```

2. **Take a snapshot** to understand the DOM structure:
   ```
   browser_snapshot()
   ```

3. **Check the console** for JavaScript errors:
   ```
   browser_console(clear=true)
   ```
   Do this after every navigation and after every significant interaction. Silent JS errors are high-value findings.

4. **Take an annotated screenshot** to visually assess the page and identify interactive elements:
   ```
   browser_vision(question="Describe the page layout, identify any visual issues, broken elements, or accessibility concerns", annotate=true)
   ```
   The `annotate=true` flag overlays numbered `[N]` labels on interactive elements. Each `[N]` maps to ref `@eN` for subsequent browser commands.

5. **Test interactive elements** systematically:
   - Click buttons and links: `browser_click(ref="@eN")`
   - Fill forms: `browser_type(ref="@eN", text="test input")`
   - Test keyboard navigation: `browser_press(key="Tab")`, `browser_press(key="Enter")`
   - Scroll through content: `browser_scroll(direction="down")`
   - Test form validation with invalid inputs
   - Test empty submissions

6. **After each interaction**, check for:
   - Console errors: `browser_console()`
   - Visual changes: `browser_vision(question="What changed after the interaction?")`
   - Expected vs actual behavior

### Phase 3: Collect Evidence

For every issue found:

1. **Take a screenshot** showing the issue:
   ```
   browser_vision(question="Capture and describe the issue visible on this page", annotate=false)
   ```
   Save the `screenshot_path` from the response — you will reference it in the report.

2. **Record the details**:
   - URL where the issue occurs
   - Steps to reproduce
   - Expected behavior
   - Actual behavior
   - Console errors (if any)
   - Screenshot path

3. **Classify the issue** using the issue taxonomy (see `references/issue-taxonomy.md`):
   - Severity: Critical / High / Medium / Low
   - Category: Functional / Visual / Accessibility / Console / UX / Content

### Phase 4: Categorize

1. Review all collected issues.
2. De-duplicate — merge issues that are the same bug manifesting in different places.
3. Assign final severity and category to each issue.
4. Sort by severity (Critical first, then High, Medium, Low).
5. Count issues by severity and category for the executive summary.

### Phase 5: Report

Generate the final report using the template at `templates/dogfood-report-template.md`.

The report must include:
1. **Executive summary** with total issue count, breakdown by severity, and testing scope
2. **Per-issue sections** with:
   - Issue number and title
   - Severity and category badges
   - URL where observed
   - Description of the issue
   - Steps to reproduce
   - Expected vs actual behavior
   - Screenshot references (use `MEDIA:<screenshot_path>` for inline images)
   - Console errors if relevant
3. **Summary table** of all issues
4. **Testing notes** — what was tested, what was not, any blockers

Save the report to `{output_dir}/report.md`.

## Tools Reference

| Tool | Purpose |
|------|---------|
| `browser_navigate` | Go to a URL |
| `browser_snapshot` | Get DOM text snapshot (accessibility tree) |
| `browser_click` | Click an element by ref (`@eN`) or text |
| `browser_type` | Type into an input field |
| `browser_scroll` | Scroll up/down on the page |
| `browser_back` | Go back in browser history |
| `browser_press` | Press a keyboard key |
| `browser_vision` | Screenshot + AI analysis; use `annotate=true` for element labels |
| `browser_console` | Get JS console output and errors |

Use whichever browser tool the environment actually exposes — for example
`browser_exec(code=...)` with pre-imported helpers (`new_tab`, `goto_url`, `js`,
`page_info`, `wait_for_load`, `fill_input`). Work **text-first**: `js()` returns DOM
values (class lists, `href`, `innerText`) that are exact evidence, while a screenshot
path is only useful if the analysing model can see images.

## Tips

- **Always check `browser_console()` after navigating and after significant interactions.** Silent JS errors are among the most valuable findings.
- **Use `annotate=true` with `browser_vision`** when you need to reason about interactive element positions or when the snapshot refs are unclear.
- **Test with both valid and invalid inputs** — form validation bugs are common.
- **Scroll through long pages** — content below the fold may have rendering issues.
- **Test navigation flows** — click through multi-step processes end-to-end.
- **Check responsive behavior** by noting any layout issues visible in screenshots.
- **Don't forget edge cases**: empty states, very long text, special characters, rapid clicking.
- When reporting screenshots to the user, include `MEDIA:<screenshot_path>` so they can see the evidence inline.
- **Keep one reusable `dump()` helper** that walks the candidate elements and returns
their `href`/`innerText`/`className`, then reuse it on every page. Class-list dumps are
reproducible evidence; ad-hoc picks are not.
- **For a state bug ("clicking A changes B"), enumerate the candidate set in ONE DOM
pass** — collect every sidebar link (or table row) with its classes, then compute the
conflicting pairs in Python before opening anything. Then load the two or three URLs
that should differ and confirm. Clicking page-by-page is the slow path, and the pair
list it produces is what makes the report conclusive.
- **Quote the exact attribute value** in the report (`class="... mary-active-menu
bg-base-300"`, plus a wrong `aria-current="page"` if present). A wrong active marker is
an accessibility bug as well as a visual one — say both.
- **Check whether a test already covers the state you are reporting** (e.g. an E2E
active-state spec). If it passes while the bug is live, name the gap — the pages it
visits — so the fix can be pinned rather than just described.
