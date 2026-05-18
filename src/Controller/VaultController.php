<?php

declare(strict_types=1);

namespace NullAuth\Controller;

use NullAuth\Http\Request;
use NullAuth\Http\Response;
use NullAuth\Service\CsrfService;
use NullAuth\Service\SessionService;
use NullAuth\Service\VaultService;
use NullAuth\View\View;

final readonly class VaultController
{
    public function __construct(
        private SessionService $session,
        private CsrfService $csrf,
        private VaultService $vault,
        private Request $request,
    ) {
    }

    public function index(): Response
    {
        $userId = $this->session->requireUser();
        if ($userId === null) {
            return Response::redirect('/');
        }

        $content = View::render('vault/index', [
            'entries' => $this->vault->listForUser($userId),
            'csrf' => $this->csrf,
        ]);

        return Response::html(View::layout('Vault', $content, [
            'active' => 'vault',
            'csrf' => $this->csrf,
        ]));
    }

    public function store(): Response
    {
        $userId = $this->session->requireUser();
        if ($userId === null) {
            return Response::redirect('/');
        }
        if (!$this->csrf->validate($this->request->input('_csrf'))) {
            return Response::html('Invalid request token.', 419);
        }

        $this->vault->create($userId, array_map('strval', $this->request->post));
        return Response::redirect('/vault');
    }

    public function reveal(): Response
    {
        $userId = $this->session->requireUser();
        if ($userId === null) {
            return Response::json(['ok' => false], 401);
        }
        if (!$this->csrf->validate($this->request->input('_csrf'))) {
            return Response::json(['ok' => false], 419);
        }

        $password = $this->vault->revealPassword($userId, (string) $this->request->input('entry_id', ''));
        if ($password === null) {
            return Response::json(['ok' => false], 404);
        }

        return Response::json(['ok' => true, 'password' => $password])
            ->withHeader('Cache-Control', 'no-store, private');
    }
}

