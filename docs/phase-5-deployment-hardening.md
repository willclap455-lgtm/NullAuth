# Phase 5 - Nginx hardening, deployment, backup/recovery, and production checklist

## Ubuntu packages

```bash
sudo apt update
sudo apt install -y nginx postgresql-16 php8.3-fpm php8.3-cli php8.3-pgsql php8.3-mbstring php8.3-xml php8.3-curl php8.3-sodium php8.3-opcache unzip acl fail2ban
```

Install Composer from the official signed installer if vendor autoloading is required by your deployment process.

## PostgreSQL

```sql
CREATE ROLE nullauth_app LOGIN PASSWORD 'replace-me';
CREATE DATABASE nullauth OWNER nullauth_app;
\c nullauth
CREATE EXTENSION IF NOT EXISTS pgcrypto;
```

Apply:

```bash
psql -v ON_ERROR_STOP=1 -d nullauth -f database/schema.sql
```

Use a dedicated database role. Do not use the PostgreSQL superuser from PHP.

## Filesystem permissions

```bash
sudo install -d -o www-data -g www-data -m 0750 /var/www/nullauth/var/{cache,log,sessions,uploads}
sudo chown -R root:www-data /var/www/nullauth
sudo chmod -R o-rwx /var/www/nullauth
sudo chmod 0640 /var/www/nullauth/.env
sudo chown root:www-data /var/www/nullauth/.env
```

Only `public/` should be web-accessible.

## Nginx

Use `deploy/nginx/nullauth.conf`, adjust domain names and certificate paths, then:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

Recommended controls:

- Redirect HTTP to HTTPS.
- TLS 1.2/1.3 only; prefer TLS 1.3.
- HSTS after validating HTTPS.
- `client_max_body_size` sized for expected encrypted attachments.
- Rate limiting on all dynamic endpoints and stricter limits on `/unlock` and authentication.
- Deny hidden files and sensitive extensions.
- Do not log request bodies.
- Keep access logs but avoid query-string secrets by ensuring the app never places secrets in URLs.

## PHP-FPM

Set a dedicated pool for NullAuth:

```ini
user = www-data
group = www-data
listen = /run/php/nullauth.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 20
php_admin_value[open_basedir] = /var/www/nullauth:/tmp
php_admin_value[session.cookie_secure] = 1
php_admin_value[session.cookie_httponly] = 1
php_admin_value[session.use_strict_mode] = 1
php_admin_value[expose_php] = 0
```

## Backups

Back up three things:

1. PostgreSQL database.
2. `.env` and any external key material.
3. Encrypted attachment storage.

Encrypt backups before leaving the host:

```bash
pg_dump --format=custom nullauth | age -r RECIPIENT_PUBLIC_KEY > nullauth-$(date +%F).dump.age
```

Test restoration regularly on an isolated host. A database backup without `APP_KEY_BASE64` cannot decrypt vault contents; key backups must be protected but recoverable.

## Disaster recovery

- Keep offline copies of `APP_KEY_BASE64`, `APP_PEPPER`, PostgreSQL credentials, and recovery runbooks.
- Verify that at least two trusted operators can restore from backup.
- Use `bin/nullauth user:unlock` and `bin/nullauth user:reset-mfa` only from a trusted shell.
- If the application key is suspected compromised, rotate by decrypting and re-encrypting all DEKs with a new KEK during a maintenance window.

## Production checklist

- [ ] HTTPS certificate installed and auto-renewal tested.
- [ ] HSTS enabled after validation.
- [ ] `.env` permissions are `0640` or stricter and outside backups with broad access.
- [ ] PostgreSQL role is least privilege.
- [ ] `APP_KEY_BASE64` is 32 random bytes encoded as base64.
- [ ] `APP_PEPPER` is high entropy and outside the database.
- [ ] `sodium` extension enabled.
- [ ] Nginx serves only `public/`.
- [ ] PHP `display_errors=Off`, `log_errors=On`, `expose_php=Off`.
- [ ] Backups encrypted and restore-tested.
- [ ] Fail2ban rules monitor authentication failures.
- [ ] Initial administrator created through a controlled local process.
- [ ] Security headers verified with browser developer tools and curl.

