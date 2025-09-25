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
    $qaScore = $call?->qaScores?->first();
@endphp

<div class="space-y-8" x-data="{ }">
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
            @if ($qaScore)
                <div class="rounded border border-brand-300 bg-brand-50 px-3 py-2 text-brand-700 shadow-sm dark:border-brand-400/60 dark:bg-brand-500/10 dark:text-brand-200">
                    <span class="block text-[10px] font-semibold uppercase tracking-wide text-brand-500/80">QA Score</span>
                    <span>{{ $qaScore->total_score }} / 100</span>
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
</div>

@push('scripts')
    <script src="https://unpkg.com/wavesurfer.js@7/dist/wavesurfer.js"></script>
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
