<?php

namespace App\Providers;

use App\Mail\GmailOAuthTokenProvider;
use App\Mail\Transport\GmailOAuthTransport;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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

        Mail::extend('gmail-oauth', function (): GmailOAuthTransport {
            $gmail = (array) config('services.gmail', []);

            $tokens = new GmailOAuthTokenProvider(
                http: $this->app->make(Factory::class),
                cache: $this->app->make(CacheRepository::class),
                encrypter: $this->app->make(Encrypter::class),
                clientId: (string) ($gmail['client_id'] ?? ''),
                clientSecret: (string) ($gmail['client_secret'] ?? ''),
                refreshToken: (string) ($gmail['refresh_token'] ?? ''),
                tokenUri: (string) ($gmail['token_uri'] ?? 'https://oauth2.googleapis.com/token'),
            );

            return new GmailOAuthTransport(
                http: $this->app->make(Factory::class),
                tokens: $tokens,
                apiUri: (string) ($gmail['api_uri'] ?? 'https://gmail.googleapis.com/gmail/v1'),
            );
        });

        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by(
                $request->user()?->getAuthIdentifier() ?: $request->ip(),
            );
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $submittedLoginId = $request->input('login_id');
            $loginId = is_string($submittedLoginId) ? Str::lower(trim($submittedLoginId)) : '';

            return Limit::perMinute(5)->by($loginId.'|'.$request->ip());
        });

        RateLimiter::for('register', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip());
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
