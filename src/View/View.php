<?php

declare(strict_types=1);

namespace NullAuth\View;

final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require base_path('templates/' . $template . '.php');
        return (string) ob_get_clean();
    }

    /** @param array<string, mixed> $data */
    public static function layout(string $title, string $content, array $data = []): string
    {
        return self::render('layout', array_merge($data, [
            'title' => $title,
            'content' => $content,
        ]));
    }
}

