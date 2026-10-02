<?php

namespace App\Providers;

use App\Http\Middleware\SelectLibraryPortal;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LibraryPortalFlowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config(['auth.guards.library_student' => ['driver' => 'session', 'provider' => config('auth.guards.web.provider', 'users')]]);
    }

    public function boot(): void
    {
        $this->app['router']->pushMiddlewareToGroup('web', SelectLibraryPortal::class);
        Authenticate::redirectUsing(fn ($r) => $r->is('student/*') ? route('student.login') : route('login'));
        $this->loadRoutesFrom(base_path('routes/library-portal-flow.php'));
        $this->app->make(ExceptionHandler::class)->renderable(function (AuthenticationException $e, Request $r) {
            if (! $r->expectsJson() && $r->is('student/*')) {
                return redirect()->route('student.login');
            }

            return null;
        });
        $this->app->make(ExceptionHandler::class)->renderable(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }
            $target = $request->is('student/*', 'student-login', 'student-login/*') ? route('student.login') : ($request->is('admission') ? route('admission.create') : route('login'));

            return redirect($target)->with('error', 'यह फॉर्म पुराना हो गया है। ताजा पेज खुल गया है; कृपया दोबारा सबमिट करें।');
        });
        $this->app->booted(function () {
            $this->app->make(Kernel::class)->addToMiddlewarePriorityBefore(AuthenticatesRequests::class, SelectLibraryPortal::class);
            Schedule::command('library:fee-reminders')->timezone('Asia/Kolkata')->dailyAt('09:00')->withoutOverlapping();
            Schedule::command('library:deliver-mail')->everyMinute()->withoutOverlapping();
        });
    }
}
