<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Actions\StopImpersonating;
use App\Http\Controllers\BillingWebhookController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\Student\StudentLearningController;
use App\Livewire\Student\AssignmentPortal;
use App\Livewire\Student\AttendanceSchedule;
use App\Livewire\Student\CoursePlayer;
use App\Livewire\Student\Dashboard;
use App\Livewire\Student\PaymentHistory;
use App\Livewire\Student\QuizPlayer;
use App\Livewire\Student\StudentProfile;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', HealthCheckController::class)->name('health');

Route::post('/impersonation/stop', function (StopImpersonating $action) {
    $action->execute();

    return redirect('/admin');
})->name('impersonation.stop');

Route::get('/courses/{slug}/preview', function (string $slug) {
    $course = Course::where('slug', $slug)->firstOrFail();

    return view('catalog.preview', compact('course'));
})->name('courses.preview');

// Calendar Views & ICS Subscriptions per spec
Route::get('/calendar/feed/{token}.ics', [CalendarFeedController::class, 'feed'])->name('calendar.feed');
Route::get('/sessions/{session}/ics', [CalendarFeedController::class, 'downloadSessionIcs'])->name('calendar.session.ics');
Route::get('/calendar/events', [CalendarFeedController::class, 'eventsJson'])->name('calendar.events');

// Enrollment Routes per spec (Section F)
Route::middleware('auth')->group(function (): void {
    Route::post('/enroll', [EnrollmentController::class, 'store'])->name('enrollments.store');
    Route::get('/enrollments/{enrollment}/timeline', [EnrollmentController::class, 'timeline'])->name('enrollments.timeline');
});

// Billing & Payment Routes per spec (Section F)
Route::post('/webhooks/stripe', [BillingWebhookController::class, 'handleStripe'])->name('webhooks.stripe');
Route::get('/invoices/{invoice}/status', [InvoiceController::class, 'status'])->name('invoices.status');
Route::get('/invoices/{invoice}', [InvoiceController::class, 'viewInvoice'])->name('invoices.view');

// Student Portal Routes per spec (Section D)
Route::middleware('auth')->prefix('student')->name('student.')->group(function (): void {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/courses/{courseSlug}', CoursePlayer::class)->name('course.player');
    Route::get('/assignments', AssignmentPortal::class)->name('assignments');
    Route::get('/quizzes', QuizPlayer::class)->name('quizzes');
    Route::get('/schedule', AttendanceSchedule::class)->name('schedule');
    Route::get('/payments', PaymentHistory::class)->name('payments');
    Route::get('/profile', StudentProfile::class)->name('profile');

    // Streaming, Downloads & Playback Ping
    Route::get('/lessons/{lesson}/stream', [StudentLearningController::class, 'streamVideo'])->name('lessons.stream');
    Route::get('/resources/{resource}/download', [StudentLearningController::class, 'downloadResource'])->name('resources.download');
    Route::post('/lessons/{lesson}/progress', [StudentLearningController::class, 'updateVideoProgress'])->name('lessons.progress');
});
