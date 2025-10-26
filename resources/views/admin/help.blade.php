<section class="space-y-8">
    <header class="space-y-2">
        <h1 class="text-2xl font-semibold tracking-tight">Internal API & Exports</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Reference for CSV exports and JWT-protected business intelligence APIs. Last updated {{ $docsGeneratedAt }}.</p>
    </header>

    <section class="grid gap-6 lg:grid-cols-2">
        <article class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-lg font-semibold">CSV Exports</h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">All exports honour privacy masking and filter parameters from their respective screens.</p>
            <ul class="mt-4 space-y-3 text-sm">
                <li>
                    <div class="font-semibold text-slate-900 dark:text-slate-100">Calls</div>
                    <p class="text-slate-500 dark:text-slate-400">GET <code class="rounded bg-slate-100 px-1 py-0.5 dark:bg-slate-700">/admin/calls/export</code> — accepts the same query parameters as the recording library (date range, agent, direction, queue, transcript/QA flags).</p>
                </li>
                <li>
                    <div class="font-semibold text-slate-900 dark:text-slate-100">Recordings</div>
                    <p class="text-slate-500 dark:text-slate-400">GET <code class="rounded bg-slate-100 px-1 py-0.5 dark:bg-slate-700">/admin/recordings/export</code>.</p>
                </li>
                <li>
                    <div class="font-semibold text-slate-900 dark:text-slate-100">QA Scores</div>
                    <p class="text-slate-500 dark:text-slate-400">GET <code class="rounded bg-slate-100 px-1 py-0.5 dark:bg-slate-700">/admin/qa/export</code> — honours <code>from</code>, <code>to</code>, <code>agent_id</code>, and <code>team</code>.</p>
                </li>
            </ul>
        </article>

        <article class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-lg font-semibold">Authentication</h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Internal APIs require a JWT signed with the shared secret <code class="rounded bg-slate-100 px-1 py-0.5 dark:bg-slate-700">CALLHUB_INTERNAL_API_SECRET</code>.</p>
            <ol class="mt-4 space-y-2 text-sm text-slate-500 dark:text-slate-400">
                <li>Construct a JWT with header <code>{"alg":"HS256","typ":"JWT"}</code>.</li>
                <li>Include at minimum <code>iss</code>, <code>aud</code>, and a short-lived <code>exp</code> (seconds since epoch).</li>
                <li>Sign using the secret from <code>.env</code> or Admin → Settings when overridden.</li>
                <li>Send the token as <code>Authorization: Bearer &lt;token&gt;</code>.</li>
            </ol>
            <p class="mt-3 text-xs text-slate-400 dark:text-slate-500">Requests with missing, expired, or tampered tokens return HTTP 401 with a descriptive message.</p>
        </article>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <h2 class="text-lg font-semibold">Available Endpoints</h2>
        <dl class="mt-4 space-y-4 text-sm">
            <div>
                <dt class="font-semibold text-slate-900 dark:text-slate-100">GET {{ $apiBaseUrl }}/status</dt>
                <dd class="text-slate-500 dark:text-slate-400">Health heartbeat used by BI tooling. Returns uptime timestamp.</dd>
            </div>
            <div>
                <dt class="font-semibold text-slate-900 dark:text-slate-100">GET {{ $apiBaseUrl }}/calls</dt>
                <dd class="text-slate-500 dark:text-slate-400">
                    Paginates calls with optional filters:
                    <code>date_from</code>, <code>date_to</code>, <code>agent</code>, <code>direction</code>, <code>number</code>, <code>queue</code>, <code>disposition</code>, <code>has_transcript</code>, <code>has_qa_score</code>, <code>per_page</code>.
                    Numbers are masked when privacy mode is enabled.
                </dd>
            </div>
            <div>
                <dt class="font-semibold text-slate-900 dark:text-slate-100">GET {{ $apiBaseUrl }}/qa-scores</dt>
                <dd class="text-slate-500 dark:text-slate-400">Returns QA submissions filtered by <code>from</code>, <code>to</code>, <code>agent_id</code>, <code>team</code>, <code>queue</code>, <code>passed</code>, and <code>per_page</code>.</dd>
            </div>
        </dl>
    </section>

    <section class="rounded-lg border border-amber-300 bg-amber-50 p-6 text-sm text-amber-800 dark:border-amber-500/50 dark:bg-amber-900/30 dark:text-amber-200">
        <h2 class="font-semibold">Operational Notes</h2>
        <ul class="mt-3 list-disc space-y-1 pl-5">
            <li>All API responses include pagination metadata and relative navigation links.</li>
            <li>Failed authentication attempts are rate-limited and logged for audit review.</li>
            <li>Exports and APIs both respect the privacy mask toggle and retention windows configured in Settings.</li>
        </ul>
    </section>
</section>
