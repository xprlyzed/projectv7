<?php

use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\EnsureUserIsVerified;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackUserActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // Güvenilen proxy'ler yük dengeleyici/reverse proxy CIDR aralığından okunur.
        // '*' yalnızca proxy'nin kendisi X-Forwarded-* başlıklarını GÜVENLİ ayarladığı ortamlarda
        // kullanılmalı; production'da TRUSTED_PROXIES'e gerçek proxy IP/CIDR listesi verin
        // (virgülle ayrılmış), aksi halde X-Forwarded-For sahtelenip IP throttle atlatılabilir.
        $trustedProxies = (string) env('TRUSTED_PROXIES', '*');
        $middleware->trustProxies(
            at: $trustedProxies === '*' ? '*' : array_filter(array_map('trim', explode(',', $trustedProxies)))
        );

        $middleware->append(CheckMaintenanceMode::class);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            TrackUserActivity::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'verified.account' => EnsureUserIsVerified::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'maintenance' => CheckMaintenanceMode::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Tüm HTTP hata durumları için tasarıma uygun Inertia (Vue) hata sayfası.
        // API/JSON istekleri etkilenmez; onlar standart JSON yanıtını alır.
        $exceptions->respond(function (
            \Symfony\Component\HttpFoundation\Response $response,
            \Throwable $e,
            \Illuminate\Http\Request $request
        ) {
            $status = $response->getStatusCode();

            if ($request->expectsJson() || $request->is('api/*') || $request->is('broadcasting/*')) {
                return $response;
            }

            if (in_array($status, [403, 404, 419, 429, 500, 503], true)) {
                $activeAuctions = 0;
                try {
                    $activeAuctions = \App\Models\Auction::where('status', 'active')->count();
                } catch (\Throwable $ignored) {
                    // DB erişilemezse (örn. 500) sayaç 0 kalır
                }

                return \Inertia\Inertia::render('Errors/Error', [
                    'status' => $status,
                    'activeAuctions' => $activeAuctions,
                ])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
