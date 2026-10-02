<?php

namespace App\Http\Middleware;

use App\Models\Student;
use App\Services\LibraryStudentSessionService;
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
            $request->session()->forget('library_device_token');
            $request->session()->regenerateToken();

            return redirect()->route('student.login')
                ->withErrors(['email' => 'This student portal account is inactive.']);
        }

        $student = Student::where('user_id', $user->id)->firstOrFail();
        if (! app(LibraryStudentSessionService::class)->touch($student, $request->session()->get('library_device_token'))) {
            Auth::guard('library_student')->logout();
            $request->session()->forget('library_device_token');

            return redirect()->route('student.login')->with('error', 'आपका सत्र समाप्त या बंद हो गया है। कृपया फिर लॉगिन करें।');
        }

        return $next($request);
    }
}
