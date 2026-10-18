<?php

namespace App\Providers;

use App\Domain\Billing\Gateways\PaymentGateway;
use App\Domain\Billing\Gateways\StripePaymentGateway;
use App\Domain\Catalog\Services\Video\BunnyStreamVideoProvider;
use App\Domain\Catalog\Services\Video\NullVideoProvider;
use App\Domain\Catalog\Services\Video\VideoProvider;
use App\Domain\Communication\Commands\ApplyCommunicationRetentionCommand;
use App\Domain\Communication\Commands\SendScheduledRemindersCommand;
use App\Domain\Communication\Contracts\SmsProvider;
use App\Domain\Communication\Contracts\WhatsAppProvider;
use App\Domain\Communication\Listeners\SessionRescheduledNotificationListener;
use App\Domain\Communication\Services\LogSmsProvider;
use App\Domain\Communication\Services\LogWhatsAppProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\PermissionResolver;
use App\Domain\Scheduling\Events\SessionRescheduled;
use App\Domain\Scheduling\Services\Meeting\GoogleMeetProvider;
use App\Domain\Scheduling\Services\Meeting\MeetingProvider;
use App\Domain\Scheduling\Services\Meeting\NullMeetingProvider;
use App\Domain\Scheduling\Services\Meeting\ZoomMeetingProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Event;
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
        $this->app->singleton(VideoProvider::class, function () {
            $driver = config('services.video.driver', 'null');

            return match ($driver) {
                'bunny' => new BunnyStreamVideoProvider,
                default => new NullVideoProvider,
            };
        });
        $this->app->singleton(MeetingProvider::class, function () {
            $driver = config('services.meeting.driver', 'null');

            return match ($driver) {
                'zoom' => new ZoomMeetingProvider,
                'google_meet' => new GoogleMeetProvider,
                default => new NullMeetingProvider,
            };
        });
        $this->app->singleton(PaymentGateway::class, function () {
            return new StripePaymentGateway;
        });
        $this->app->singleton(WhatsAppProvider::class, function () {
            return new LogWhatsAppProvider;
        });
        $this->app->singleton(SmsProvider::class, function () {
            return new LogSmsProvider;
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                SendScheduledRemindersCommand::class,
                ApplyCommunicationRetentionCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Factory::guessFactoryNamesUsing(function (string $modelName): string {
            return 'Database\\Factories\\'.class_basename($modelName).'Factory';
        });

        // Event-driven domain notifications (Section H)
        Event::listen(
            SessionRescheduled::class,
            SessionRescheduledNotificationListener::class
        );

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
