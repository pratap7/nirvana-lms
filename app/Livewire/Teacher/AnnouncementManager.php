<?php

declare(strict_types=1);

namespace App\Livewire\Teacher;

use App\Domain\Communication\Models\Announcement;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TeacherScopeService;
use App\Domain\Scheduling\Models\Batch;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.teacher')]
class AnnouncementManager extends Component
{
    public ?string $batch_id = null;

    public string $title = '';

    public string $content = '';

    public ?string $feedbackMessage = null;

    public function mount(TeacherScopeService $scopeService): void
    {
        /** @var User $user */
        $user = Auth::user();
        $firstBatchId = $scopeService->getAssignedBatchIds($user)->first();
        if ($firstBatchId) {
            $this->batch_id = (string) $firstBatchId;
        }
    }

    public function postAnnouncement(TeacherScopeService $scopeService): void
    {
        $this->validate([
            'batch_id' => 'required|exists:batches,id',
            'title' => 'required|string|max:200',
            'content' => 'required|string|max:5000',
        ]);

        /** @var User $user */
        $user = Auth::user();

        /** @var Batch $batch */
        $batch = Batch::query()->findOrFail((int) $this->batch_id);

        if (! $scopeService->canAccessBatch($user, $batch)) {
            abort(403, 'Unauthorized to post announcements to this batch.');
        }

        Announcement::create([
            'batch_id' => $batch->id,
            'course_id' => $batch->course_id,
            'title' => $this->title,
            'content' => $this->content,
            'author_id' => $user->id,
            'published_at' => CarbonImmutable::now(),
        ]);

        $this->title = '';
        $this->content = '';
        $this->feedbackMessage = 'Announcement broadcast to cohort successfully.';
    }

    public function render(TeacherScopeService $scopeService): View
    {
        /** @var User $user */
        $user = Auth::user();

        $assignedBatchIds = $scopeService->getAssignedBatchIds($user);

        /** @var Collection<int, Batch> $batches */
        $batches = Batch::query()->whereIn('id', $assignedBatchIds)->get();

        /** @var Collection<int, Announcement> $announcements */
        $announcements = Announcement::query()
            ->whereIn('batch_id', $assignedBatchIds)
            ->with(['batch', 'author'])
            ->latest('published_at')
            ->take(20)
            ->get();

        return view('livewire.teacher.announcement-manager', [
            'batches' => $batches,
            'announcements' => $announcements,
        ]);
    }
}
