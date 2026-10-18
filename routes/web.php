<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Actions\StopImpersonating;
use App\Domain\Reporting\Controllers\ReportExportDownloadController;
use App\Http\Controllers\BillingWebhookController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\CertificateVerificationController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\EventIcsController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PublicArticleController;
use App\Http\Controllers\PublicCatalogController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\PublicFacultyController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\Student\StudentLearningController;
use App\Livewire\Certification\CertificateVerification;
use App\Livewire\Certification\StudentCompletionChecklist;
use App\Livewire\Communication\NotificationOutboxDashboard;
use App\Livewire\Communication\TemplateManager;
use App\Livewire\Events\EventAttendanceReport;
use App\Livewire\Events\EventShow;
use App\Livewire\Events\StaffEventCheckIn;
use App\Livewire\Moderator\ModeratorConsole;
use App\Livewire\Reporting\ReportingDashboard;
use App\Livewire\Student\AssignmentPortal;
use App\Livewire\Student\AttendanceSchedule;
use App\Livewire\Student\CoursePlayer;
use App\Livewire\Student\Dashboard;
use App\Livewire\Student\PaymentHistory;
use App\Livewire\Student\QuizPlayer;
use App\Livewire\Student\StudentProfile;
use App\Livewire\Teacher\AnnouncementManager;
use App\Livewire\Teacher\AssignmentGrading;
use App\Livewire\Teacher\AttendanceMarker;
use App\Livewire\Teacher\BatchMessenger;
use App\Livewire\Teacher\BatchRoster;
use App\Livewire\Teacher\SessionManager;
use App\Livewire\Teacher\TeacherDashboard;
use App\Livewire\Teacher\WellbeingEscalation;
use Illuminate\Support\Facades\Route;

// Public Site Routes (Section H - Public Site)
Route::get('/', [PublicSiteController::class, 'home'])->name('home');
Route::get('/courses', [PublicCatalogController::class, 'index'])->name('courses.index');
Route::get('/courses/{slug}', [PublicCatalogController::class, 'show'])->name('courses.show');
Route::get('/schedule', [PublicSiteController::class, 'schedule'])->name('schedule');
Route::get('/events-calendar', [PublicEventController::class, 'calendar'])->name('events.calendar');
Route::get('/faculty', [PublicFacultyController::class, 'index'])->name('faculty.index');
Route::get('/faculty/{user:ulid}', [PublicFacultyController::class, 'show'])->name('faculty.show');
Route::get('/about', [PublicSiteController::class, 'about'])->name('about');
Route::get('/contact', [PublicSiteController::class, 'contact'])->name('contact');
Route::post('/contact', [PublicSiteController::class, 'contactSubmit'])->name('contact.submit');
Route::get('/articles', [PublicArticleController::class, 'index'])->name('articles.index');
Route::get('/articles/{slug}', [PublicArticleController::class, 'show'])->name('articles.show');
Route::get('/faq', [PublicSiteController::class, 'faq'])->name('faq');
Route::get('/testimonials', [PublicSiteController::class, 'testimonials'])->name('testimonials');
Route::get('/sitemap.xml', [PublicSiteController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [PublicSiteController::class, 'robots'])->name('robots');
Route::get('/set-locale/{locale}', [PublicSiteController::class, 'setLocale'])->name('set-locale');
Route::get('/login', fn () => redirect('/admin/login'))->name('login');

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
    Route::get('/courses/{courseSlug}/completion/{batchId?}', StudentCompletionChecklist::class)->name('completion.checklist');

    // Streaming, Downloads & Playback Ping
    Route::get('/lessons/{lesson}/stream', [StudentLearningController::class, 'streamVideo'])->name('lessons.stream');
    Route::get('/resources/{resource}/download', [StudentLearningController::class, 'downloadResource'])->name('resources.download');
    Route::post('/lessons/{lesson}/progress', [StudentLearningController::class, 'updateVideoProgress'])->name('lessons.progress');
});

// Teacher Portal Routes per spec (Section E)
Route::middleware('auth')->prefix('teacher')->name('teacher.')->group(function (): void {
    Route::get('/dashboard', TeacherDashboard::class)->name('dashboard');
    Route::get('/roster/{batch?}', BatchRoster::class)->name('roster');
    Route::get('/attendance/{session?}', AttendanceMarker::class)->name('attendance');
    Route::get('/grading', AssignmentGrading::class)->name('grading');
    Route::get('/sessions', SessionManager::class)->name('sessions');
    Route::get('/announcements', AnnouncementManager::class)->name('announcements');
    Route::get('/messages', BatchMessenger::class)->name('messages');
    Route::get('/concerns', WellbeingEscalation::class)->name('concerns');
});

// Academic Moderator Console Routes
Route::middleware('auth')->prefix('moderator')->name('moderator.')->group(function (): void {
    Route::get('/console', ModeratorConsole::class)->name('console');
    Route::get('/communication/outbox', NotificationOutboxDashboard::class)->name('communication.outbox');
    Route::get('/communication/templates', TemplateManager::class)->name('communication.templates');
    Route::get('/reporting', ReportingDashboard::class)->name('reporting.dashboard');
});

// Signed Report Export Download Route (Section H)
Route::get('/reporting/exports/{export}/download', ReportExportDownloadController::class)
    ->name('reporting.exports.download')
    ->middleware('signed');

// Events Module Routes (Section C)
Route::get('/events/{event:slug}', EventShow::class)->name('events.show');
Route::get('/events/{event:slug}/invite', EventShow::class)->name('events.invite');
Route::get('/events/{event:slug}/ics', EventIcsController::class)->name('events.ics');

Route::middleware('auth')->prefix('moderator/events')->name('events.')->group(function (): void {
    Route::get('/check-in/{event?}', StaffEventCheckIn::class)->name('checkin');
    Route::get('/{event}/report', EventAttendanceReport::class)->name('report');
});

// Public Certificate Verification & Open Badges (Rate-limited per Section G spec)
Route::middleware('throttle:60,1')->group(function (): void {
    Route::get('/verify/{publicToken}', CertificateVerification::class)->name('certificates.verify');
    Route::get('/verify/{publicToken}/badge.json', [CertificateVerificationController::class, 'openBadgeJson'])->name('certificates.badge');
    Route::get('/certificates/{publicToken}/download', [CertificateVerificationController::class, 'downloadPdf'])->name('certificates.pdf');
});
