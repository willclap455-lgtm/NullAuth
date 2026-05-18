<?php

declare(strict_types=1);

namespace NullAuth\Controller;

use NullAuth\Http\Response;
use NullAuth\Service\CsrfService;
use NullAuth\Service\SessionService;
use NullAuth\View\View;

final readonly class DashboardController
{
    public function __construct(private SessionService $session, private CsrfService $csrf)
    {
    }

    public function index(): Response
    {
        $userId = $this->session->requireUser();
        if ($userId === null) {
            return Response::redirect('/');
        }

        $content = View::render('dashboard', [
            'displayName' => $this->session->displayName(),
        ]);

        return Response::html(View::layout('Dashboard', $content, [
            'active' => 'dashboard',
            'csrf' => $this->csrf,
        ]));
    }
}

