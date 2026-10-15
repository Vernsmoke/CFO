<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function show()
    {
        return Auth::check() ? redirect()->route('dashboard') : view('auth.login');
    }

    public function login(Request $request)
    {
        $identifier = trim((string) ($request->input('login') ?: $request->input('university_id')));
        $request->merge(['login' => $identifier]);
        $credentials = $request->validate(['login' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'max:128']]);
        $key = 'login|'.Str::lower($identifier).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withInput($request->only('login'))->withErrors(['login' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
        $user = $isEmail
            ? User::where('role', 'student')->whereRaw('LOWER(email) = ?', [Str::lower($identifier)])->first()
            : User::where('university_id', $identifier)->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($key, 900);

            return back()->withInput($request->only('login'))->withErrors(['login' => 'The sign-in details are incorrect.']);
        }

        if ($user->role === 'student' && (! $user->email_verified_at || $user->student_account_status === 'Pending Verification')) {
            RateLimiter::hit($key, 60);

            return back()->withInput($request->only('login'))
                ->with('pending_verification_email', $user->email)
                ->withErrors(['login' => 'Email verification is still required before this student account can sign in.']);
        }
        if (! $user->active) {
            RateLimiter::hit($key, 900);

            return back()->withInput($request->only('login'))->withErrors(['login' => 'The sign-in details are incorrect.']);
        }

        Auth::login($user);
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $this->audit('Signed in', $isEmail ? Str::lower($identifier) : $identifier);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        $this->audit('Signed out', $request->user()->university_id);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function audit(string $action, string $target): void
    {
        DB::table('audit_logs')->insert(['actor_id' => Auth::id(), 'action' => $action, 'target' => $target, 'created_at' => now(), 'updated_at' => now()]);
    }
}
