<?php

namespace App\Http\Middleware;

use App\Models\MobileApiToken;
use App\Models\Student;
use App\Services\LibraryStudentSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobileApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();

        if (blank($plainToken)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $token = MobileApiToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if (! $token || ! $token->user || ! $token->user->status || ($token->expires_at && $token->expires_at->isPast())) {
            optional($token)->delete();

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($token->user->role === 'student') {
            $student = Student::where('user_id', $token->user_id)->where('status', 'active')->first();
            if (! $student || ! app(LibraryStudentSessionService::class)->touch($student, $plainToken)) {
                return response()->json(['message' => 'Login expired or active on another system.'], 401);
            }
        }
        $token->forceFill(['last_used_at' => now()])->save();
        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('mobile_api_token', $token);

        return $next($request);
    }
}
