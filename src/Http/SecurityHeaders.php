<?php

declare(strict_types=1);

namespace NullAuth\Http;

final class SecurityHeaders
{
    private string $nonce;

    public function __construct()
    {
        $this->nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    }

    public function nonce(): string
    {
        return $this->nonce;
    }

    public function apply(Response $response, Request $request): void
    {
        $csp = implode('; ', [
            "default-src 'self'",
            "base-uri 'none'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "img-src 'self' data:",
            "style-src 'self' 'nonce-{$this->nonce}'",
            "script-src 'self' 'nonce-{$this->nonce}'",
            "connect-src 'self'",
            'upgrade-insecure-requests',
        ]);

        header('Content-Security-Policy: ' . $csp);
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');

        if ($request->path !== '/' && $request->path !== '/unlock') {
            header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }
}

