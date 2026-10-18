<div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; margin: 0; color: var(--text-main);">Course Quizzes</h1>
            <p style="color: var(--text-muted); margin: 4px 0 0 0;">Test your conceptual understanding and scriptural retention.</p>
        </div>
        @if($isTakingQuiz || $latestAttempt)
            <button wire:click="resetQuiz" class="btn btn-outline">← Back to Quizzes</button>
        @endif
    </div>

    @if (session()->has('error'))
        <div style="background: #fee2e2; color: #991b1b; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600;">
            {{ session('error') }}
        </div>
    @endif

    @if($isTakingQuiz && $activeQuiz)
        <!-- Quiz In-Progress Taking View -->
        <div class="card" style="max-width: 800px; margin: 0 auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <span class="badge badge-info">{{ $activeQuiz->course?->title }}</span>
                    <h2 style="font-size: 1.35rem; font-weight: 800; margin: 4px 0 0 0;">{{ $activeQuiz->title }}</h2>
                </div>
                @if($activeQuiz->duration_minutes)
                    <div style="background: #f1f5f9; padding: 4px 10px; border-radius: 6px; font-weight: 700; color: #475569;">
                        ⏱️ {{ $activeQuiz->duration_minutes }} Mins
                    </div>
                @endif
            </div>

            <form wire:submit.prevent="submitQuiz" style="display: flex; flex-direction: column; gap: 2rem;">
                @foreach($activeQuiz->questions as $index => $q)
                    <div style="border-bottom: 1px solid var(--border); padding-bottom: 1.5rem;">
                        <div style="font-weight: 700; font-size: 1.05rem; margin-bottom: 0.75rem; color: var(--text-main);">
                            Question {{ $index + 1 }}. {{ $q->question_text }}
                            <span style="font-size: 0.8rem; font-weight: 500; color: var(--text-muted);">({{ $q->points }} pt)</span>
                        </div>

                        @if($q->type === 'single_choice' || $q->type === 'true_false')
                            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                @foreach($q->options as $opt)
                                    <label style="display: flex; align-items: center; gap: 8px; font-size: 0.95rem; cursor: pointer; padding: 6px 10px; border-radius: 6px; background: var(--surface-subtle);">
                                        <input type="radio" wire:model="answers.{{ $q->id }}" value="{{ $opt['id'] }}">
                                        <span>{{ $opt['text'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @elseif($q->type === 'multiple_choice')
                            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                @foreach($q->options as $opt)
                                    <label style="display: flex; align-items: center; gap: 8px; font-size: 0.95rem; cursor: pointer; padding: 6px 10px; border-radius: 6px; background: var(--surface-subtle);">
                                        <input type="checkbox" wire:model="answers.{{ $q->id }}" value="{{ $opt['id'] }}">
                                        <span>{{ $opt['text'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <input type="text" wire:model="answers.{{ $q->id }}" placeholder="Type your short answer..." style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 6px; font-size: 0.95rem;">
                        @endif
                    </div>
                @endforeach

                <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem; font-size: 1rem; align-self: flex-start;">
                    Submit Quiz for Grading →
                </button>
            </form>
        </div>
    @elseif($latestAttempt && $activeQuiz)
        <!-- Quiz Results & Explanations Review View -->
        <div class="card" style="max-width: 800px; margin: 0 auto;">
            <div style="text-align: center; border-bottom: 1px solid var(--border); padding-bottom: 1.5rem; margin-bottom: 1.5rem;">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">
                    {{ $latestAttempt->passed ? '🎉' : '📖' }}
                </div>
                <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;">
                    {{ $latestAttempt->passed ? 'Quiz Passed!' : 'Practice Again' }}
                </h2>
                <div style="font-size: 1.25rem; font-weight: 800; color: {{ $latestAttempt->passed ? '#10b981' : '#f59e0b' }}; margin: 8px 0;">
                    Score: {{ $latestAttempt->score }} / {{ $latestAttempt->max_score }} ({{ (int) $latestAttempt->percentage }}%)
                </div>
                <div style="font-size: 0.85rem; color: var(--text-muted);">
                    Pass mark: {{ $activeQuiz->pass_percentage }}% • Attempt #{{ $latestAttempt->attempt_number }} of {{ $activeQuiz->max_attempts }}
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                @foreach($activeQuiz->questions as $index => $q)
                    <div style="padding: 1rem; border-radius: 8px; background: #f8fafc; border: 1px solid var(--border);">
                        <div style="font-weight: 700; margin-bottom: 8px;">
                            {{ $index + 1 }}. {{ $q->question_text }}
                        </div>
                        <div style="font-size: 0.85rem; color: #475569; margin-bottom: 6px;">
                            <strong>Your Answer:</strong>
                            @php
                                $ans = $latestAttempt->answers[$q->id] ?? $latestAttempt->answers[(string) $q->id] ?? 'None';
                            @endphp
                            {{ is_array($ans) ? implode(', ', $ans) : $ans }}
                        </div>
                        @if($q->explanation)
                            <div style="background: #eef2ff; color: #3730a3; padding: 8px; border-radius: 6px; font-size: 0.85rem;">
                                💡 <strong>Explanation:</strong> {{ $q->explanation }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <!-- Quiz List View -->
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem;">
            @forelse($quizzes as $quiz)
                @php
                    $bestAttempt = $quiz->attempts->sortByDesc('percentage')->first();
                    $userAttempts = $quiz->attempts->count();
                @endphp
                <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                            <span class="badge badge-info">{{ $quiz->course?->title }}</span>
                            @if($bestAttempt && $bestAttempt->passed)
                                <span class="badge badge-success">Passed ({{ (int) $bestAttempt->percentage }}%)</span>
                            @elseif($bestAttempt)
                                <span class="badge badge-warning">{{ (int) $bestAttempt->percentage }}%</span>
                            @endif
                        </div>
                        <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0 0 6px 0;">{{ $quiz->title }}</h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0 0 1rem 0;">{{ $quiz->description }}</p>
                    </div>

                    <div style="border-top: 1px solid var(--border); padding-top: 1rem; margin-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
                        <div style="font-size: 0.8rem; color: var(--text-muted);">
                            {{ $quiz->questions->count() }} Questions •
                            Attempts: {{ $userAttempts }}/{{ $quiz->max_attempts }}
                        </div>
                        @if($userAttempts < $quiz->max_attempts)
                            <button wire:click="startQuiz('{{ $quiz->ulid }}')" class="btn btn-primary" style="padding: 4px 12px; font-size: 0.85rem;">
                                {{ $userAttempts > 0 ? 'Retake Quiz' : 'Start Quiz' }}
                            </button>
                        @else
                            <span style="font-size: 0.8rem; color: #94a3b8; font-style: italic;">Max attempts reached</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--text-muted);">
                    No quizzes currently available.
                </div>
            @endforelse
        </div>
    @endif
</div>
