<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class FirstAdminSetupController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if ($this->adminExists()) {
            return redirect()->route('login');
        }

        return view('auth.setup-admin');
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->adminExists()) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ]);

        try {
            $user = Cache::lock('cnet:first-admin-setup', 15)->block(5, function () use ($validated) {
                if ($this->adminExists()) {
                    return null;
                }

                $user = User::create([
                    'name' => $validated['name'],
                    'email' => strtolower(trim($validated['email'])),
                    'password' => Hash::make($validated['password']),
                    'role' => 'super_admin',
                    'status' => true,
                    'branch_id' => null,
                ]);

                if ($role = Role::query()->where('slug', 'super-admin')->first()) {
                    $user->roles()->syncWithoutDetaching([$role->id]);
                }

                return $user;
            });
        } catch (LockTimeoutException) {
            return back()
                ->withErrors(['setup' => 'Administrator setup is already being processed. Please try again.'])
                ->onlyInput('name', 'email');
        }

        if (! $user) {
            return redirect()->route('login');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    private function adminExists(): bool
    {
        return User::query()->where('role', 'super_admin')->exists();
    }
}
