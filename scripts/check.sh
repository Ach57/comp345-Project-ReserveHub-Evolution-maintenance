#!/usr/bin/env bash
# scripts/check.sh
# Verify the local development environment is correctly configured.
# Exits with code 0 if all checks pass, non-zero otherwise.

# Note: no set -e here — we want every check to run even if earlier ones fail.
set -uo pipefail

# Colour helpers
# shellcheck source=scripts/utils.sh
source "$(dirname "${BASH_SOURCE[0]}")/utils.sh"

FAILURES=0

# Config
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
HTDOCS="${XAMPP_HTDOCS:-/Applications/XAMPP/xamppfiles/htdocs}"
MYSQL_BIN="${XAMPP_MYSQL:-/Applications/XAMPP/xamppfiles/bin/mysql}"
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3307}"
DB_NAME="${DB_NAME:-reservehub}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

echo ""
echo -e "${BOLD}${CYAN}ReserveHub — Environment Check${NC}"
echo -e "────────────────────────────────"

# 1. api/db.php 
if [[ -f "$REPO_ROOT/api/db.php" ]]; then
  pass "api/db.php exists"
else
  fail "api/db.php missing  →  run: make setup"
fi

# 2. htdocs copy
DEST="$HTDOCS/reservehub"
if [[ -d "$DEST" && ! -L "$DEST" ]]; then
  pass "htdocs copy exists  →  $DEST"
elif [[ -L "$DEST" ]]; then
  warn "'$DEST' is a symlink (old workflow)  →  run: make setup to convert to a copy"
else
  fail "htdocs copy missing  →  run: make setup"
fi

# 3. Apache 
if pgrep -f "httpd" &>/dev/null || pgrep -f "apache2" &>/dev/null; then
  pass "Apache is running"
else
  fail "Apache not running  →  start XAMPP Apache"
fi

# 4. MySQL process
if pgrep -f "mysqld" &>/dev/null; then
  pass "MySQL is running"
else
  fail "MySQL not running  →  start XAMPP MySQL"
fi

# 5. MySQL connection + database
if [[ ! -x "$MYSQL_BIN" ]]; then
  MYSQL_BIN="$(command -v mysql 2>/dev/null || true)"
fi

if [[ -n "$MYSQL_BIN" && -x "$MYSQL_BIN" ]]; then
  MYSQL_OPTS=(-h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" --connect-timeout=3)
  [[ -n "$DB_PASS" ]] && MYSQL_OPTS+=(-p"$DB_PASS")

  if "$MYSQL_BIN" "${MYSQL_OPTS[@]}" -e "SELECT 1;" &>/dev/null 2>&1; then
    pass "MySQL connection OK  (user=${DB_USER}, host=${DB_HOST}:${DB_PORT})"

    if "$MYSQL_BIN" "${MYSQL_OPTS[@]}" -e "USE \`${DB_NAME}\`;" &>/dev/null 2>&1; then
      # Count rows in a key table to confirm seed data is present
      row_count=$("$MYSQL_BIN" "${MYSQL_OPTS[@]}" "$DB_NAME" \
        -se "SELECT COUNT(*) FROM restaurants;" 2>/dev/null || echo "?")
      pass "Database '${DB_NAME}' exists  (restaurants: ${row_count})"
    else
      fail "Database '${DB_NAME}' not found  →  run: make setup"
    fi
  else
    fail "Cannot connect to MySQL  →  check credentials or start XAMPP MySQL"
  fi
else
  warn "mysql client not found — skipping connection check"
fi

# Summary 
echo ""
if [[ $FAILURES -eq 0 ]]; then
  echo -e "${GREEN}${BOLD}All checks passed.${NC}"
  echo -e "  Open → ${CYAN}http://localhost/reservehub${NC}"
else
  echo -e "${RED}${BOLD}${FAILURES} check(s) failed.${NC}  Resolve the issues above, then re-run: make check"
fi
echo ""

exit "$FAILURES"
