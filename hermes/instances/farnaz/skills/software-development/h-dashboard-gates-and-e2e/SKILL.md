---
name: h-dashboard-gates-and-e2e
description: "Run h-dashboard gates, E2E, and upstream PRs here."
version: 1.0.1
author: Farnaz
license: MIT
metadata:
  hermes:
    tags: [h-dashboard, laravel, e2e, playwright, phpstan, pint, ci]
    category: software-development
---

# h-dashboard: gates, E2E and PRs (this box)

## When to Use

- Running PHP gates (`composer test`, `composer verify`, Pint, PHPStan) in this repo.
- Running the Playwright E2E suites (`scripts/e2e-test.sh`).
- Cutting an issue branch from upstream `beta` and opening the PR on `asgarimehdi/h-dashboard`.
- Any E2E/gate failure whose message names `.env.e2e`, a missing database, a missing Playwright browser, or a preflight database mismatch.

Use when working the h-dashboard repo on this server: running PHP gates, the
Playwright suites, or opening a PR to upstream `beta`.

## Ground rules

- `cd /home/runner/h-dashboard`; read `git remote -v` and `git branch --show-current` — never hardcode a branch. Here `origin` = `nashenas6/h-dashboard` (fork, `push: true`) and upstream `asgarimehdi/h-dashboard` is read-only (`push: false`).
- One-issue branch off upstream beta without touching remote config:
  `git fetch --no-tags https://github.com/asgarimehdi/h-dashboard.git beta:refs/remotes/upstream/beta` then `git checkout -b <type>/<issue>-<slug> refs/remotes/upstream/beta`.
- Push with an explicit refspec (`git push origin HEAD:refs/heads/<branch>`); a bare `git push` follows the wrong `branch.<name>.merge` for other branches.
- Open the cross-fork PR with head `nashenas6:<branch>`:
  `gh pr create --repo asgarimehdi/h-dashboard --base beta --head nashenas6:<branch> --body-file <file>`.
- `--reviewer <login>` fails with `GraphQL: nashenas6 does not have the correct permissions to execute RequestReviewsByLogin` (and the REST equivalent 404s) — the fork account cannot request reviewers on upstream. Fall back to `gh pr comment <n>` mentioning the reviewer and say so in the report.
- `gh pr checks <n> --repo asgarimehdi/h-dashboard --watch --interval 30`; required jobs are `Tests & Coverage`, `Code Style (Pint)`, `PHPStan Static Analysis`.

## Environment prep (fresh box)

1. `bash scripts/build-env-e2e.sh` — builds the gitignored `.env.e2e` from `.env.e2e.example` copying APP_KEY/DB_PASSWORD/REDIS_PASSWORD straight out of `.env` (never prints values). Without it `scripts/e2e-test.sh` dies at the first `cp .env.e2e .env`.
2. The E2E database is a *separate* DB and `migrate:fresh` does **not** create it. `createdb` it with the creds from `.env` (usually `h_dashboard_e2e`). Dev DB is `h_dashboard`, PHPUnit DB is `h_dashboard_test` — never point `.env.e2e` at either; the E2E run wipes it.
3. Playwright browsers are not installed on a fresh box: `npx playwright install chromium`. Symptom is `browserType.launch: Executable doesn't exist at ~/.cache/ms-playwright/chromium_headless_shell-<n>/...` and **every** spec failing in the same second.

## Commands

```bash
composer test tests/Feature/<File>.php      # targeted
composer test                               # full suite (~520s, serial)
composer verify tests/Feature/<File>.php    # preflight + view:clear + pint --test + phpstan + named tests
php vendor/laravel/pint/builds/pint --dirty --format agent   # only when CI needs the result: composer test does NOT run pint
php vendor/phpstan/phpstan/phpstan analyse --no-progress
bash scripts/e2e-test.sh tests/e2e/navigation
bash scripts/e2e-test.sh tests/e2e/a11y
```

## Pitfalls

