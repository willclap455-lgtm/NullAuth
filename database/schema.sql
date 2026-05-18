BEGIN;

CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE EXTENSION IF NOT EXISTS citext;

CREATE TYPE audit_result AS ENUM ('success', 'failure', 'denied');
CREATE TYPE share_permission AS ENUM ('read', 'write', 'share', 'owner');
CREATE TYPE vault_entry_status AS ENUM ('active', 'deleted', 'archived');

CREATE TABLE roles (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name text NOT NULL UNIQUE,
    description text NOT NULL DEFAULT '',
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE permissions (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name text NOT NULL UNIQUE,
    description text NOT NULL DEFAULT ''
);

CREATE TABLE role_permissions (
    role_id uuid NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    permission_id uuid NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, permission_id)
);

CREATE TABLE users (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    email citext NOT NULL UNIQUE,
    username citext NOT NULL UNIQUE,
    display_name text NOT NULL,
    password_hash text NOT NULL,
    password_changed_at timestamptz NOT NULL DEFAULT now(),
    force_password_reset boolean NOT NULL DEFAULT false,
    is_disabled boolean NOT NULL DEFAULT false,
    locked_until timestamptz,
    last_login_at timestamptz,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    deleted_at timestamptz,
    CONSTRAINT users_email_not_blank CHECK (length(trim(email::text)) > 3),
    CONSTRAINT users_username_not_blank CHECK (length(trim(username::text)) >= 3)
);

CREATE TABLE user_roles (
    user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role_id uuid NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    created_at timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (user_id, role_id)
);

