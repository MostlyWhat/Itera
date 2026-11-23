<?php

namespace App\Providers;

use App\Enums\SortingMode;
use App\Priority\TaskOrder;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The way Tasks are put in order (FR-5): the signed-in user's sorting
        // mode (ITERA-08), or Stack Order without a user.
        $this->app->bind(TaskOrder::class, function (Application $app): TaskOrder {
            $mode = $app->make('auth')->user()?->sorting_mode;

            return ($mode instanceof SortingMode ? $mode : SortingMode::Stack)->strategy();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureLocalDevelopment();
    }

    /**
     * Local development only: mark new accounts as verified, so that a
     * developer does not need the verification email to use the app.
     */
    protected function configureLocalDevelopment(): void
    {
        if (! $this->app->isLocal()) {
            return;
        }

        Event::listen(Registered::class, function (Registered $event): void {
            $user = $event->user;

            if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
