<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AclServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // 'system-manager' role bypasses every Policy/Gate check, same as old isAdmin() shortcut.
        Gate::before(function ($user, string $ability) {
            return $user->isAdmin() ?: null;
        });

        // Lets every other permission string be checked via Laravel's normal @can as well:
        // @can('admin/users') ... @endcan
        Gate::define('permission', function ($user, string $privilege) {
            return $user->hasPermission($privilege);
        });

        // Old views used isAllow('admin/users') directly in PHP. Blade equivalent:
        // @permission('admin/users') ... @endpermission
        Blade::if('permission', function (string $privilege) {
            $user = auth()->user();
            return $user && $user->hasPermission($privilege);
        });
    }
}
