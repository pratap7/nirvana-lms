<div style="display: grid; grid-template-columns: 1fr 340px; gap: 2rem;">
    <!-- Main Content Area: Video/PDF Player & Lesson Body -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Lesson Header -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <a href="/student/dashboard" style="font-size: 0.85rem; color: var(--text-muted); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; margin-bottom: 6px;">
                    ← Back to Dashboard
                </a>
                <h1 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--text-main);">
                    {{ $activeLesson?->title ?? 'Select a Lesson' }}
                </h1>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
                    {{ $course->title }} • {{ $batch?->name ?? 'Standard Batch' }}
                </div>
            </div>

            @if($activeLesson)
                <button wire:click="toggleComplete" class="btn {{ $activeProgress?->is_completed ? 'btn-success' : 'btn-outline' }}">
                    @if($activeProgress?->is_completed)
                        ✓ Completed
                    @else
                        Mark as Complete
                    @endif
                </button>
            @endif
        </div>

        @if($activeLesson)
            <!-- Media Player Container -->
            @if($activeLesson->content_type === 'video')
                <div class="video-container" id="video-wrapper">
                    <!-- Dynamic Anti-Piracy Watermark -->
                    <div class="video-watermark" id="watermark">
                        {{ $watermarkData['text'] }}
                    </div>

                    <video id="player" controls controlsList="nodownload" style="width: 100%; height: 100%; object-fit: contain;">
                        <source src="{{ $signedStreamUrl }}" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>

                <!-- Playback Speed Controls -->
                <div style="display: flex; justify-content: space-between; align-items: center; background: white; border: 1px solid var(--border); padding: 0.5rem 1rem; border-radius: 8px;">
                    <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);">
                        Playback Speed:
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        @foreach([0.75, 1.0, 1.25, 1.5, 2.0] as $speed)
                            <button type="button" onclick="setSpeed({{ $speed }})" class="btn btn-outline" style="padding: 2px 8px; font-size: 0.75rem;">
                                {{ $speed }}x
                            </button>
                        @endforeach
                    </div>
                </div>
            @elseif($activeLesson->content_type === 'pdf')
                <div class="card" style="padding: 0; overflow: hidden; height: 600px;">
                    @php
                        $pdfContent = $activeLesson->contents->where('type', 'pdf')->first();
                    @endphp
                    <iframe src="{{ $pdfContent?->media_path ?? '/sample.pdf' }}" width="100%" height="100%" style="border: none;"></iframe>
                </div>
            @else
                <!-- Rich Reading / Text Content -->
                <div class="card">
                    @php
                        $textContent = $activeLesson->contents->first();
                    @endphp
                    <div style="font-size: 1rem; line-height: 1.7; color: var(--text-main);">
                        {!! nl2br(e($textContent?->body ?? 'This lesson contains guided readings and reflections.')) !!}
                    </div>
                </div>
            @endif

            <!-- Downloadable Resources Section -->
            @if($activeLesson->resources->count() > 0)
                <div class="card">
                    <h3 class="card-title" style="margin-bottom: 0.75rem;">Downloadable Resources & Handouts</h3>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        @foreach($activeLesson->resources as $res)
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem; background: var(--surface-subtle); border-radius: 6px;">
                                <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem;">
                                    <span>📄</span>
                                    <strong>{{ $res->title }}</strong>
                                    <span style="font-size: 0.75rem; color: var(--text-muted);">({{ strtoupper($res->type) }})</span>
                                </div>
                                <a href="/student/resources/{{ $res->ulid }}/download?batch={{ $batch?->ulid }}" class="btn btn-secondary" style="padding: 3px 8px; font-size: 0.8rem;">
                                    Download ⬇️
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @else
            <div class="card" style="text-align: center; padding: 3rem;">
                <p style="color: var(--text-muted);">No lessons available in this course yet.</p>
            </div>
        @endif
    </div>

    <!-- Right Sidebar: Modules & Lessons Navigation -->
    <div class="card" style="align-self: flex-start; max-height: calc(100vh - 120px); overflow-y: auto; padding: 1.25rem;">
        <div style="margin-bottom: 1.25rem; border-bottom: 1px solid var(--border); padding-bottom: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted);">Course Progress</span>
                <span style="font-size: 0.9rem; font-weight: 800; color: var(--primary);">{{ (int) ($courseProgress?->overall_percentage ?? 0) }}%</span>
            </div>
            <div style="width: 100%; height: 6px; background: #e2e8f0; border-radius: 9999px; overflow: hidden;">
                <div style="width: {{ $courseProgress?->overall_percentage ?? 0 }}%; height: 100%; background: #4f46e5; border-radius: 9999px; transition: width 0.3s ease;"></div>
            </div>
        </div>

        <h3 style="font-size: 1rem; font-weight: 800; margin: 0 0 1rem 0; color: var(--text-main);">Curriculum Outline</h3>

        <div style="display: flex; flex-direction: column; gap: 1rem;">
            @foreach($course->modules as $modIndex => $module)
                <div>
                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 6px;">
                        Module {{ $modIndex + 1 }}: {{ $module->name }}
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        @foreach($module->lessons as $lesson)
                            @php
                                $isActive = ($activeLesson && $activeLesson->id === $lesson->id);
                                $isCompleted = in_array($lesson->id, $completedLessonIds, true);
                            @endphp
                            <button wire:click="selectLesson('{{ $lesson->ulid }}')"
                                    style="display: flex; align-items: center; justify-content: space-between; width: 100%; text-align: left; padding: 8px 10px; border-radius: 6px; font-size: 0.85rem; border: none; cursor: pointer; background: {{ $isActive ? 'var(--primary-light)' : 'transparent' }}; color: {{ $isActive ? 'var(--primary)' : 'var(--text-main)' }}; font-weight: {{ $isActive ? '700' : '500' }}; transition: background 0.15s ease;">
                                <div style="display: flex; align-items: center; gap: 8px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    @if($isCompleted)
                                        <span style="color: #10b981; font-weight: bold;">✓</span>
                                    @else
                                        <span style="color: #94a3b8;">○</span>
                                    @endif
                                    <span>{{ $lesson->title }}</span>
                                </div>
                                <span style="font-size: 0.75rem; color: #94a3b8;">
                                    {{ $lesson->duration_minutes ? $lesson->duration_minutes . 'm' : '' }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- HTML5 Video Progress Tracking & Speed Control -->
    <script>
        function setSpeed(speed) {
            const player = document.getElementById('player');
            if (player) {
                player.playbackRate = speed;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const player = document.getElementById('player');
            if (!player) return;

            // Resume where left off
            const startSec = {{ $activeProgress?->last_position_seconds ?? 0 }};
            if (startSec > 0) {
                player.currentTime = startSec;
            }

            let lastPing = 0;
            player.addEventListener('timeupdate', () => {
                const now = Date.now();
                if (now - lastPing > 4000 && player.duration > 0) {
                    lastPing = now;
                    const percent = Math.floor((player.currentTime / player.duration) * 100);
                    @this.recordProgress(Math.floor(player.currentTime), percent);
                }
            });
        });
    </script>
</div>
