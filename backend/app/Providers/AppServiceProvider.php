<?php

namespace App\Providers;

use App\Services\Arena\GuestContext;
use App\Services\Battle\DamageCalculator;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // GuestContext はリクエストスコープで session を共有するため scoped で登録。
        $this->app->scoped(GuestContext::class, function ($app) {
            return new GuestContext($app->make(Session::class));
        });

        // Illuminate\Contracts\Session\Session は標準では未バインドなので、
        // current session store を返すよう明示的に解決する。
        $this->app->bind(Session::class, fn ($app) => $app->make('session.store'));

        // DamageCalculator はスカラー値を config から注入する必要があるため明示バインド。
        $this->app->bind(DamageCalculator::class, fn () => DamageCalculator::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
