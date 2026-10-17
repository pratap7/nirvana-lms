<?php

declare(strict_types=1);

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
