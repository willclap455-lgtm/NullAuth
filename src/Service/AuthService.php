<?php

declare(strict_types=1);

namespace NullAuth\Service;

use NullAuth\Repository\UserRepository;

final readonly class AuthService
{
    public function __construct(
        private UserRepository $users,
        private SessionService $sessions,
        private AuditService $audit,
        private TotpService $totp,
        private CryptoService $crypto,
    ) {
    }

    /** @return array{ok: bool, message: string} */
    public function login(string $identifier, string $password, ?string $totpCode, string $ip, string $userAgent): array
    {
        $normalized = mb_strtolower(trim($identifier));
        $user = $this->users->findForLogin($normalized);
        $genericFailure = ['ok' => false, 'message' => 'The supplied credentials could not be verified.'];

        if ($this->isThrottled($normalized, $ip)) {
            $this->audit->record('auth.login.throttled', 'failure', null, $normalized);
            $this->recordAttempt($normalized, $ip, $userAgent, false, 'throttled');
            usleep(random_int(250000, 700000));
            return $genericFailure;
        }

        $hash = is_array($user) ? (string) $user['password_hash'] : password_hash(random_bytes(32), PASSWORD_ARGON2ID);
        $candidate = $this->peppered($password);
        $validPassword = password_verify($candidate, $hash);

        if (!is_array($user) || !$validPassword || (bool) $user['is_disabled'] || $this->isLocked($user)) {
            $this->recordAttempt($normalized, $ip, $userAgent, false, 'invalid');
            $this->audit->record('auth.login.failure', 'failure', is_array($user) ? (string) $user['id'] : null, $normalized);
            usleep(random_int(250000, 700000));
            return $genericFailure;
        }

        if ($this->mfaRequired((string) $user['id']) && !$this->verifyMfa((string) $user['id'], (string) $totpCode)) {
            $this->recordAttempt($normalized, $ip, $userAgent, false, 'mfa');
            $this->audit->record('auth.mfa.failure', 'failure', (string) $user['id'], $normalized);
            return $genericFailure;
        }

        if (password_needs_rehash($hash, PASSWORD_ARGON2ID, config('argon2id'))) {
            // Password rehashing is deliberately not done here because it requires a repository method
            // that handles concurrent updates. Production deployments should add that migration path.
        }

        $this->sessions->authenticate($user, $ip, $userAgent);
        $this->users->updateLastLogin((string) $user['id']);
        $this->recordAttempt($normalized, $ip, $userAgent, true, null);
        $this->audit->record('auth.login.success', 'success', (string) $user['id'], $normalized);

        return ['ok' => true, 'message' => 'Authenticated.'];
    }

    public function hashPassword(string $password): string
    {
        return password_hash($this->peppered($password), PASSWORD_ARGON2ID, config('argon2id'));
    }

    private function peppered(string $password): string
    {
        $pepper = (string) config('app.pepper', '');
        return $pepper === '' ? $password : hash_hmac('sha384', $password, $pepper, true);
    }

    private function isThrottled(string $normalized, string $ip): bool
    {
        $failures = $this->users->countRecentFailures($normalized, $ip);
        if ($failures < 6) {
            return false;
        }

        $backoffSeconds = min(900, 2 ** min($failures - 5, 10));
        $attemptedAt = $this->users->latestFailureTimestamp($normalized, $ip);

        return $attemptedAt !== null && time() - $attemptedAt < $backoffSeconds;
    }

    private function recordAttempt(string $normalized, string $ip, string $userAgent, bool $success, ?string $reason): void
    {
        $this->users->recordLoginAttempt($normalized, $ip, $userAgent, $success, $reason);
    }

    /** @param array<string, mixed> $user */
    private function isLocked(array $user): bool
    {
        return !empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time();
    }

    private function mfaRequired(string $userId): bool
    {
        return $this->users->enabledTotp($userId) !== null;
    }

    private function verifyMfa(string $userId, string $code): bool
    {
        $totp = $this->users->enabledTotp($userId);
        if ($totp === null || $code === '') {
            return false;
        }

        $envelope = is_string($totp['secret_envelope'])
            ? $totp['secret_envelope']
            : json_encode($totp['secret_envelope'], JSON_THROW_ON_ERROR);
        $secret = $this->crypto->decryptString($envelope, 'mfa_totp:' . $userId);
        return $this->totp->verify($secret, $code);
    }
}

