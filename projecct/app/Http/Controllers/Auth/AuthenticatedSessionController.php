<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): \Inertia\Response
    {
        return \Inertia\Inertia::render('Auth/Login', [
            'activeAuctions' => \App\Models\Auction::where('status', 'active')->count(),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $this->ensureIsNotRateLimited($request);

        $login = trim($request->email);

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {

            $field = 'email';

        } elseif (preg_match('/^[0-9+\s\-\(\)]+$/', $login)) {

            $field = 'phone';

        } else {

            $field = 'username';
        }

        $credentials = [
            $field => $login,
            'password' => $request->password,
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {

            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'email' => 'Bilgiler hatalı.',
            ]);
        }

        // Askıya alınmış hesap giriş yapamaz (verisi/ilişkileri korunur).
        if (Auth::user()->suspended_at !== null) {
            $reason = Auth::user()->suspension_reason;
            Auth::guard('web')->logout();
            throw ValidationException::withMessages([
                'email' => 'Hesabınız askıya alınmıştır.'.($reason ? ' Sebep: '.$reason : ''),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }


    protected function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'email' => "Çok fazla deneme yaptın. {$seconds} saniye sonra tekrar dene.",
        ]);
    }


    protected function throttleKey(Request $request): string
    {
        // Anahtar HESAP TANIMLAYICISI (email/kullanıcı adı) + IP üzerine kurulur.
        // Önceki hata: request'te olmayan 'login' alanı okunuyordu → anahtar hep '|ip' oluyor,
        // farklı hesaplara yapılan denemeler tek kovada birleşiyordu.
        return Str::transliterate(
            Str::lower((string) $request->input('email')) . '|' . $request->ip()
        );
    }
}
