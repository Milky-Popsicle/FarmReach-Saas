<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Paginator::useBootstrapFive();

        Gate::policy(User::class, UserPolicy::class);

        Gate::define(
            'viewAdminAnalytics',
            fn (User $user): bool => $user->isAdmin(),
        );

        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by(
                $request->user()?->getAuthIdentifier() ?: $request->ip(),
            );
        });

        View::composer('layouts.app', function ($view): void {
            if (! Auth::check()) {
                return;
            }

            $user = Auth::user();

            $view->with([
                'unreadNotificationsCount' => $user->unreadNotifications()->count(),
                'latestNotifications' => $user->notifications()->latest()->take(8)->get(),
            ]);
        });
    }
}
