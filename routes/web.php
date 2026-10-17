<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Actions\StopImpersonating;
use App\Http\Controllers\BillingWebhookController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\InvoiceController;
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
