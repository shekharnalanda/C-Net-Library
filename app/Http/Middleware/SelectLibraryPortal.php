<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SelectLibraryPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $student = $request->is('student/*', 'student-login', 'student-login/*');
        $admin = $request->is('admin/*', 'admin-login', 'login', 'logout', 'setup-admin');
        Auth::shouldUse($student ? 'library_student' : (! $admin && Auth::guard('library_student')->check() && ! Auth::guard('web')->check() ? 'library_student' : 'web'));

        return $next($request);
    }
}
