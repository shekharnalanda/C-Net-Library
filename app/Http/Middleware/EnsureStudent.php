<?php

namespace App\Http\Middleware;

use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'student') {
            abort(403, 'Student portal access only.');
        }

        $studentIsActive = $user->status && Student::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if (! $studentIsActive) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'This student portal account is inactive.']);
        }

        return $next($request);
    }
}
