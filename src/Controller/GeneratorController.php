<?php

declare(strict_types=1);

namespace NullAuth\Controller;

use NullAuth\Http\Request;
use NullAuth\Http\Response;
use NullAuth\Service\CsrfService;
use NullAuth\Service\PasswordGeneratorService;
use NullAuth\Service\SessionService;
use NullAuth\View\View;

final readonly class GeneratorController
{
    public function __construct(
        private SessionService $session,
        private CsrfService $csrf,
        private PasswordGeneratorService $generator,
        private Request $request,
    ) {
    }

    public function index(): Response
    {
        $userId = $this->session->requireUser();
        if ($userId === null) {
            return Response::redirect('/');
        }

        $content = View::render('generator/index', [
            'csrf' => $this->csrf,
            'result' => null,
        ]);

        return Response::html(View::layout('Generator', $content, [
            'active' => 'generator',
            'csrf' => $this->csrf,
        ]));
    }

    public function generate(): Response
    {
        $userId = $this->session->requireUser();
        if ($userId === null) {
            return Response::redirect('/');
        }
        if (!$this->csrf->validate($this->request->input('_csrf'))) {
            return Response::html('Invalid request token.', 419);
        }

        $result = $this->generator->generate(array_map('strval', $this->request->post));
        $content = View::render('generator/index', [
            'csrf' => $this->csrf,
            'result' => $result,
        ]);

        return Response::html(View::layout('Generator', $content, [
            'active' => 'generator',
            'csrf' => $this->csrf,
        ]))->withHeader('Cache-Control', 'no-store, private');
    }
}

