<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class StudentActivationController extends Controller
{
    public function show(string $token): View
    {
        $student = $this->resolveStudent($token);

        return view('auth.student-activate', compact('student', 'token'));
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $student = $this->resolveStudent($token);

        $data = $request->validate([
            'password' => [
                'required',
                'confirmed',
                Password::min(10)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $student->user->update(['password' => $data['password']]);
        $student->update([
            'portal_activation_token' => null,
            'portal_activation_expires_at' => null,
            'portal_activated_at' => now(),
        ]);

        Auth::login($student->user);
        $request->session()->regenerate();

        return redirect()->route('student.dashboard')
            ->with('success', 'Student portal activated successfully.');
    }

    private function resolveStudent(string $token): Student
    {
        $student = Student::query()
            ->with('user')
            ->where('portal_activation_token', hash('sha256', $token))
            ->firstOrFail();

        abort_if(! $student->user || $student->user->role !== 'student', 404);
        abort_if($student->status !== 'active' || ! $student->user->status, 410, 'This student portal account is inactive.');
        abort_if(! $student->portal_activation_expires_at || $student->portal_activation_expires_at->isPast(), 410, 'Activation link has expired.');
        abort_if($student->portal_activated_at, 410, 'Student portal is already activated.');

        return $student;
    }
}
