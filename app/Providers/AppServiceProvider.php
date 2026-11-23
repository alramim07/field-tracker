<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Rbac\PermissionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PermissionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('permission', function (User $user, ?string $key = null): bool {
            if (! $key) {
                return false;
            }

            return app(PermissionService::class)->userHasPermission($user, $key);
        });
    }
}
