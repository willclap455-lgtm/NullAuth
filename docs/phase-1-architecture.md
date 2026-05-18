# Phase 1 - Architecture, threat model, schema, directory structure, and security strategy

## Goals

NullAuth is a private, self-hosted password manager for professional administrators. It must remain safe when exposed to the internet, when attackers attempt credential stuffing, and when a database snapshot is stolen. It does not rely on stealth for authentication security.

## High-level architecture

```text
browser
  |
  | HTTPS, HSTS, CSP, secure cookies
  v
Nginx
  |
  | fastcgi_param HTTPS on, restricted locations
  v
PHP-FPM 8.3
  |
  | MVC controller -> services -> repositories
  v
PostgreSQL 16
```

Application layers:

- `public/` is the only web root. It contains the front controller and static assets.
- `src/Http` owns request, response, routing, and middleware concerns.
- `src/Controller` implements web endpoints.
- `src/Service` owns security-sensitive workflows such as authentication, CSRF, encryption, MFA, auditing, sessions, throttling, and password generation.
- `src/Repository` is the only layer that talks to PostgreSQL.
- `config/` contains non-secret defaults. Secrets are supplied through `.env` or the process environment.
- `database/schema.sql` is the canonical PostgreSQL schema.
- `deploy/` contains hardened production examples.

## Threat model

### In scope

- Brute force and credential stuffing.
- Username enumeration.
- Session fixation, hijacking, replay, and stolen cookies.
- CSRF, XSS, clickjacking, mixed content, and browser cache leakage.
- SQL injection and insecure direct object references.
- Malicious or careless insiders.
- Database theft.
- Clipboard exposure where browser APIs permit mitigation.
- Sensitive data in logs, hidden fields, URLs, or sessions.
- Weak random generation, predictable nonces, or cryptographic misuse.
- PHP-FPM/Nginx misconfiguration and unsafe filesystem permissions.

### Out of scope / residual risk

- Fully compromised PHP runtime or root-level host compromise can read process memory and server-held keys.
- Browser compromise can read secrets after a legitimate reveal.
- Clipboard clearing is best-effort only; operating systems and browsers do not provide a reliable cross-platform guarantee.

## Security strategy

NullAuth uses layered controls:

1. **Network edge:** HTTPS-only, HSTS, rate limits, request size limits, no direct access to application files.
2. **HTTP layer:** strict security headers, CSP nonces, `frame-ancestors 'none'`, anti-cache headers on authenticated pages.
3. **Authentication:** Argon2id, optional pepper outside the database, MFA, recovery codes, throttling by account and network source, anti-enumeration responses.
4. **Sessions:** regeneration on privilege transition, HttpOnly secure cookies, SameSite=Lax by default, idle and absolute timeouts, database-backed session metadata.
5. **Authorization:** RBAC plus object-scoped folder and entry sharing checks in repositories/services.
6. **Encryption:** libsodium XChaCha20-Poly1305 with unique nonces, associated data, envelope metadata, and key separation.
7. **Data access:** prepared PDO statements, UUID primary keys, foreign keys, constraints, soft delete where useful.
8. **Auditability:** security events go to structured audit tables without secrets.
9. **Operations:** least-privilege filesystem/database roles, backup encryption, fail2ban-ready logs, and emergency CLI recovery.

## Stealth landing page

`GET /` returns an actual HTTP 500 with a sparse generic error page. The login form is not present in the initial DOM. A configured keyboard sequence fetches `/unlock` and injects the login panel at runtime. The sequence is a concealment measure against casual discovery, not an authorization boundary.

## Database design

The schema uses PostgreSQL UUIDs through `gen_random_uuid()`, strict foreign keys, check constraints, JSONB where extensibility is needed, and partial indexes for soft-deleted records. Core tables:

- `users`, `roles`, `permissions`, `role_permissions`, `user_roles`.
- `mfa_totp`, `mfa_recovery_codes`.
- `folders`, `categories`, `vault_entries`, `vault_entry_password_history`, `vault_entry_attachments`.
- `vault_entry_shares`, `folder_shares`.
- `audit_logs`, `login_attempts`, `sessions`, `api_tokens`.
- `password_generator_profiles`, `system_settings`.

Encrypted fields are stored as JSONB envelopes with algorithm, nonce, ciphertext, key id, and authenticated-data context rather than as plaintext columns.

