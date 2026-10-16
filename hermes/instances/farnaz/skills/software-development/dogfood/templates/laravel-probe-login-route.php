<?php

/**
 * TEMPLATE — temporary session bootstrap for LOCAL-ONLY QA of a Laravel app that
 * sits behind a login wall, when no credential is available in the vault and the
 * agent must never type a password.
 *
 * Copy into `routes/web.php`, use it once, then DELETE it and re-run
 * `php artisan route:clear`. The route is environment-gated AND nonce-guarded; the
 * nonce is throwaway (it is not a user secret) and must never be committed.
 *
 * Replace NONCE below. Log in as an account that ALREADY EXISTS — the app's own
 * admin — so every menu, gate and permission is reachable.
 */

use Illuminate\Http\Request;

Route::get('/__probe-login/{id}', function (int $id, Request $request) {
    abort_unless(app()->environment('local') && $request->query('k') === 'NONCE', 404);

    Auth::loginUsingId($id);

    return redirect('/dashboard');
});

/* ---------------------------------------------------------------------------
 * USE (shell) — keep the cookie jar in the workspace/scratch dir, not /tmp; just
 * overwrite it next run, so there is nothing to delete:
 *
 *   php artisan route:clear            # routes-v7.php is cached: a new route 404s
 *                                      # until this is cleared
 *   php artisan route:list --path=__probe      # confirm it registered
 *
 *   J="$BH_AGENT_WORKSPACE/probe.jar"
 *   curl -s -c "$J" -o /dev/null -w 'probe:%{http_code} -> %{redirect_url}\n' \
 *     'http://127.0.0.1:8000/__probe-login/1?k=NONCE'
 *   curl -s -b "$J" -o /dev/null -w 'dashboard:%{http_code}\n' \
 *     http://127.0.0.1:8000/dashboard     # 200 = the session works
 *
 * Then open the probe URL in the browser tool (it follows the redirect and keeps
 * the session cookie), and do the QA pass.
 *
 * CLEANUP (do not skip):
 *   - delete the route from routes/web.php
 *   - php artisan route:clear
 *   - git status --short      # only pre-existing modifications may remain
 *   - state in the report that the probe route was added and removed
 *
 * If the platform refuses to run a "dangerous" cleanup command, do not reach for a
 * forced variant — there is normally nothing that needs deleting.
 * --------------------------------------------------------------------------- */