CREATE TABLE mfa_totp (
    user_id uuid PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    secret_envelope jsonb NOT NULL,
    enabled_at timestamptz,
    last_used_step bigint,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE mfa_recovery_codes (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    code_hash text NOT NULL,
    used_at timestamptz,
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE folders (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    owner_user_id uuid NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    parent_id uuid REFERENCES folders(id) ON DELETE SET NULL,
    name text NOT NULL,
    description text NOT NULL DEFAULT '',
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    deleted_at timestamptz,
    CONSTRAINT folders_name_not_blank CHECK (length(trim(name)) > 0)
);

CREATE INDEX folders_owner_idx ON folders(owner_user_id) WHERE deleted_at IS NULL;
CREATE INDEX folders_parent_idx ON folders(parent_id) WHERE deleted_at IS NULL;

CREATE TABLE categories (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    owner_user_id uuid NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    name text NOT NULL,
    color text NOT NULL DEFAULT '#0d6efd',
    created_at timestamptz NOT NULL DEFAULT now(),
    deleted_at timestamptz,
    UNIQUE (owner_user_id, name)
);

CREATE TABLE vault_entries (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    owner_user_id uuid NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    folder_id uuid REFERENCES folders(id) ON DELETE SET NULL,
    category_id uuid REFERENCES categories(id) ON DELETE SET NULL,
    title text NOT NULL,
    username_envelope jsonb,
    password_envelope jsonb NOT NULL,
    url text,
    notes_envelope jsonb,
    custom_fields_envelope jsonb,
    tags text[] NOT NULL DEFAULT '{}',
    favorite boolean NOT NULL DEFAULT false,
    expires_at timestamptz,
    status vault_entry_status NOT NULL DEFAULT 'active',
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    deleted_at timestamptz,
    CONSTRAINT vault_entries_title_not_blank CHECK (length(trim(title)) > 0),
    CONSTRAINT vault_entries_url_reasonable CHECK (url IS NULL OR length(url) <= 2048)
);

CREATE INDEX vault_entries_owner_active_idx ON vault_entries(owner_user_id, updated_at DESC) WHERE deleted_at IS NULL AND status = 'active';
CREATE INDEX vault_entries_folder_idx ON vault_entries(folder_id) WHERE deleted_at IS NULL;
CREATE INDEX vault_entries_tags_gin_idx ON vault_entries USING gin(tags);

CREATE TABLE vault_entry_password_history (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    vault_entry_id uuid NOT NULL REFERENCES vault_entries(id) ON DELETE CASCADE,
    password_envelope jsonb NOT NULL,
    changed_by_user_id uuid REFERENCES users(id) ON DELETE SET NULL,
    changed_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX vault_entry_password_history_entry_idx ON vault_entry_password_history(vault_entry_id, changed_at DESC);

CREATE TABLE vault_entry_attachments (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    vault_entry_id uuid NOT NULL REFERENCES vault_entries(id) ON DELETE CASCADE,
    stored_name text NOT NULL UNIQUE,
    original_name_envelope jsonb NOT NULL,
    mime_type text NOT NULL,
    size_bytes bigint NOT NULL CHECK (size_bytes >= 0),
    sha256_hex text NOT NULL CHECK (sha256_hex ~ '^[a-f0-9]{64}$'),
    content_envelope jsonb NOT NULL,
    uploaded_by_user_id uuid REFERENCES users(id) ON DELETE SET NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    deleted_at timestamptz
);

CREATE TABLE vault_entry_shares (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    vault_entry_id uuid NOT NULL REFERENCES vault_entries(id) ON DELETE CASCADE,
    grantee_user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    permission share_permission NOT NULL,
    granted_by_user_id uuid NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    created_at timestamptz NOT NULL DEFAULT now(),
    revoked_at timestamptz,
    UNIQUE (vault_entry_id, grantee_user_id, permission)
);

CREATE INDEX vault_entry_shares_grantee_idx ON vault_entry_shares(grantee_user_id) WHERE revoked_at IS NULL;

CREATE TABLE folder_shares (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    folder_id uuid NOT NULL REFERENCES folders(id) ON DELETE CASCADE,
    grantee_user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    permission share_permission NOT NULL,
    granted_by_user_id uuid NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    created_at timestamptz NOT NULL DEFAULT now(),
    revoked_at timestamptz,
    UNIQUE (folder_id, grantee_user_id, permission)
);

CREATE TABLE audit_logs (
    id bigserial PRIMARY KEY,
    occurred_at timestamptz NOT NULL DEFAULT now(),
    actor_user_id uuid REFERENCES users(id) ON DELETE SET NULL,
    username_attempted citext,
    ip inet,
    user_agent text,
    event text NOT NULL,
    object_type text,
    object_id uuid,
    result audit_result NOT NULL,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    CONSTRAINT audit_event_not_blank CHECK (length(trim(event)) > 0)
);

CREATE INDEX audit_logs_occurred_idx ON audit_logs(occurred_at DESC);
CREATE INDEX audit_logs_actor_idx ON audit_logs(actor_user_id, occurred_at DESC);
CREATE INDEX audit_logs_event_idx ON audit_logs(event, occurred_at DESC);

CREATE TABLE login_attempts (
    id bigserial PRIMARY KEY,
    attempted_at timestamptz NOT NULL DEFAULT now(),
    username_attempted citext NOT NULL,
    normalized_username text NOT NULL,
    ip inet NOT NULL,
    user_agent text,
    success boolean NOT NULL,
    failure_reason text,
    lockout_until timestamptz
);

CREATE INDEX login_attempts_account_idx ON login_attempts(normalized_username, attempted_at DESC);
CREATE INDEX login_attempts_ip_idx ON login_attempts(ip, attempted_at DESC);

CREATE TABLE sessions (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    session_id_hash text NOT NULL UNIQUE,
    ip_hash text NOT NULL,
    user_agent_hash text NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    last_seen_at timestamptz NOT NULL DEFAULT now(),
    idle_expires_at timestamptz NOT NULL,
    absolute_expires_at timestamptz NOT NULL,
    revoked_at timestamptz
);

CREATE INDEX sessions_user_active_idx ON sessions(user_id, last_seen_at DESC) WHERE revoked_at IS NULL;

CREATE TABLE api_tokens (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name text NOT NULL,
    token_hash text NOT NULL UNIQUE,
    scopes text[] NOT NULL DEFAULT '{}',
    last_used_at timestamptz,
    expires_at timestamptz,
    created_at timestamptz NOT NULL DEFAULT now(),
    revoked_at timestamptz,
    CONSTRAINT api_tokens_name_not_blank CHECK (length(trim(name)) > 0)
);

CREATE TABLE password_generator_profiles (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name text NOT NULL,
    settings jsonb NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (user_id, name)
);

CREATE TABLE system_settings (
    key text PRIMARY KEY,
    value jsonb NOT NULL,
    updated_by_user_id uuid REFERENCES users(id) ON DELETE SET NULL,
    updated_at timestamptz NOT NULL DEFAULT now()
);

INSERT INTO roles (name, description) VALUES
    ('administrator', 'Full administrative access'),
    ('operator', 'Standard vault user'),
    ('auditor', 'Audit review without secret reveal')
ON CONFLICT DO NOTHING;

INSERT INTO permissions (name, description) VALUES
    ('users.manage', 'Create, disable, and update users'),
    ('roles.manage', 'Manage RBAC assignments'),
    ('vault.read', 'Read own and shared vault metadata'),
    ('vault.write', 'Create and update vault entries'),
    ('vault.reveal', 'Reveal authorized vault secrets'),
    ('vault.share', 'Share vault entries and folders'),
    ('audit.read', 'Read audit logs'),
    ('settings.manage', 'Manage system settings')
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.name IN ('users.manage', 'roles.manage', 'vault.read', 'vault.write', 'vault.reveal', 'vault.share', 'audit.read', 'settings.manage')
WHERE r.name = 'administrator'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.name IN ('vault.read', 'vault.write', 'vault.reveal', 'vault.share')
WHERE r.name = 'operator'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.name IN ('audit.read')
WHERE r.name = 'auditor'
ON CONFLICT DO NOTHING;

COMMIT;
