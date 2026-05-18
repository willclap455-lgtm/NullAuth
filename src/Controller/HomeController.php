<?php

declare(strict_types=1);

namespace NullAuth\Controller;

use NullAuth\Http\Response;
use NullAuth\Http\SecurityHeaders;

final readonly class HomeController
{
    public function __construct(private SecurityHeaders $headers)
    {
    }

    public function errorLanding(): Response
    {
        $sequence = e(json_encode(config('app.hotkey_sequence'), JSON_THROW_ON_ERROR));
        $nonce = $this->headers->nonce();
        $body = <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>500 Internal Server Error</title>
  <style nonce="{$nonce}">
    body{font-family:Arial,sans-serif;background:#fff;color:#222;margin:44px}
    h1{font-size:28px;font-weight:400}
    hr{border:0;border-top:1px solid #ddd}
    .nginx{color:#555}
  </style>
</head>
<body>
  <h1>500 Internal Server Error</h1>
  <hr>
  <p class="nginx">nginx</p>
  <script nonce="{$nonce}" src="/assets/js/entry.js" data-k="{$sequence}"></script>
</body>
</html>
HTML;

        return Response::html($body, 500);
    }
}

