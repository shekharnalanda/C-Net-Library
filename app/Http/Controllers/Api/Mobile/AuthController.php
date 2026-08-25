<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MobileApiToken;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $email = Str::lower(trim($validated['email']));
        $key = 'mobile-login:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => 'Too many login attempts.',
                'retry_after' => RateLimiter::availableIn($key),
            ], 429);
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user || ! $user->status || $user->role !== 'student' || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            return response()->json(['message' => 'Invalid login credentials or inactive account.'], 422);
        }

        $student = Student::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $student) {
            RateLimiter::hit($key, 60);
            return response()->json(['message' => 'Invalid login credentials or inactive account.'], 422);
        }

        RateLimiter::clear($key);

        $plainToken = Str::random(80);
        MobileApiToken::query()->create([
            'user_id' => $user->id,
            'name' => $validated['device_name'] ?? 'mobile-app',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(90),
        ]);

        return response()->json([
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'expires_in_days' => 90,
            'student' => [
                'id' => $student->id,
                'student_code' => $student->student_code,
                'name' => $student->name,
                'email' => $student->email,
                'mobile' => $student->mobile,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('mobile_api_token')?->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
