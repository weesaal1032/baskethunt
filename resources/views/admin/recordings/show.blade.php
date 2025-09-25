@php
    $callDuration = $call?->duration_sec;
    $formatDuration = function (?int $seconds): string {
        if ($seconds === null) {
            return '—';
        }

        $seconds = max($seconds, 0);
        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d', $minutes, $remainingSeconds);
    };
    $segments = $transcript?->segments ?? [];
    $qaSummaryScore = $call?->qaScores?->first();
    $qaContext = $qaContext ?? [];
    $formatScore = function ($score) {
        if (! $score) {
            return null;
        }

        return [
            'id' => $score->id,
            'version' => $score->version,
            'status' => $score->status,
            'total_score' => $score->total_score,
            'possible_score' => $score->possible_score,
            'passed' => (bool) $score->passed,
            'comments' => $score->comments,
            'tags' => $score->tags ?? [],
            'rubric_version' => $score->rubric_version,
            'created_at' => optional($score->created_at)->toDateTimeString(),
            'updated_at' => optional($score->updated_at)->toDateTimeString(),
            'submitted_at' => optional($score->submitted_at)->toDateTimeString(),
            'scorer' => $score->scorer?->only(['id', 'name']),
        ];
    };

    $qaHistory = collect($qaContext['history'] ?? [])->map($formatScore)->filter()->values()->all();
    $qaDraft = $formatScore($qaContext['draft'] ?? null);
    $qaLatest = $formatScore($qaContext['latest_submitted'] ?? null);
    $qaSeed = $qaContext['seed'] ?? ['responses' => [], 'comment' => null, 'tags' => [], 'passed' => false];

    $qaWorkspaceInitial = [
        'rubric' => $qaContext['rubric'] ?? [],
        'rubricVersion' => $qaContext['rubric_version'] ?? 1,
        'passThreshold' => $qaContext['pass_threshold'] ?? 80,
        'nextVersion' => $qaContext['next_version'] ?? 1,
        'seedResponses' => $qaSeed['responses'] ?? [],
        'seedComment' => $qaSeed['comment'] ?? '',
        'seedTags' => $qaSeed['tags'] ?? [],
        'seedPassed' => $qaSeed['passed'] ?? false,
        'history' => $qaHistory,
        'draft' => $qaDraft,
        'latest' => $qaLatest,
        'suggestedTags' => $qaContext['suggested_tags'] ?? [],
        'saveUrl' => route('admin.recordings.qa.score', $recording),
        'historyUrl' => route('admin.recordings.qa.history', $recording),
    ];
@endphp

