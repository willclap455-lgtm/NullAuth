# NullAuth

NullAuth is a self-hosted, security-first password vault for a single organization. It targets Ubuntu, Nginx, PHP 8.3+, PostgreSQL 16+, Bootstrap 5, and no hosted identity or cloud runtime dependencies.

This repository is organized as a production application scaffold plus phased implementation documentation:

- `docs/phase-1-architecture.md` - architecture, threat model, database strategy, and security model.
- `docs/phase-2-auth-encryption.md` - authentication, Argon2id, MFA, sessions, lockout, and encryption hierarchy.
- `docs/phase-3-vault-generator-ui.md` - vault workflows, generator, and UI/security behavior.
- `docs/phase-4-admin-audit-sharing.md` - RBAC, sharing, auditability, retention, and admin recovery.
- `docs/phase-5-deployment-hardening.md` - Nginx, Ubuntu deployment, backups, recovery, and production checklist.

## Security posture

NullAuth assumes hostile internet exposure and database compromise scenarios. Vault secrets are encrypted with libsodium XChaCha20-Poly1305 using unique nonces, associated data, envelope metadata, and keys derived from a server-held root key plus per-record data encryption keys. Password authentication uses Argon2id with per-user salts handled by PHP's password API and an optional out-of-database pepper.

The stealth landing page intentionally returns HTTP 500 and creates no login DOM until the configured keyboard sequence is entered. This is a concealment layer only; authorization, throttling, MFA, CSRF, CSP, session controls, audit logging, and encryption enforce actual security.

## Quick local bootstrap

```bash
cp .env.example .env
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
php -S 127.0.0.1:8080 -t public
```

## Development/test environment

Ubuntu development and Cursor Cloud environments need PHP CLI, Composer, and the PostgreSQL client before running checks:

```bash
bash deploy/cloud-agent/setup-nullauth-tools.sh
composer install --no-interaction
composer check
psql --version
```

The setup script installs `php-cli`, `php-pgsql`, `php-mbstring`, `php-xml`, `php-curl`, `composer`, and `postgresql-client`. The sodium extension is provided by Ubuntu's PHP 8.3 package set and is verified by the script.

To validate the schema against a local PostgreSQL 16 server in a disposable development environment:

```bash
bash deploy/cloud-agent/setup-nullauth-tools.sh --with-postgres-server
sudo -u postgres psql -v ON_ERROR_STOP=1 -c "CREATE ROLE nullauth_app_test LOGIN PASSWORD 'test-password';"
sudo -u postgres psql -v ON_ERROR_STOP=1 -c "CREATE DATABASE nullauth_test OWNER nullauth_app_test;"
PGPASSWORD='test-password' psql -h 127.0.0.1 -U nullauth_app_test -d nullauth_test -v ON_ERROR_STOP=1 -f database/schema.sql
```

Apply `database/schema.sql` to PostgreSQL before using authenticated workflows:

```bash
psql -v ON_ERROR_STOP=1 -d nullauth -f database/schema.sql
```

## Production deployment

Use `deploy/nginx/nullauth.conf` as a starting Nginx server block and follow `docs/phase-5-deployment-hardening.md`. Production must run behind HTTPS with secure cookies, HSTS, hardened PHP-FPM, restrictive file permissions, PostgreSQL least-privilege roles, encrypted backups, and tested recovery.

## CLI recovery

`bin/nullauth` provides emergency administrator recovery operations intended for local shell use only by a server operator with filesystem and database access.

