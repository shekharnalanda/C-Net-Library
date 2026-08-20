<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = Str::lower(trim($credentials['email']));
        $key = $email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        $remember = $request->boolean('remember');
        $attemptCredentials = [
            'email' => $email,
            'password' => $credentials['password'],
            'status' => true,
        ];

        if (! Auth::attempt($attemptCredentials, $remember)) {
            RateLimiter::hit($key, 60);

            return back()->withErrors([
                'email' => 'Invalid login credentials or inactive account.',
            ])->onlyInput('email');
        }

        $user = Auth::user();
        if ($user?->role === 'student' && ! Student::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            RateLimiter::hit($key, 60);

            return back()->withErrors([
                'email' => 'Invalid login credentials or inactive account.',
            ])->onlyInput('email');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $fallback = auth()->user()->role === 'student'
            ? route('student.dashboard')
            : route('admin.dashboard');

        $intended = $request->session()->pull('url.intended');

        if (is_string($intended)) {
            $parts = parse_url($intended);
            $host = $parts['host'] ?? null;
            $scheme = $parts['scheme'] ?? null;
            $path = $parts['path'] ?? '/';
            $query = isset($parts['query']) ? '?'.$parts['query'] : '';

            $isRelative = $host === null
                && $scheme === null
                && str_starts_with($intended, '/')
                && ! str_starts_with($intended, '//');

            $isSameHost = $host !== null
                && strcasecmp($host, $request->getHost()) === 0
                && ($scheme === null || strcasecmp($scheme, $request->getScheme()) === 0);

            if ($isRelative || $isSameHost) {
                return redirect($path.$query);
            }
        }

        return redirect($fallback);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
