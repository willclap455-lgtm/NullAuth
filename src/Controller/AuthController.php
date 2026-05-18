<?php

declare(strict_types=1);

namespace NullAuth\Controller;

use NullAuth\Http\Request;
use NullAuth\Http\Response;
use NullAuth\Http\SecurityHeaders;
use NullAuth\Service\AuthService;
use NullAuth\Service\CsrfService;
use NullAuth\Service\SessionService;

final readonly class AuthController
{
    public function __construct(
        private AuthService $auth,
        private CsrfService $csrf,
        private SessionService $session,
        private SecurityHeaders $headers,
        private Request $request,
    ) {
    }

    public function unlock(): Response
    {
        $this->session->start();
        $token = $this->csrf->token();
        $nonce = $this->headers->nonce();
        $html = <<<HTML
<div class="na-unlock card shadow-lg border-0">
  <div class="card-body p-4">
    <h1 class="h4 mb-1">Secure access</h1>
    <p class="text-muted mb-4">Enter your NullAuth credentials. Responses are intentionally generic.</p>
    <form method="post" action="/login" autocomplete="off" novalidate>
      <input type="hidden" name="_csrf" value="{$token}">
      <div class="mb-3">
        <label class="form-label" for="identifier">Username or email</label>
        <input class="form-control" id="identifier" name="identifier" required maxlength="255" autocomplete="username">
      </div>
      <div class="mb-3">
        <label class="form-label" for="password">Password</label>
        <input class="form-control" id="password" name="password" required type="password" autocomplete="current-password">
      </div>
      <div class="mb-3">
        <label class="form-label" for="totp">MFA code</label>
        <input class="form-control" id="totp" name="totp" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code">
      </div>
      <button class="btn btn-primary w-100" type="submit">Continue</button>
    </form>
  </div>
</div>
<style nonce="{$nonce}">
  body{background:linear-gradient(135deg,#eaf3ff,#ffffff);min-height:100vh}
  .na-unlock{max-width:440px;margin:8vh auto}
</style>
HTML;

        return Response::html($html)->withHeader('Cache-Control', 'no-store');
    }

    public function login(): Response
    {
        $this->session->start();
        if (!$this->csrf->validate($this->request->input('_csrf'))) {
            return Response::html('The supplied credentials could not be verified.', 419);
        }

        $result = $this->auth->login(
            (string) $this->request->input('identifier', ''),
            (string) $this->request->input('password', ''),
            $this->request->input('totp'),
            $this->request->ip(),
            $this->request->userAgent(),
        );

        if (!$result['ok']) {
            return Response::html(e($result['message']), 401);
        }

        return Response::redirect('/dashboard');
    }

    public function logout(): Response
    {
        $this->session->destroy();
        return Response::redirect('/');
    }
}

