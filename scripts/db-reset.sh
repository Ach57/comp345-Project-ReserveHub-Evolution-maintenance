#!/usr/bin/env bash
# scripts/db-reset.sh
# Drop and re-import sql/local_setup.sql — resets all tables and seed data.
# WARNING: all existing data will be lost.
#
# Same environment overrides as setup.sh (DB_HOST, DB_USER, DB_PASS, etc.)

set -euo pipefail

# Colour helpers
# shellcheck source=scripts/utils.sh
source "$(dirname "${BASH_SOURCE[0]}")/utils.sh"

# Paths / config
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MYSQL_BIN="${XAMPP_MYSQL:-/Applications/XAMPP/xamppfiles/bin/mysql}"
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3307}"
DB_NAME="${DB_NAME:-reservehub}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

echo ""
warn "This will DROP and recreate all tables in '${DB_NAME}' and reload seed data."
warn "All existing reservations, users, and reviews will be permanently deleted."
echo ""
read -r -p "Type 'yes' to continue, anything else to abort: " confirm
echo ""

if [[ "$confirm" != "yes" ]]; then
  info "Aborted. Database was not modified."
  exit 0
fi

# Resolve mysql binary
if [[ ! -x "$MYSQL_BIN" ]]; then
  MYSQL_BIN="$(command -v mysql 2>/dev/null || true)"
  [[ -z "$MYSQL_BIN" ]] && die "mysql client not found. Is XAMPP MySQL running?"
fi

MYSQL_OPTS=(-h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" --connect-timeout=5)
[[ -n "$DB_PASS" ]] && MYSQL_OPTS+=(-p"$DB_PASS")

# Verify connection 
if ! "$MYSQL_BIN" "${MYSQL_OPTS[@]}" -e "SELECT 1;" &>/dev/null; then
  die "Cannot connect to MySQL at ${DB_HOST}:${DB_PORT}. Is XAMPP MySQL running?"
fi

# Import 
info "Importing sql/local_setup.sql into '${DB_NAME}'..."
"$MYSQL_BIN" "${MYSQL_OPTS[@]}" "$DB_NAME" < "$REPO_ROOT/sql/local_setup.sql"
success "Database reset complete. Seed data restored."
echo ""
