<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsVerified
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->hasRole('admin')) {
            return $next($request);
        }

        // E-posta doğrulaması zorunlu (yalnızca yeni işlemleri kısıtlar; mevcut doğrulanmış
        // kullanıcılar UserFactory/seed sayesinde email_verified_at doludur). is_verified true
        // olan eski kayıtlar da doğrulanmış sayılır → geçmiş veri bozulmaz.
        if (! $user->hasVerifiedEmail() && ! $user->is_verified) {
            return redirect()->route('verification.notice');
        }

        if ($user->hasRole('buyer')) {
            return $next($request);
        }

        if ($user->hasRole('seller')) {
            $profile = $user->sellerProfile;

            if (! $profile) {
                Auth::logout();
                return redirect()->route('login')
                    ->withErrors(['email' => 'Hesabınızda bir sorun oluştu, lütfen tekrar kayıt olun.']);
            }

            if ($profile->isPending()) {
                return \Inertia\Inertia::render('Auth/PendingApproval', [
                    'activeAuctions' => \App\Models\Auction::where('status', 'active')->count(),
                ]);
            }

            if ($profile->isRejected()) {
                return \Inertia\Inertia::render('Auth/Rejected', [
                    'reason' => $profile->rejection_reason,
                    'activeAuctions' => \App\Models\Auction::where('status', 'active')->count(),
                ]);
            }
        }

        return $next($request);
    }
}