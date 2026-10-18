<div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; margin: 0; color: var(--text-main);">Course Assignments</h1>
            <p style="color: var(--text-muted); margin: 4px 0 0 0;">Submit practical sadhana logs, reflections, and homework.</p>
        </div>
    </div>

    @if (session()->has('success'))
        <div style="background: #d1fae5; color: #065f46; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600;">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div style="background: #fee2e2; color: #991b1b; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600;">
            {{ session('error') }}
        </div>
    @endif

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
        <!-- Left: Assignments List -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            @forelse($assignments as $assignment)
                @php
                    $latest = $assignment->submissions->first();
                    $isSelected = ($selectedAssignment && $selectedAssignment->id === $assignment->id);
                @endphp
                <div class="card" style="cursor: pointer; border-color: {{ $isSelected ? '#4f46e5' : 'var(--border)' }};" wire:click="selectAssignment('{{ $assignment->ulid }}')">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div>
                            <span class="badge badge-info" style="margin-bottom: 6px;">{{ $assignment->course?->title }}</span>
                            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700;">{{ $assignment->title }}</h3>
                        </div>
                        <div>
                            @if($latest)
                                @if($latest->status === 'graded')
                                    <span class="badge badge-success">Graded: {{ $latest->score }}/{{ $assignment->max_score }}</span>
                                @else
                                    <span class="badge badge-info">Submitted (#{{ $latest->submission_number }})</span>
                                @endif
                            @else
                                <span class="badge badge-warning">Due {{ $assignment->due_at->format('d M') }}</span>
                            @endif
                        </div>
                    </div>

                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 8px;">
                        Deadline: {{ $assignment->due_at->format('d M Y, h:i A') }} •
                        Max Score: {{ $assignment->max_score }} pts •
                        Resubmissions Allowed: {{ $assignment->max_resubmissions }}
                    </div>
                </div>
            @empty
                <div class="card" style="padding: 2rem; text-align: center; color: var(--text-muted);">
                    No assignments currently assigned.
                </div>
            @endforelse
        </div>

        <!-- Right: Submission & Feedback Panel -->
        <div>
            @if($selectedAssignment)
                <div class="card">
                    <h2 style="font-size: 1.25rem; font-weight: 800; margin: 0 0 0.5rem 0;">{{ $selectedAssignment->title }}</h2>
                    <div style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 1.5rem;">
                        {!! nl2br(e($selectedAssignment->description)) !!}
                    </div>

                    @php
                        $latestSub = $selectedAssignment->submissions->first();
                    @endphp

                    @if($latestSub)
                        <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <strong style="font-size: 0.9rem;">Your Submission (#{{ $latestSub->submission_number }})</strong>
                                <span style="font-size: 0.8rem; color: var(--text-muted);">Submitted {{ $latestSub->submitted_at->format('d M, h:i A') }}</span>
                            </div>

                            @if($latestSub->text_content)
                                <div style="font-size: 0.85rem; margin-bottom: 8px; color: var(--text-main);">
                                    {!! nl2br(e($latestSub->text_content)) !!}
                                </div>
                            @endif

                            @if($latestSub->link_url)
                                <div style="font-size: 0.85rem; margin-bottom: 8px;">
                                    Link: <a href="{{ $latestSub->link_url }}" target="_blank" rel="noopener noreferrer" style="color: #4f46e5;">{{ $latestSub->link_url }}</a>
                                </div>
                            @endif

                            @if($latestSub->status === 'graded')
                                <div style="border-top: 1px dashed var(--border); padding-top: 8px; margin-top: 8px;">
                                    <div style="font-weight: 700; color: #065f46; font-size: 0.95rem;">
                                        Score: {{ $latestSub->score }} / {{ $selectedAssignment->max_score }}
                                    </div>
                                    @if($latestSub->feedback)
                                        <div style="font-size: 0.85rem; color: #334155; margin-top: 4px;">
                                            Teacher Feedback: <em>"{{ $latestSub->feedback }}"</em>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif

                    <!-- Submit / Resubmit Form -->
                    @if(! $latestSub || $latestSub->submission_number < $selectedAssignment->max_resubmissions)
                        <form wire:submit.prevent="submitAssignment" style="display: flex; flex-direction: column; gap: 1rem;">
                            <h3 style="font-size: 1rem; font-weight: 700; margin: 0;">
                                {{ $latestSub ? 'Resubmit Assignment' : 'New Submission' }}
                            </h3>

                            <div>
                                <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 4px;">Reflection / Response Text</label>
                                <textarea wire:model="textContent" rows="4" style="width: 100%; border: 1px solid var(--border); border-radius: 6px; padding: 8px; font-family: inherit; font-size: 0.9rem;" placeholder="Write your submission answers or notes here..."></textarea>
                            </div>

                            <div>
                                <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 4px;">External Link (Google Drive / GitHub / Video link)</label>
                                <input type="url" wire:model="linkUrl" style="width: 100%; border: 1px solid var(--border); border-radius: 6px; padding: 8px; font-size: 0.9rem;" placeholder="https://...">
                            </div>

                            <div>
                                <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 4px;">Upload Document (PDF / DOC / Image)</label>
                                <input type="file" wire:model="fileUpload" style="font-size: 0.85rem;">
                            </div>

                            <button type="submit" class="btn btn-primary" style="align-self: flex-start;">
                                Submit Assignment 🚀
                            </button>
                        </form>
                    @else
                        <div style="font-size: 0.85rem; color: var(--text-muted); font-style: italic;">
                            You have completed all allowable submissions for this assignment.
                        </div>
                    @endif
                </div>
            @else
                <div class="card" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                    Select an assignment from the left to view details and submit your work.
                </div>
            @endif
        </div>
    </div>
</div>