<div class="space-y-8" x-data="qaWorkspace(@json($qaWorkspaceInitial))">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <a href="{{ route('admin.recordings.index') }}" class="text-sm text-brand-500 hover:text-brand-600">&larr; Back to library</a>
            <h1 class="mt-2 text-2xl font-semibold">Call review</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Genesys-style QA workspace with synchronized audio and transcript.</p>
        </div>
        <div class="flex flex-wrap gap-3 text-sm">
            <div class="rounded border border-slate-300 bg-white px-3 py-2 text-slate-600 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                <span class="block text-[10px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Agent</span>
                <span>{{ $call?->agent?->name ?? 'Unassigned' }}</span>
            </div>
            <div class="rounded border border-slate-300 bg-white px-3 py-2 text-slate-600 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                <span class="block text-[10px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Queue</span>
                <span>{{ $call?->queue ?? '—' }}</span>
            </div>
            <div class="rounded border border-slate-300 bg-white px-3 py-2 text-slate-600 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                <span class="block text-[10px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Duration</span>
                <span>{{ $formatDuration($callDuration) }}</span>
            </div>
            <div class="rounded border border-slate-300 bg-white px-3 py-2 text-slate-600 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                <span class="block text-[10px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Direction</span>
                <span class="capitalize">{{ $call?->direction ?? '—' }}</span>
            </div>
            <div class="rounded border border-slate-300 bg-white px-3 py-2 text-slate-600 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                <span class="block text-[10px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Disposition</span>
                <span>{{ $call?->disposition ?? '—' }}</span>
            </div>
            @php
                $qaHeaderScore = $qaLatest['total_score'] ?? ($qaSummaryScore->total_score ?? null);
                $qaHeaderStatus = $qaLatest['status'] ?? ($qaSummaryScore->status ?? null) ?? 'draft';
            @endphp
            @if ($qaHeaderScore !== null)
                <div class="rounded border border-brand-300 bg-brand-50 px-3 py-2 text-brand-700 shadow-sm dark:border-brand-400/60 dark:bg-brand-500/10 dark:text-brand-200">
                    <span class="block text-[10px] font-semibold uppercase tracking-wide text-brand-500/80">QA Score ({{ $qaHeaderStatus }})</span>
                    <span>{{ $qaHeaderScore }}%</span>
                </div>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">
        <div class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900/70">
            <div>
                <h2 class="text-lg font-semibold">Playback</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Masked numbers: {{ format_phone($call?->from_number, $piiMasking) }} → {{ format_phone($call?->to_number, $piiMasking) }}</p>
            </div>
            <div class="relative rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                <div id="waveform" class="h-32 w-full"></div>
                <div id="waveform-markers" class="pointer-events-none absolute inset-x-4 bottom-4 top-4"></div>
                <audio id="call-audio" src="{{ $audioUrl }}" preload="auto"></audio>
            </div>
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <button type="button" id="play-pause" class="inline-flex items-center gap-2 rounded bg-brand-500 px-4 py-2 font-medium text-white shadow hover:bg-brand-600">
                    <span data-state="label">Play</span>
                    <span class="text-xs" data-state="icon">&#9658;</span>
                </button>
                <div class="flex items-center gap-2">
                    <button type="button" id="rewind" class="rounded border border-slate-300 px-3 py-2 text-slate-600 hover:border-brand-500 hover:text-brand-600 dark:border-slate-600 dark:text-slate-300">&larr; 5s</button>
                    <button type="button" id="forward" class="rounded border border-slate-300 px-3 py-2 text-slate-600 hover:border-brand-500 hover:text-brand-600 dark:border-slate-600 dark:text-slate-300">5s &rarr;</button>
                </div>
                <div class="flex items-center gap-2 rounded border border-slate-300 px-3 py-2 text-slate-600 dark:border-slate-600 dark:text-slate-300">
                    <span class="text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">Speed</span>
                    <span id="speed-display" class="font-semibold">1.0x</span>
                </div>
                <div class="ml-auto flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                    <span>Space: Play/Pause</span>
                    <span>←/→: ±5s</span>
                    <span>↑/↓: Speed</span>
                </div>
            </div>
        </div>

        <div class="flex flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900/70">
            <div class="border-b border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-lg font-semibold">Transcript</h2>
                <div class="mt-2 flex items-center gap-2">
                    <input id="transcript-search" type="search" placeholder="Search transcript" class="w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                    <button type="button" id="clear-search" class="rounded border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:border-brand-500 hover:text-brand-600 dark:border-slate-600 dark:text-slate-300">Clear</button>
                </div>
            </div>
            <div id="transcript-panel" class="flex-1 overflow-y-auto px-4 py-4">
                @if (empty($segments))
                    <p class="text-sm text-slate-500 dark:text-slate-400">Transcript not available yet. Transcription will appear once processing completes.</p>
                @else
                    <ul id="transcript-list" class="space-y-1">
                        @foreach ($segments as $index => $segment)
                            @php
                                $start = is_numeric($segment['start'] ?? null) ? (float) $segment['start'] : null;
                                $end = is_numeric($segment['end'] ?? null) ? (float) $segment['end'] : null;
                                $displayTime = $start !== null ? gmdate('i:s', (int) $start) : '--:--';
                                $text = trim((string) ($segment['text'] ?? ''));
                            @endphp
                            <li
                                data-segment-index="{{ $index }}"
                                data-start="{{ $start ?? '' }}"
                                data-end="{{ $end ?? '' }}"
                                class="group flex cursor-pointer items-start gap-3 rounded px-3 py-2 text-sm hover:bg-brand-50 dark:hover:bg-brand-500/10"
                            >
                                <span class="mt-0.5 min-w-[48px] text-xs font-medium text-slate-500 dark:text-slate-400">{{ $displayTime }}</span>
                                <div class="flex-1 space-y-1">
                                    <p class="leading-relaxed text-slate-700 dark:text-slate-200" data-content>{{ $text }}</p>
                                    <div class="opacity-0 transition-opacity group-hover:opacity-100">
                                        <button type="button" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-600 hover:border-brand-500 hover:text-brand-600 dark:border-slate-600 dark:text-slate-300" data-action="copy-snippet" data-text="{{ $text }}">Copy snippet</button>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900/70">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-6 py-4 dark:border-slate-700 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">QA Scoring</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Rubric v<span x-text="rubricVersion"></span> · Pass threshold <span x-text="passThreshold"></span>%</p>
            </div>
            <div class="text-right">
                <div class="text-2xl font-semibold text-slate-900 dark:text-slate-100" x-text="scoreLabel()"></div>
                <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400" x-text="statusBadge()"></div>
            </div>
        </div>

        <div class="space-y-6 px-6 py-6">
            <template x-for="(category, catIndex) in rubric" :key="category.id">
                <div class="space-y-4 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900/40">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100" x-text="category.name"></h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Weight: <span x-text="category.weight"></span></p>
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">Questions: <span x-text="category.questions.length"></span></div>
                    </div>

                    <div class="space-y-4">
                        <template x-for="(question, questionIndex) in category.questions" :key="question.id">
                            <div class="rounded-md border border-slate-200 bg-white p-4 dark:border-slate-600 dark:bg-slate-900">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100" x-text="question.prompt"></p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">Weight: <span x-text="question.weight"></span></p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <template x-if="question.type === 'yes_no'">
                                            <div class="flex gap-2">
                                                <button
                                                    type="button"
                                                    class="rounded-md border px-3 py-1 text-sm font-semibold"
                                                    :class="responses[question.id] === true ? 'border-brand-500 bg-brand-500 text-white' : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300'"
                                                    @click="setYesNo(question.id, true)"
                                                >Yes</button>
                                                <button
                                                    type="button"
                                                    class="rounded-md border px-3 py-1 text-sm font-semibold"
                                                    :class="responses[question.id] === false ? 'border-brand-500 bg-brand-500 text-white' : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300'"
                                                    @click="setYesNo(question.id, false)"
                                                >No</button>
                                            </div>
                                        </template>
                                        <template x-if="question.type === 'scale'">
                                            <div class="flex items-center gap-2">
                                                <input type="range" :min="question.scale_min" :max="question.scale_max" step="0.5" :value="responses[question.id] ?? question.scale_min" @input="setScale(question.id, $event.target.value)" class="w-40">
                                                <span class="w-10 text-right text-sm font-semibold text-slate-700 dark:text-slate-200" x-text="Number(responses[question.id] ?? question.scale_min).toFixed(1)"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <div class="space-y-4">
                <div>
                    <label for="qa_comment" class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Evaluator Comments</label>
                    <textarea id="qa_comment" rows="3" x-model="comment" @input="queueSave()" class="mt-2 w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" placeholder="Call out coaching moments, objections, or compliance notes"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Tags</label>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <template x-for="(tag, index) in tags" :key="tag">
                            <span class="inline-flex items-center gap-1 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-600 dark:bg-brand-500/20 dark:text-brand-200">
                                <span x-text="tag"></span>
                                <button type="button" class="text-brand-500 hover:text-brand-700 dark:text-brand-200" @click="removeTag(index)">×</button>
                            </span>
                        </template>
                        <input type="text" placeholder="Add tag and press enter" class="min-w-[160px] rounded-md border border-dashed border-slate-300 px-3 py-1 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500" @keydown.enter.prevent="addTagFromInput($event)">
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-500 dark:text-slate-400">
                        <template x-for="suggestion in suggestedTags" :key="suggestion">
                            <button type="button" class="rounded-full border border-slate-300 px-3 py-1 hover:border-brand-500 hover:text-brand-600 dark:border-slate-600 dark:hover:border-brand-400 dark:hover:text-brand-200" @click="addSuggestedTag(suggestion)">
                                <span x-text="suggestion"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <input id="qa_passed" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-500 focus:ring-brand-500" x-model="passed" @change="queueSave()">
                    <label for="qa_passed" class="text-sm text-slate-600 dark:text-slate-300">Mark as pass regardless of score</label>
                </div>
            </div>

            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="text-xs" :class="error ? 'text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-slate-400'" x-text="statusLabel()"></div>
                <div class="flex gap-3">
                    <button type="button" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:border-brand-500 hover:text-brand-600 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200" @click="save(false)" :disabled="saving">Save Draft</button>
                    <button type="button" class="inline-flex items-center rounded-md bg-brand-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:opacity-60" @click="save(true)" :disabled="saving">Submit Score</button>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">History</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead class="bg-slate-50 dark:bg-slate-900/60">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                <th class="px-4 py-2">Version</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2 text-right">Score</th>
                                <th class="px-4 py-2">Evaluator</th>
                                <th class="px-4 py-2">Submitted</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                            <template x-if="history.length === 0">
                                <tr>
                                    <td colspan="5" class="px-4 py-3 text-center text-slate-500 dark:text-slate-400">No QA entries yet.</td>
                                </tr>
                            </template>
                            <template x-for="item in history" :key="item.id">
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                    <td class="px-4 py-2 text-slate-700 dark:text-slate-200" x-text="'v' + item.version"></td>
                                    <td class="px-4 py-2 text-slate-600 dark:text-slate-300" x-text="item.status"></td>
                                    <td class="px-4 py-2 text-right font-semibold text-slate-900 dark:text-slate-100" x-text="item.total_score + '%'"></td>
                                    <td class="px-4 py-2 text-slate-600 dark:text-slate-300" x-text="item.scorer ? item.scorer.name : '—'"></td>
                                    <td class="px-4 py-2 text-slate-600 dark:text-slate-300" x-text="item.submitted_at || item.updated_at"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script src="https://unpkg.com/wavesurfer.js@7/dist/wavesurfer.js"></script>
    <script>
        window.qaWorkspace = function (initial) {
            return {
                rubric: initial.rubric ?? [],
                rubricVersion: initial.rubricVersion ?? 1,
                passThreshold: initial.passThreshold ?? 80,
                nextVersion: initial.nextVersion ?? 1,
                responses: Object.assign({}, initial.seedResponses ?? {}),
                comment: initial.seedComment ?? '',
                tags: Array.isArray(initial.seedTags) ? [...initial.seedTags] : [],
                passed: Boolean(initial.seedPassed ?? false),
                history: Array.isArray(initial.history) ? initial.history : [],
                draft: initial.draft ?? null,
                latest: initial.latest ?? null,
                suggestedTags: Array.isArray(initial.suggestedTags) ? initial.suggestedTags : [],
                saveUrl: initial.saveUrl,
                historyUrl: initial.historyUrl,
                version: initial.draft?.version ?? initial.nextVersion ?? 1,
                saving: false,
                error: null,
                currentScorePercent: null,
                currentPossible: 0,
                queueHandle: null,
                saveQueued: false,
                queuedFinalize: false,
                lastSavedAt: initial.draft?.updated_at ?? initial.latest?.submitted_at ?? null,
                csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                init() {
                    this.updateScore();
                },
                setYesNo(id, value) {
                    const key = String(id);
                    if (this.responses[key] === value) {
                        delete this.responses[key];
                    } else {
                        this.responses[key] = value;
                    }
                    this.updateScore();
                    this.queueSave();
                },
                setScale(id, value) {
                    const numeric = parseFloat(value);
                    const key = String(id);
                    this.responses[key] = Number.isFinite(numeric) ? numeric : null;
                    this.updateScore();
                    this.queueSave();
                },
                addTagFromInput(event) {
                    const value = (event.target.value || '').trim();
                    if (!value) {
                        return;
                    }
                    this.addTag(value);
                    event.target.value = '';
                },
                addSuggestedTag(tag) {
                    this.addTag(tag);
                },
                addTag(tag) {
                    const clean = (tag || '').trim();
                    if (!clean) {
                        return;
                    }
                    if (!this.tags.includes(clean)) {
                        this.tags.push(clean);
                        this.queueSave();
                    }
                },
                removeTag(index) {
                    this.tags.splice(index, 1);
                    this.queueSave();
                },
                queueSave() {
                    if (this.queueHandle) {
                        clearTimeout(this.queueHandle);
                    }
                    this.queueHandle = setTimeout(() => {
                        this.queueHandle = null;
                        this.save(false);
                    }, 900);
                },
                async save(finalize = false) {
                    if (this.queueHandle) {
                        clearTimeout(this.queueHandle);
                        this.queueHandle = null;
                    }

                    if (this.saving) {
                        this.saveQueued = true;
                        this.queuedFinalize = this.queuedFinalize || finalize;
                        return;
                    }

                    this.saving = true;
                    this.error = null;

                    const payload = {
                        responses: this.serializedResponses(),
                        comment: this.comment || null,
                        tags: this.tags,
                        passed: this.passed,
                        version: this.version,
                        rubric_version: this.rubricVersion,
                        finalize,
                    };

                    try {
                        const response = await fetch(this.saveUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                            },
                            body: JSON.stringify(payload),
                        });

                        if (response.status === 409) {
                            const conflict = await response.json();
                            this.error = conflict.message ?? 'Rubric has changed. Refresh to continue scoring.';
                            if (conflict.rubric_version) {
                                this.rubricVersion = conflict.rubric_version;
                            }
                            return;
                        }

                        if (!response.ok) {
                            const message = (await response.json().catch(() => null))?.message ?? 'Unable to save QA score.';
                            throw new Error(message);
                        }

                        const data = await response.json();
                        this.applyWorkspace(data);
                    } catch (error) {
                        this.error = error instanceof Error ? error.message : 'Unable to save QA score.';
                    } finally {
                        this.saving = false;
                        const shouldRetry = this.saveQueued;
                        const finalizeRetry = this.queuedFinalize;
                        this.saveQueued = false;
                        this.queuedFinalize = false;
                        if (shouldRetry) {
                            this.save(finalizeRetry);
                        }
                    }
                },
                applyWorkspace(data) {
                    if (typeof data !== 'object' || data === null) {
                        return;
                    }

                    if (Array.isArray(data.history)) {
                        this.history = data.history;
                    }

                    this.draft = data.draft ?? null;
                    this.latest = data.latest_submitted ?? null;
                    if (Array.isArray(data.suggested_tags)) {
                        this.suggestedTags = data.suggested_tags;
                    }

                    if (typeof data.rubric_version === 'number') {
                        this.rubricVersion = data.rubric_version;
                    }

                    if (typeof data.pass_threshold === 'number') {
                        this.passThreshold = data.pass_threshold;
                    }

                    if (typeof data.next_version === 'number') {
                        this.nextVersion = data.next_version;
                    }

                    if (data.score) {
                        this.version = data.score.version ?? this.version;
                        this.passed = Boolean(data.score.passed ?? this.passed);
                        this.comment = data.score.comments ?? '';
                        this.tags = Array.isArray(data.score.tags) ? data.score.tags : [];
                        this.lastSavedAt = data.score.submitted_at ?? data.score.updated_at ?? null;
                    } else if (this.draft) {
                        this.version = this.draft.version;
                        this.lastSavedAt = this.draft.updated_at ?? null;
                        this.passed = Boolean(this.draft.passed);
                        this.comment = this.draft.comments ?? '';
                        this.tags = Array.isArray(this.draft.tags) ? this.draft.tags : this.tags;
                    } else if (this.latest) {
                        this.version = this.latest.version;
                        this.lastSavedAt = this.latest.submitted_at ?? this.latest.updated_at ?? null;
                        this.passed = Boolean(this.latest.passed);
                        this.comment = this.latest.comments ?? '';
                        this.tags = Array.isArray(this.latest.tags) ? this.latest.tags : this.tags;
                    } else {
                        this.version = this.nextVersion ?? this.version ?? 1;
                    }

                    this.error = null;
                    this.updateScore();
                },
                serializedResponses() {
                    const allowed = new Set();
                    this.rubric.forEach((category) => {
                        (category.questions ?? []).forEach((question) => {
                            if (question.id) {
                                allowed.add(String(question.id));
                            }
                        });
                    });

                    const output = {};

                    allowed.forEach((id) => {
                        if (!Object.prototype.hasOwnProperty.call(this.responses, id)) {
                            return;
                        }

                        const value = this.responses[id];
                        const question = this.findQuestion(id);

                        if (!question) {
                            return;
                        }

                        if (question.type === 'yes_no') {
                            if (typeof value === 'boolean') {
                                output[id] = value;
                            } else if (typeof value === 'string') {
                                const normalized = value.trim().toLowerCase();
                                output[id] = ['true', '1', 'yes', 'y'].includes(normalized);
                            } else {
                                output[id] = Boolean(value);
                            }
                        } else {
                            const numeric = Number(value);
                            if (Number.isFinite(numeric)) {
                                output[id] = numeric;
                            }
                        }
                    });

                    return output;
                },
                findQuestion(id) {
                    for (const category of this.rubric) {
                        for (const question of category.questions ?? []) {
                            if (String(question.id) === String(id)) {
                                return question;
                            }
                        }
                    }
                    return null;
                },
                statusLabel() {
                    if (this.error) {
                        return this.error;
                    }

                    if (this.saving) {
                        return 'Saving…';
                    }

                    if (this.draft) {
                        const timestamp = this.draft.updated_at ?? this.lastSavedAt ?? null;
                        return `Draft v${this.draft.version} · ${this.formatTimestamp(timestamp)}`;
                    }

                    if (this.latest) {
                        const timestamp = this.latest.submitted_at ?? this.latest.updated_at ?? null;
                        return `Submitted v${this.latest.version} · ${this.formatTimestamp(timestamp)}`;
                    }

                    return 'No QA evaluation captured yet.';
                },
                statusBadge() {
                    if (this.error) {
                        return 'Attention needed';
                    }

                    if (this.saving) {
                        return 'Saving…';
                    }

                    if (this.draft) {
                        return this.draft.passed ? `Draft · Passing` : `Draft · Needs review`;
                    }

                    if (this.latest) {
                        return this.latest.passed ? `Submitted · Passing` : `Submitted · Needs review`;
                    }

                    return 'Unscored';
                },
                scoreLabel() {
                    const percent = this.currentPercent();
                    if (percent !== null) {
                        return `${Math.round(percent)}%`;
                    }

                    if (this.latest) {
                        return `${Math.round(this.latest.total_score ?? 0)}%`;
                    }

                    if (this.draft) {
                        return `${Math.round(this.draft.total_score ?? 0)}%`;
                    }

                    return '--';
                },
                currentPercent() {
                    if (this.currentScorePercent !== null) {
                        return this.currentScorePercent;
                    }

                    return null;
                },
                updateScore() {
                    const result = this.calculateScore();
                    this.currentPossible = result.possible;
                    this.currentScorePercent = result.percent;
                },
                calculateScore() {
                    let possible = 0;
                    let earned = 0;

                    this.rubric.forEach((category) => {
                        (category.questions ?? []).forEach((question) => {
                            const weight = Number(question.weight ?? 0);
                            if (weight <= 0) {
                                return;
                            }

                            possible += weight;
                            if (question.type === 'yes_no') {
                                const key = String(question.id);
                                const value = this.responses[key];
                                if (value === true || value === 'true' || value === 1) {
                                    earned += weight;
                                }
                            } else if (question.type === 'scale') {
                                const min = Number(question.scale_min ?? 0);
                                const max = Number(question.scale_max ?? weight || 1);
                                const key = String(question.id);
                                const raw = Number(this.responses[key] ?? min);
                                if (Number.isFinite(raw)) {
                                    const clamped = Math.min(max, Math.max(min, raw));
                                    const range = Math.max(max - min, 1);
                                    earned += weight * ((clamped - min) / range);
                                }
                            }
                        });
                    });

                    if (possible <= 0) {
                        return { possible: 0, earned: 0, percent: null };
                    }

                    return {
                        possible,
                        earned,
                        percent: (earned / possible) * 100,
                    };
                },
                isPassing() {
                    if (this.passed) {
                        return true;
                    }

                    const percent = this.currentPercent();
                    if (percent === null) {
                        const reference = this.latest ?? this.draft;
                        if (!reference) {
                            return false;
                        }
                        return Number(reference.total_score ?? 0) >= this.passThreshold;
                    }

                    return percent >= this.passThreshold;
                },
                formatTimestamp(value) {
                    if (!value) {
                        return 'Not saved yet';
                    }

                    const parsed = new Date(value.replace(' ', 'T'));
                    if (Number.isNaN(parsed.getTime())) {
                        return value;
                    }

                    return parsed.toLocaleString();
                },
            };
        };
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const audioElement = document.getElementById('call-audio');
            const playPause = document.getElementById('play-pause');
            const rewind = document.getElementById('rewind');
            const forward = document.getElementById('forward');
            const speedDisplay = document.getElementById('speed-display');
            const transcriptList = document.getElementById('transcript-list');
            const transcriptPanel = document.getElementById('transcript-panel');
            const markers = document.getElementById('waveform-markers');
            const searchInput = document.getElementById('transcript-search');
            const clearSearch = document.getElementById('clear-search');
            const waveformData = @json($recording->waveform_json ?? []);
            const segments = @json($segments);
            const getDuration = () => audioElement.duration || wave.getDuration() || {{ $callDuration ?? '0' }};
            const wave = WaveSurfer.create({
                container: '#waveform',
                waveColor: '#94a3b8',
                progressColor: '#0f62fe',
                backend: 'MediaElement',
                media: audioElement,
                height: 120,
                normalize: true,
                responsive: true,
                partialRender: true,
                barWidth: 2,
                barGap: 1,
            });

            if (waveformData.length > 0) {
                const duration = {{ $callDuration ?? 'null' }};
                wave.load(audioElement, waveformData, duration || undefined);
            } else {
                wave.load(audioElement);
            }

            let playbackRate = 1.0;
            const minRate = 0.5;
            const maxRate = 2.0;
            const rateStep = 0.25;
            let activeIndex = null;
            let scrollLock = false;

            const updatePlayPause = () => {
                const playing = !audioElement.paused;
                playPause.querySelector('[data-state="label"]').textContent = playing ? 'Pause' : 'Play';
                playPause.querySelector('[data-state="icon"]').innerHTML = playing ? '&#10074;&#10074;' : '&#9658;';
            };

            const seekDelta = (seconds) => {
                const total = getDuration();
                if (!Number.isFinite(total) || total <= 0) {
                    return;
                }

                const target = Math.max(0, Math.min(total, audioElement.currentTime + seconds));
                audioElement.currentTime = target;
                wave.seekTo(target / total);
            };

            const highlightSegment = (index) => {
                if (!transcriptList) {
                    return;
                }

                if (activeIndex !== null && transcriptList.children[activeIndex]) {
                    transcriptList.children[activeIndex].classList.remove('bg-brand-100', 'dark:bg-brand-500/20');
                }

                activeIndex = index;

                if (index === null || !transcriptList.children[index]) {
                    return;
                }

                const element = transcriptList.children[index];
                element.classList.add('bg-brand-100', 'dark:bg-brand-500/20');

                if (!scrollLock) {
                    const panelRect = transcriptPanel.getBoundingClientRect();
                    const elementRect = element.getBoundingClientRect();

                    if (elementRect.top < panelRect.top || elementRect.bottom > panelRect.bottom) {
                        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            };

            const segmentForTime = (time) => {
                if (!Array.isArray(segments)) {
                    return null;
                }

                for (let i = 0; i < segments.length; i++) {
                    const segment = segments[i];
                    const start = parseFloat(segment.start ?? segment[0]);
                    const endValue = segment.end ?? segment[1];
                    const end = parseFloat(endValue !== undefined ? endValue : start + 2);

                    if (!Number.isFinite(start)) {
                        continue;
                    }

                    if (!Number.isFinite(end)) {
                        if (time >= start) {
                            return i;
                        }

                        continue;
                    }

                    if (time >= start && time <= end) {
                        return i;
                    }
                }

                return null;
            };

            const renderMarkers = (duration) => {
                if (!markers || !Array.isArray(segments) || !Number.isFinite(duration) || duration <= 0) {
                    return;
                }

                markers.innerHTML = '';

                segments.forEach((segment) => {
                    const start = parseFloat(segment.start ?? segment[0]);

                    if (!Number.isFinite(start)) {
                        return;
                    }

                    const marker = document.createElement('span');
                    marker.className = 'absolute top-0 bottom-0 border-l border-brand-400/70';
                    marker.style.left = `${Math.min(100, Math.max(0, (start / duration) * 100))}%`;
                    markers.appendChild(marker);
                });
            };

            const setPlaybackRate = (rate) => {
                playbackRate = Math.min(maxRate, Math.max(minRate, rate));
                audioElement.playbackRate = playbackRate;
                speedDisplay.textContent = `${playbackRate.toFixed(2)}x`;
            };

            playPause?.addEventListener('click', () => {
                if (audioElement.paused) {
                    audioElement.play();
                } else {
                    audioElement.pause();
                }
            });

            rewind?.addEventListener('click', () => seekDelta(-5));
            forward?.addEventListener('click', () => seekDelta(5));

            audioElement.addEventListener('play', updatePlayPause);
            audioElement.addEventListener('pause', updatePlayPause);

            wave.on('audioprocess', (time) => {
                highlightSegment(segmentForTime(time));
            });

            wave.on('seek', () => {
                highlightSegment(segmentForTime(wave.getCurrentTime()));
            });

            wave.on('ready', () => {
                renderMarkers(audioElement.duration || wave.getDuration());
            });

            if (audioElement.readyState >= 1) {
                renderMarkers(audioElement.duration);
            } else {
                audioElement.addEventListener('loadedmetadata', () => {
                    renderMarkers(audioElement.duration);
                });
            }

            transcriptList?.addEventListener('click', (event) => {
                const item = event.target.closest('[data-segment-index]');

                if (!item) {
                    return;
                }

                const start = parseFloat(item.dataset.start ?? '');

                if (Number.isFinite(start)) {
                    scrollLock = true;
                    audioElement.currentTime = start;
                    const total = getDuration();
                    if (Number.isFinite(total) && total > 0) {
                        wave.seekTo(Math.min(1, Math.max(0, start / total)));
                    }
                    if (audioElement.paused) {
                        audioElement.play();
                    }
                    setTimeout(() => {
                        scrollLock = false;
                    }, 600);
                }
            });

            transcriptList?.addEventListener('click', (event) => {
                const button = event.target.closest('[data-action="copy-snippet"]');

                if (!button) {
                    return;
                }

                event.stopPropagation();
                const text = button.dataset.text ?? '';

                if (!navigator.clipboard) {
                    return;
                }

                navigator.clipboard.writeText(text).then(() => {
                    const original = button.textContent;
                    button.textContent = 'Copied!';
                    setTimeout(() => {
                        button.textContent = original;
                    }, 1500);
                });
            });

            const filterTranscript = () => {
                const query = (searchInput?.value || '').trim().toLowerCase();

                if (!transcriptList) {
                    return;
                }

                Array.from(transcriptList.children).forEach((item) => {
                    const content = item.querySelector('[data-content]');
                    const text = (content?.textContent || '').toLowerCase();
                    const match = query === '' || text.includes(query);
                    item.style.display = match ? '' : 'none';
                });
            };

            searchInput?.addEventListener('input', filterTranscript);
            clearSearch?.addEventListener('click', () => {
                if (!searchInput) {
                    return;
                }

                searchInput.value = '';
                filterTranscript();
                searchInput.focus();
            });

            document.addEventListener('keydown', (event) => {
                const active = document.activeElement;
                const tag = active?.tagName?.toLowerCase();
                const isTyping = tag === 'input' || tag === 'textarea';

                if (isTyping) {
                    return;
                }

                if (event.code === 'Space') {
                    event.preventDefault();
                    if (audioElement.paused) {
                        audioElement.play();
                    } else {
                        audioElement.pause();
                    }
                }

                if (event.key === 'ArrowRight') {
                    event.preventDefault();
                    seekDelta(5);
                } else if (event.key === 'ArrowLeft') {
                    event.preventDefault();
                    seekDelta(-5);
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    setPlaybackRate(playbackRate + rateStep);
                } else if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    setPlaybackRate(playbackRate - rateStep);
                }
            });

            setPlaybackRate(playbackRate);
            updatePlayPause();
        });
    </script>
@endpush
