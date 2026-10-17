<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Actions\StopImpersonating;
use App\Http\Controllers\HealthCheckController;
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
