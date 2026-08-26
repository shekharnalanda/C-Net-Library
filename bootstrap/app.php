<?php

use App\Http\Middleware\AuthenticateMobileApi;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureBranchScope;
use App\Http\Middleware\EnsureGlobalAdmin;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureStudent;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::group([], base_path('routes/lockers.php'));
            Route::group([], base_path('routes/branches.php'));
            Route::group([], base_path('routes/students.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'admin.branch' => EnsureBranchScope::class,
            'global-admin' => EnsureGlobalAdmin::class,
            'permission' => EnsurePermission::class,
            'student' => EnsureStudent::class,
            'mobile.auth' => AuthenticateMobileApi::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Central exception reporting hooks will be added for production.
    })->create();