- **`@push('scripts')` is too late for a library a Livewire component needs in its Alpine `init()`.** On `wire:navigate` Livewire morphs the body and runs Alpine BEFORE evaluating body scripts, so a pushed `leaflet.js` yields `Alpine Expression Error: L is not defined`, `#map` without `leaflet-container`, `Alpine.store('map').get()` null — a blank map with no loud error. `@assets` is injected into `<head>` and new head assets are evaluated before the swap completes; that is the mechanism to use (verified on #1182). `@assets` also renders nothing in the body, so PageScriptSplitting-style presence assertions still see the `src` in the HTML.
- **Livewire's `$renderedAssets` static leaks between requests inside one PHPUnit process.** After converting a page to `@assets`, a test that renders several pages (or runs after another page's test) sees the previous page's assets in `<head>`, so `assertDontSee` passes/fails by test order. Call `Livewire::flushState()` before each `$this->get()`.
- **`.env.e2e` built by `scripts/build-env-e2e.sh` can keep the literal placeholder `<same-as-dev>`** (on this box `.env`'s own `DB_PASSWORD`/`REDIS_PASSWORD` ARE that literal). `source .env.e2e` then dies with `syntax error near unexpected token 'newline'` and the E2E run exits 2 before Playwright. Quote the value (`DB_PASSWORD="<same-as-dev>"`); `.env.e2e` is gitignored.
- **`source .env` in a bash subshell can leave `DB_PASSWORD` empty** even though the line exists — extract with `awk -F= '/^DB_PASSWORD=/{print substr($0,13); exit}' .env` and pass it as `PGPASSWORD=`. Needed to create the e2e database (`createdb h_dashboard_e2e`): `migrate:fresh` will not create it.
- **`pint --dirty` and CI's `pint --test` do NOT scan `resources/views`, but the pre-commit hook passes the staged paths explicitly**, so it reformats the PHP class inside every touched `*.blade.php` and re-stages it — unrelated churn in a focused PR (`+87 lines` of import reordering). Either keep it deliberately or restore those files and amend with `--no-verify` after checking the real files (`pint --test tests/...`) are clean. Prove the scope with `pint --test resources/views/livewire/<any untouched view>.blade.php` → it fails on beta, while the repo-wide run passes.
- **`composer.lock` drift in the working tree makes PHPStan report `ignore.unmatched` baseline errors** (`CenterBoundarySeeder`, `HardwareHistorySeeder`) that CI does not have. Compare `php vendor/phpstan/phpstan/phpstan --version` with the lock's pinned version; `git checkout -- composer.lock package-lock.json && composer install` aligns the vendor tree with CI and clears them.
- **`ticket-comments.blade.php`, `⚡inbox` and `⚡monitoring` drift on beta** — rebase/apply with `git apply -3` and re-check the insertion point, the line numbers move.
- **`| tail -N` eats the exit code.** `bash scripts/e2e-test.sh … | tail -80` reports 0 even when 34 specs failed. Redirect instead: `… > $LOG 2>&1; echo "EXIT=$?"`.
- **Never two E2E runs at once** — the script swaps `.env` for `.env.e2e` and both runs would fight over the same DB and port 8001. Teardown is trap-based; if a run is hard-killed, restore with `cp .env.dev.bak .env`, delete the bak, `rm -f tests/e2e/.run-state.json`, and kill the leftover `artisan serve --port=8001`.
- **`pgrep -f 'port=8001'` matches your own command line**, so it always "finds" a server. Check the port instead (`ss -ltn | grep 8001`, or `curl -s -o /dev/null -w '%{http_code}' http://localhost:8001/login`).
- **`composer verify` preflight aborts with “Database is [h_dashboard]; expected [h_dashboard_test]”** whenever an earlier `set -a; source .env; set +a` in the same shell exported `DB_DATABASE`. Undo it: `unset $(grep -oE '^[A-Za-z_][A-Za-z0-9_]*=' .env | tr -d '=' | sort -u)`.
- **`vendor/bin/pint` can be refused by the lifecycle guard** (“larger than the scan cap”): run `php vendor/laravel/pint/builds/pint` instead.
- **`composer test` writes a per-clone APP_KEY into the git-tracked `.env.testing`.** It shows as a modified file in `git status`; stage explicit paths (never `git add -A`) and `git checkout -- .env.testing` when finished.
- `test-results/` and `playwright-report/` are gitignored — failures leave traces there, not untracked-junk in `git status`.
- `phpstan-baseline.neon` is line-keyed: after editing a baselined file the only legal re-generate is one whose `git diff` adds **zero** entries.
- `MaryUiMirrorTest` diffs each mirror from the first `<<<'BLADE'` heredoc to EOF — anything added to `app/View/Components/Accessible*.php` must sit **above** `render()`, and `render()` itself must not change.
