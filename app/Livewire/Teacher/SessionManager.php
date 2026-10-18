<?php

declare(strict_types=1);

namespace App\Livewire\Teacher;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TeacherScopeService;
use App\Domain\Scheduling\Enums\MeetingProviderType;
use App\Domain\Scheduling\Enums\SessionStatus;
use App\Domain\Scheduling\Models\Batch;
use App\Domain\Scheduling\Models\Session;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.teacher')]
class SessionManager extends Component
{
    public ?string $batch_id = null;

    public string $title = '';

    public string $starts_at = '';

    public string $ends_at = '';

    public string $meeting_link = '';

    public string $meeting_provider = 'manual';

    public bool $is_online = true;

    public ?string $feedbackMessage = null;

    public ?string $errorMessage = null;

    public function mount(TeacherScopeService $scopeService): void
    {
        /** @var User $user */
        $user = Auth::user();
        $firstBatchId = $scopeService->getAssignedBatchIds($user)->first();
        if ($firstBatchId) {
            $this->batch_id = (string) $firstBatchId;
        }

        $this->starts_at = now()->addDay()->format('Y-m-d\TH:i');
        $this->ends_at = now()->addDay()->addHours(2)->format('Y-m-d\TH:i');
    }

    public function createSession(TeacherScopeService $scopeService): void
    {
        $this->validate([
            'batch_id' => 'required|exists:batches,id',
            'title' => 'required|string|max:200',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'meeting_link' => 'nullable|url|max:500',
            'meeting_provider' => 'required|string|in:zoom,google_meet,manual',
        ]);

        /** @var User $user */
        $user = Auth::user();

        /** @var Batch $batch */
        $batch = Batch::query()->findOrFail((int) $this->batch_id);

        if (! $scopeService->canAccessBatch($user, $batch)) {
            abort(403, 'Unauthorized to schedule sessions for this batch.');
        }

        $session = Session::create([
            'batch_id' => $batch->id,
            'title' => $this->title,
            'slug' => Str::slug($this->title).'-'.Str::lower(Str::random(6)),
            'starts_at' => CarbonImmutable::parse($this->starts_at),
            'ends_at' => CarbonImmutable::parse($this->ends_at),
            'timezone' => $user->timezone ?? 'Asia/Kolkata',
            'is_online' => $this->is_online,
            'meeting_provider' => MeetingProviderType::from($this->meeting_provider),
            'meeting_link' => $this->meeting_link ?: null,
            'status' => SessionStatus::Scheduled,
        ]);

        $this->title = '';
        $this->meeting_link = '';
        $this->feedbackMessage = "Session '{$session->title}' scheduled successfully.";
    }

    public function render(TeacherScopeService $scopeService): View
    {
        /** @var User $user */
        $user = Auth::user();

        $assignedBatchIds = $scopeService->getAssignedBatchIds($user);

        /** @var Collection<int, Batch> $batches */
        $batches = Batch::query()->whereIn('id', $assignedBatchIds)->with('course')->get();

        /** @var Collection<int, Session> $sessions */
        $sessions = Session::query()
            ->whereIn('batch_id', $assignedBatchIds)
            ->with('batch.course')
            ->orderByDesc('starts_at')
            ->take(30)
            ->get();

        return view('livewire.teacher.session-manager', [
            'batches' => $batches,
            'sessions' => $sessions,
        ]);
    }
}
