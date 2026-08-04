#!/usr/bin/env bash
# scripts/setup.sh
# One-time local setup: symlink → db config → database import.
# Run once per developer machine. Safe to re-run (idempotent).
#
# Environment overrides (all optional):
#   XAMPP_HTDOCS   – path to XAMPP htdocs  (default: /Applications/XAMPP/xamppfiles/htdocs)
#   XAMPP_MYSQL    – path to mysql binary   (default: /Applications/XAMPP/xamppfiles/bin/mysql)
#   DB_HOST        – MySQL host             (default: localhost)
#   DB_PORT        – MySQL port             (default: 3307, XAMPP offset to avoid Oracle MySQL on 3306)
#   DB_NAME        – database name          (default: reservehub)
#   DB_USER        – MySQL user             (default: root)
#   DB_PASS        – MySQL password         (default: empty)

set -euo pipefail

# Colour helpers 
# shellcheck source=scripts/utils.sh
source "$(dirname "${BASH_SOURCE[0]}")/utils.sh"

# Resolve paths
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
HTDOCS="${XAMPP_HTDOCS:-/Applications/XAMPP/xamppfiles/htdocs}"
MYSQL_BIN="${XAMPP_MYSQL:-/Applications/XAMPP/xamppfiles/bin/mysql}"
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3307}"
DB_NAME="${DB_NAME:-reservehub}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

echo ""
echo -e "${BOLD}${CYAN}╔══════════════════════════════════════╗${NC}"
echo -e "${BOLD}${CYAN}║     ReserveHub  ·  Local Setup       ║${NC}"
echo -e "${BOLD}${CYAN}╚══════════════════════════════════════╝${NC}"
echo ""

# Step 1: Verify XAMPP htdocs
info "Checking XAMPP htdocs at: $HTDOCS"
if [[ ! -d "$HTDOCS" ]]; then
  die "XAMPP htdocs not found at '$HTDOCS'.\n       Set XAMPP_HTDOCS to override, e.g.:\n         XAMPP_HTDOCS=/opt/lampp/htdocs make setup"
fi
success "XAMPP htdocs found."

# Step 2: Copy repo into htdocs
DEST="$HTDOCS/reservehub"

# Remove any leftover symlink from the old setup workflow
if [[ -L "$DEST" ]]; then
  warn "Existing symlink found at '$DEST'. Removing and replacing with a copy."
  rm -f "$DEST"
fi

info "Copying repo into $DEST (this may take a moment)..."
rsync -a --delete --exclude='.git' "$REPO_ROOT/" "$DEST/"
success "Copied to: $DEST"

# Step 3: Create api/db.php
DB_CONF="$REPO_ROOT/api/db.php"
if [[ -f "$DB_CONF" ]]; then
  success "api/db.php already exists — skipping (will not overwrite)."
else
  cp "$REPO_ROOT/api/db.example.php" "$DB_CONF"
  success "api/db.php created from db.example.php."
fi

# Step 4: Resolve mysql binary 
if [[ ! -x "$MYSQL_BIN" ]]; then
  MYSQL_BIN="$(command -v mysql 2>/dev/null || true)"
  if [[ -z "$MYSQL_BIN" ]]; then
    die "mysql client not found.\n       Make sure XAMPP MySQL is started, then re-run: make setup\n       Or set XAMPP_MYSQL=/path/to/mysql and re-run."
  fi
fi
info "Using mysql binary: $MYSQL_BIN"

# Step 5: Verify MySQL is reachable
MYSQL_OPTS=(-h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" --connect-timeout=5)
[[ -n "$DB_PASS" ]] && MYSQL_OPTS+=(-p"$DB_PASS")

if ! "$MYSQL_BIN" "${MYSQL_OPTS[@]}" -e "SELECT 1;" &>/dev/null; then
  die "Cannot connect to MySQL at ${DB_HOST}:${DB_PORT} (user=${DB_USER}).\n       Start XAMPP MySQL, then re-run: make setup"
fi
success "MySQL connection OK."

# Step 6: Create database + import schema 
info "Creating database '$DB_NAME' (if not exists)..."
"$MYSQL_BIN" "${MYSQL_OPTS[@]}" \
  -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

info "Importing sql/local_setup.sql (idempotent — safe to re-run)..."
"$MYSQL_BIN" "${MYSQL_OPTS[@]}" "$DB_NAME" < "$REPO_ROOT/sql/local_setup.sql"
success "Database '$DB_NAME' ready (5 restaurants, 42 tables, 15 reviews, 3 test accounts)."

# Done 
echo ""
echo -e "${BOLD}${GREEN}Setup complete!${NC}"
echo -e "  ${BOLD}URL${NC}      → ${CYAN}http://localhost/reservehub${NC}"
echo -e "  ${BOLD}Accounts${NC} → admin@test.com / admin123"
echo -e "             vendor@test.com / vendor123"
echo -e "             customer@test.com / test123"
echo ""
echo -e "  ${YELLOW}Tip:${NC} Run ${BOLD}make sync${NC} after editing files to push changes to htdocs."
echo ""
