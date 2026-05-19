#!/usr/bin/env bash
set -euo pipefail

# Installs the local tooling required to test and debug NullAuth on Ubuntu 24.04
# Cursor Cloud machines and equivalent development hosts. Pass --with-postgres-server
# to install a local PostgreSQL server for schema validation.

WITH_POSTGRES_SERVER=0
for arg in "$@"; do
  case "${arg}" in
    --with-postgres-server)
      WITH_POSTGRES_SERVER=1
      ;;
    *)
      echo "Unknown argument: ${arg}" >&2
      exit 1
      ;;
  esac
done

if [[ "${EUID}" -ne 0 ]]; then
  if command -v sudo >/dev/null 2>&1; then
    SUDO=(sudo)
  else
    echo "This setup script must run as root or with sudo available." >&2
    exit 1
  fi
else
  SUDO=()
fi

"${SUDO[@]}" apt-get update
"${SUDO[@]}" env DEBIAN_FRONTEND=noninteractive apt-get install -y \
  composer \
  php-cli \
  php-curl \
  php-mbstring \
  php-pgsql \
  php-xml \
  postgresql-client

if [[ "${WITH_POSTGRES_SERVER}" -eq 1 ]]; then
  "${SUDO[@]}" env DEBIAN_FRONTEND=noninteractive apt-get install -y postgresql
fi

php -m | grep -Eq '^sodium$' || {
  echo "PHP sodium extension is required but not loaded." >&2
  exit 1
}

php -m | grep -Eq '^pdo_pgsql$' || {
  echo "PHP pdo_pgsql extension is required but not loaded." >&2
  exit 1
}

php -v
composer --version
psql --version

if [[ "${WITH_POSTGRES_SERVER}" -eq 1 ]]; then
  "${SUDO[@]}" pg_ctlcluster 16 main start || true
  pg_isready
fi
