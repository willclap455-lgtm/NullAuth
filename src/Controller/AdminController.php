<?php

declare(strict_types=1);

namespace NullAuth\Controller;

use NullAuth\Http\Response;
use NullAuth\Repository\UserRepository;
use NullAuth\Service\AuditService;
use NullAuth\Service\CsrfService;
use NullAuth\Service\SessionService;
use NullAuth\View\View;

final readonly class AdminController
{
    public function __construct(
        private SessionService $session,
        private CsrfService $csrf,
        private AuditService $audit,
        private UserRepository $users,
    ) {
    }

    public function users(): Response
    {
        $userId = $this->session->requireUser();
        if ($userId === null) {
            return Response::redirect('/');
        }
        if (!$this->users->userHasPermission($userId, 'users.manage')) {
            return Response::html('Forbidden', 403);
        }

        $content = View::render('admin/users', ['users' => $this->users->allActive()]);
        return Response::html(View::layout('Users', $content, [
            'active' => 'admin-users',
            'csrf' => $this->csrf,
        ]));
    }

    public function audit(): Response
    {
        $userId = $this->session->requireUser();
        if ($userId === null) {
            return Response::redirect('/');
        }
        if (!$this->users->userHasPermission($userId, 'audit.read')) {
            return Response::html('Forbidden', 403);
        }

        $content = View::render('admin/audit', ['events' => $this->audit->recent(100)]);
        return Response::html(View::layout('Audit', $content, [
            'active' => 'admin-audit',
            'csrf' => $this->csrf,
        ]));
    }
}

