#!/usr/bin/env bash
# One-time project setup: git hooks, local env files, dev image, dependencies.
#
#   scripts/setup.sh            uses host.docker.internal:5432 for PostgreSQL
#   POSTGRES_PORT=5433 scripts/setup.sh
set -euo pipefail

cd "$(dirname "$0")/.."

git config core.hooksPath .githooks
echo "git hooks aktiviert (.githooks)"

port="${POSTGRES_PORT:-5432}"
if [ ! -f .env.local ]; then
  cat > .env.local <<EOT
DATABASE_URL="postgresql://sk8:sk8@host.docker.internal:${port}/sk8_docs?serverVersion=16&charset=utf8"
EOT
  echo ".env.local angelegt (PostgreSQL auf Port ${port})"
fi
if [ ! -f .env.test.local ]; then
  cat > .env.test.local <<EOT
DATABASE_URL="postgresql://sk8:sk8@host.docker.internal:${port}/sk8_docs_test?serverVersion=16&charset=utf8"
EOT
  echo ".env.test.local angelegt"
fi

make build
make composer ARGS="install"
echo
echo "Fertig. Weiter mit: make up  (http://localhost:8001)  ·  make test"
