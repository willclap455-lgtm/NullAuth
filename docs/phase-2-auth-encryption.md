# Phase 2 - Authentication, encryption, sessions, and MFA

## Password authentication

Passwords are hashed with PHP's `password_hash()` using `PASSWORD_ARGON2ID`.

Recommended production baseline:

```php
[
    'memory_cost' => 131072, // 128 MiB
    'time_cost' => 4,
    'threads' => 2,
]
```

PHP embeds a unique cryptographic salt in every Argon2id hash. NullAuth optionally appends an application pepper before hashing. The pepper must live outside PostgreSQL in the environment or a root-readable config file. If the database is stolen, the attacker has hashes and salts but not the pepper.

Tradeoff: a pepper means password verification requires the application secret; rotating it requires forced password resets unless the old pepper is temporarily retained for migration.

## Throttling and lockout

Login attempts are recorded with timestamp, username attempted, IP, user agent, result, and reason. Throttling evaluates both normalized username and IP/network source:

- A small number of failures returns generic messaging with no enumeration signal.
- Repeated failures introduce exponential backoff.
- High-risk bursts create temporary lockouts.
- Administrators can recover accounts through the local CLI after host authentication.

Responses intentionally do not reveal whether the account exists, is disabled, requires MFA, or is locked.

## MFA

NullAuth implements RFC 6238-compatible TOTP using HMAC-SHA1 with 30-second time steps and 6 digits for authenticator app compatibility. Enrollment uses a random 160-bit secret and an `otpauth://` URI. Recovery codes are one-time high-entropy values; only Argon2id hashes of recovery codes are stored.

Clock skew is accepted within a narrow window. Used TOTP time steps can be recorded to prevent replay within the same window.

## Session management

Session requirements:

- `session.use_strict_mode=1`
- Secure, HttpOnly cookie.
- SameSite=Lax for form-based workflows. Strict can break some administrator launch flows; use Strict only if deployment testing confirms it.
- Regenerate ID after authentication and privilege changes.
- Idle timeout and absolute timeout.
- Session metadata stored in PostgreSQL with user, IP hash, user-agent hash, creation, last seen, expiry, and revocation status.
- No vault secrets, MFA secrets, plaintext passwords, or recovery codes are stored in session data.

## Encryption model

NullAuth is **server-assisted encryption**, not strict zero knowledge. The server must decrypt vault items for browser display after authorization. This allows RBAC, sharing, audit, search over non-secret metadata, and administrative recovery controls. It also means a compromised application host can decrypt active secrets.

### Key hierarchy

```text
APP_KEY_BASE64 (32 random bytes, outside database)
  |
  +-- HKDF("nullauth:vault:v1") -> key-encryption key (KEK)
        |
        +-- wraps per-entry data encryption keys (DEKs)
              |
              +-- XChaCha20-Poly1305 encrypts password, notes, custom fields
```

Each vault entry has its own random DEK. Every encrypted field gets a unique 192-bit XChaCha20 nonce. Associated data includes table name, field name, entry UUID, tenant/application context, and envelope version. This prevents ciphertext relocation across records or fields without detection.

### Why XChaCha20-Poly1305

XChaCha20-Poly1305 provides authenticated encryption, a large nonce space that is safe with random nonces, and strong integrity checks. NullAuth requires the PHP sodium extension in production instead of silently falling back to weaker custom cryptography.

### Secret handling rules

Secrets are never written to logs, URLs, hidden form fields, browser localStorage, or persistent client-side storage. Revealed passwords are rendered only after an explicit user action and should be auto-hidden by the UI.

