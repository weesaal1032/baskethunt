<div class="space-y-8">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">QA Reporting</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Review agent and team performance trends, then export scored calls for deeper analysis.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3 text-sm">
            <a href="{{ route('admin.qa.export', request()->query()) }}" class="inline-flex items-center gap-2 rounded-md border border-brand-400 bg-brand-500/10 px-3 py-2 font-semibold text-brand-600 hover:bg-brand-500/20 dark:border-brand-400/50 dark:text-brand-200">Download CSV</a>
        </div>
    </div>

    <form method="GET" class="grid gap-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/70 lg:grid-cols-5">
        <div>
            <label for="from" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">From</label>
            <input id="from" name="from" type="date" value="{{ old('from', $filters['from']) }}" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label for="to" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">To</label>
            <input id="to" name="to" type="date" value="{{ old('to', $filters['to']) }}" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label for="team" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Team</label>
            <input id="team" name="team" type="text" value="{{ old('team', $filters['team']) }}" placeholder="Support" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label for="agent_id" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Agent ID (optional)</label>
            <input id="agent_id" name="agent_id" type="number" value="{{ old('agent_id', $filters['agent_id']) }}" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" placeholder="123">
        </div>
        <div class="flex items-end justify-end">
            <button type="submit" class="inline-flex items-center rounded-md bg-brand-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">Apply</button>
        </div>
    </form>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900/70">
            <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Agent Summary</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Pass threshold {{ $summary['pass_threshold'] }}%. Ordered by average score.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/60">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2">Agent</th>
                            <th class="px-4 py-2">Team</th>
                            <th class="px-4 py-2 text-right">Evaluations</th>
                            <th class="px-4 py-2 text-right">Avg Score</th>
                            <th class="px-4 py-2 text-right">Pass Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse ($summary['agents'] as $agent)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-2 font-medium text-slate-900 dark:text-slate-100">{{ $agent['agent_name'] ?? 'Unassigned' }}</td>
                                <td class="px-4 py-2 text-slate-600 dark:text-slate-300">{{ $agent['team'] ?? 'Unassigned' }}</td>
                                <td class="px-4 py-2 text-right text-slate-600 dark:text-slate-300">{{ $agent['evaluations'] }}</td>
                                <td class="px-4 py-2 text-right font-semibold text-slate-900 dark:text-slate-100">{{ number_format($agent['average_score'], 2) }}%</td>
                                <td class="px-4 py-2 text-right text-slate-600 dark:text-slate-300">{{ number_format($agent['pass_rate'], 2) }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-3 text-center text-slate-500 dark:text-slate-400">No scored calls in the selected window.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900/70">
            <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Team Summary</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Aggregated across all scored calls in range.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/60">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2">Team</th>
                            <th class="px-4 py-2 text-right">Evaluations</th>
                            <th class="px-4 py-2 text-right">Avg Score</th>
                            <th class="px-4 py-2 text-right">Pass Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse ($summary['teams'] as $team)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-2 font-medium text-slate-900 dark:text-slate-100">{{ $team['team'] ?? 'Unassigned' }}</td>
                                <td class="px-4 py-2 text-right text-slate-600 dark:text-slate-300">{{ $team['evaluations'] }}</td>
                                <td class="px-4 py-2 text-right font-semibold text-slate-900 dark:text-slate-100">{{ number_format($team['average_score'], 2) }}%</td>
                                <td class="px-4 py-2 text-right text-slate-600 dark:text-slate-300">{{ number_format($team['pass_rate'], 2) }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-3 text-center text-slate-500 dark:text-slate-400">No scored calls in the selected window.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900/70">
        <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-700">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Trendline</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Daily averages across the selected filters.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900/60">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <th class="px-4 py-2">Date</th>
                        <th class="px-4 py-2 text-right">Evaluations</th>
                        <th class="px-4 py-2 text-right">Average Score</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse ($summary['trendline'] as $point)
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-2 text-slate-700 dark:text-slate-200">{{ $point['date'] }}</td>
                            <td class="px-4 py-2 text-right text-slate-600 dark:text-slate-300">{{ $point['evaluations'] }}</td>
                            <td class="px-4 py-2 text-right font-semibold text-slate-900 dark:text-slate-100">{{ number_format($point['average_score'], 2) }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-center text-slate-500 dark:text-slate-400">No data points in range.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($agentTrend)
        <div class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900/70">
            <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Agent {{ $filters['agent_id'] }} Trend</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Daily performance for the selected agent.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/60">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-2">Date</th>
                            <th class="px-4 py-2 text-right">Evaluations</th>
                            <th class="px-4 py-2 text-right">Average Score</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse ($agentTrend as $point)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-2 text-slate-700 dark:text-slate-200">{{ $point['date'] }}</td>
                                <td class="px-4 py-2 text-right text-slate-600 dark:text-slate-300">{{ $point['evaluations'] }}</td>
                                <td class="px-4 py-2 text-right font-semibold text-slate-900 dark:text-slate-100">{{ number_format($point['average_score'], 2) }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-3 text-center text-slate-500 dark:text-slate-400">No evaluations recorded for the selected agent.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
