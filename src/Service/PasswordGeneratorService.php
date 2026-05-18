<?php

declare(strict_types=1);

namespace NullAuth\Service;

final class PasswordGeneratorService
{
    private const AMBIGUOUS = 'Il1O0o{}[]()/\'' . '"`~,;:.<>';

    /** @var list<string> */
    private array $words = [
        'anchor', 'atlas', 'binary', 'bravo', 'canyon', 'cipher', 'delta', 'ember',
        'forest', 'galaxy', 'harbor', 'isotope', 'jupiter', 'kernel', 'lantern', 'matrix',
        'nebula', 'orbit', 'prairie', 'quantum', 'raven', 'signal', 'summit', 'tundra',
        'vector', 'willow', 'zenith', 'aurora', 'bunker', 'cobalt', 'domain', 'engine',
    ];

    /** @param array<string, string> $options @return array{password: string, entropy: float, mode: string} */
    public function generate(array $options): array
    {
        $mode = $options['mode'] ?? 'characters';
        return match ($mode) {
            'passphrase' => $this->passphrase($options),
            'pronounceable' => $this->pronounceable($options),
            default => $this->characters($options),
        };
    }

    /** @param array<string, string> $options @return array{password: string, entropy: float, mode: string} */
    private function characters(array $options): array
    {
        $length = max(12, min((int) ($options['length'] ?? 24), 128));
        $sets = [];
        if (($options['uppercase'] ?? '1') === '1') {
            $sets[] = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }
        if (($options['lowercase'] ?? '1') === '1') {
            $sets[] = 'abcdefghijklmnopqrstuvwxyz';
        }
        if (($options['numbers'] ?? '1') === '1') {
            $sets[] = '0123456789';
        }
        if (($options['symbols'] ?? '1') === '1') {
            $sets[] = '!@#$%^&*_-+=?';
        }
        if ($sets === []) {
            $sets[] = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        }

        $pool = implode('', $sets);
        if (($options['exclude_ambiguous'] ?? '') === '1') {
            $pool = str_replace(str_split(self::AMBIGUOUS), '', $pool);
        }
        $pool = implode('', array_values(array_unique(str_split($pool))));

        $password = '';
        foreach ($sets as $set) {
            $password .= $this->pick($set);
        }
        while (strlen($password) < $length) {
            $password .= $this->pick($pool);
        }

        $password = $this->shuffleSecure($password);
        return [
            'password' => substr($password, 0, $length),
            'entropy' => round($length * log(max(2, strlen($pool)), 2), 2),
            'mode' => 'characters',
        ];
    }

    /** @param array<string, string> $options @return array{password: string, entropy: float, mode: string} */
    private function passphrase(array $options): array
    {
        $count = max(4, min((int) ($options['words'] ?? 6), 12));
        $separator = mb_substr($options['separator'] ?? '-', 0, 3);
        $parts = [];
        for ($i = 0; $i < $count; $i++) {
            $parts[] = $this->words[random_int(0, count($this->words) - 1)];
        }

        return [
            'password' => implode($separator, $parts),
            'entropy' => round($count * log(count($this->words), 2), 2),
            'mode' => 'passphrase',
        ];
    }

    /** @param array<string, string> $options @return array{password: string, entropy: float, mode: string} */
    private function pronounceable(array $options): array
    {
        $length = max(12, min((int) ($options['length'] ?? 20), 64));
        $consonants = 'bcdfghjkmnprstvwyz';
        $vowels = 'aeiou';
        $password = '';
        while (strlen($password) < $length) {
            $password .= $this->pick($consonants) . $this->pick($vowels);
        }

        $password = substr($password, 0, $length);
        $password .= (string) random_int(10, 99) . $this->pick('!@#$%');

        return [
            'password' => $password,
            'entropy' => round(($length / 2) * log(strlen($consonants) * strlen($vowels), 2) + log(90 * 5, 2), 2),
            'mode' => 'pronounceable',
        ];
    }

    private function pick(string $pool): string
    {
        return $pool[random_int(0, strlen($pool) - 1)];
    }

    private function shuffleSecure(string $value): string
    {
        $chars = str_split($value);
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }
}

