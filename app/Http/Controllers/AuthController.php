<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function show() { return Auth::check() ? redirect()->route('dashboard') : view('auth.login'); }
    public function login(Request $request)
    {
        $credentials = $request->validate(['university_id' => ['required','string','max:50'], 'password' => ['required','string','max:128']]);
        $key = Str::lower($credentials['university_id']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) return back()->withErrors(['university_id' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        if (!Auth::attempt(['university_id' => $credentials['university_id'], 'password' => $credentials['password'], 'active' => true])) {
            RateLimiter::hit($key, 900);
            return back()->withInput($request->only('university_id'))->withErrors(['university_id' => 'University ID Number or password is incorrect.']);
        }
        RateLimiter::clear($key); $request->session()->regenerate(); $this->audit('Signed in', $credentials['university_id']);
        return redirect()->intended(route('dashboard'));
    }
    public function logout(Request $request)
    {
        $this->audit('Signed out', $request->user()->university_id); Auth::logout();
        $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route('login');
    }
    private function audit(string $action, string $target): void
    {
        DB::table('audit_logs')->insert(['actor_id' => Auth::id(), 'action' => $action, 'target' => $target, 'created_at' => now(), 'updated_at' => now()]);
    }
}
