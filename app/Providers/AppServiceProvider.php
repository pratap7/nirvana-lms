<?php

namespace App\Providers;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\PermissionResolver;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PermissionResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Factory::guessFactoryNamesUsing(function (string $modelName): string {
            return 'Database\\Factories\\'.class_basename($modelName).'Factory';
        });

        // Wire scoped PermissionResolver into Laravel's authorization Gate
        Gate::before(function ($user, string $ability, array $arguments = []): ?bool {
            if (! ($user instanceof User)) {
                return null;
            }

            if ($user->isDeactivated()) {
                return false;
            }

            $scope = $arguments[0] ?? null;
            /** @var PermissionResolver $resolver */
            $resolver = app(PermissionResolver::class);

            if ($resolver->can($user, $ability, $scope)) {
                return true;
            }

            return null; // Fall through to specific policies
        });

        // Automatically enrich all activity logs with client IP, user agent, and impersonation context
        Activity::saving(function (Activity $activity): void {
            if (! app()->runningInConsole() || request()->hasHeader('User-Agent')) {
                $properties = $activity->properties ?? collect();
                $activity->properties = $properties->merge([
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'impersonated_by' => session('impersonated_by'),
                ]);
            }
        });
    }
}
