# Phase 4 - Admin system, auditing, sharing, and advanced features

## RBAC

NullAuth separates global roles from object permissions:

- Administrators manage users, MFA resets, audit logs, settings, and emergency controls.
- Operators can create and manage their own vault records.
- Auditors can review logs without viewing secrets.
- Folder and entry shares grant object-level `read`, `write`, `share`, or `owner` capabilities.

Every repository method that returns vault objects must apply ownership/share predicates. Controllers do not trust client-provided IDs.

## Administration

Administrative workflows:

- Create, disable, and unlock users.
- Force password reset.
- Reset MFA after identity verification.
- Review sessions and revoke active sessions.
- Review audit logs with filters by actor, event, IP, object, and date.
- Manage CIDR allowlists.
- Configure retention windows.

Sensitive admin actions require CSRF validation, active session freshness, and audit logging.

## Audit logs

Audit events include:

- Login success/failure/MFA challenge/MFA failure.
- Vault entry viewed, copied, created, edited, deleted, restored, and shared.
- User created, disabled, role changed, MFA reset.
- Session revoked.
- API token created/revoked.
- Settings changed.

Audit log records contain actor UUID when known, attempted username when not, IP, user agent, event name, object type/id, result, and sanitized metadata. Metadata must never include plaintext vault secrets, passwords, recovery codes, TOTP secrets, session IDs, API token plaintext, or encryption keys.

## Sharing

Entry and folder shares are explicit and revocable. Read-only shares cannot reveal historical passwords unless separately granted. Future per-recipient key wrapping is reserved by the schema through envelope metadata and share tables.

## Retention

Retention policy should keep security logs long enough for incident response while respecting organizational requirements. Production deployments should schedule periodic export and pruning jobs, with encrypted archives stored separately from the database host.

