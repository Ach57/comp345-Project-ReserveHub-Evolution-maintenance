# Local Development Automated Setup Guide

This automated setup is an extension of the orginal manual guideline provided in the following document [setup guidline](SETUP.md).

---

## 1. Problem identified

### 1.1 Analysis

The real problems we wanted to solve

| Pain point                                    | Cause                                    |
| --------------------------------------------- | ---------------------------------------- |
| `cp -r` on every change                       | No automated sync between repo and `htdocs` |
| Manual `cp db.example.php db.php`             | No setup automation                      |
| Manual phpMyAdmin SQL import                  | No CLI database provisioning             |
| Scattered migration files with no clear order | Schema management debt                   |

---

### 1.2 Proposed solution

Shell scripts in a `scripts/` folder is the right call for this project because Docker/npm/etc are worse fits.

- **Docker**: Overkill for this project as it replaces XAMPP (which the whole team already uses)
- **npm scripts** adds Node.js as a runtime dependency to a pure PHP project. Wrong ecosystem.
- **Shell scripts** zero extra dependencies on macOS/Linux, readable, composable, version-controlled along the code.

---

## 2. Implemented Plan

```bash
scripts/
  setup.sh        # One-time: symlink + db config + DB import
  db-reset.sh     # Re-import sql/local_setup.sql at any time
  check.sh        # Verify XAMPP is running + DB connection works
Makefile          # Thin wrapper: `make setup`, `make db-reset`, `make check`
```

The `Makefile` at the root as the unified entry point calling the .sh files.

---

### 2.1 What each script does

- [setup.sh](../scripts/setup.sh) (run once per developer machine):
  1. Detect XAMPP htdocs path (macOS vs Windows/WSL)
  2. Copy the repo into `htdocs/reservehub` using `rsync` (removes any leftover symlink from older setups)
  3. Copy [db.example.php](../api/db.example.php) → [db.php](../api/db.php) (only if not already present, never overwrite)
  4. Check if MySQL is reachable; if yes, create the `reservehub` database and import [local_setup.sql](../sql/local_setup.sql)
  5. Print verification URL

- [db-reset.sh](../scripts/db-reset.sh)
  1. Drop and re-import [local_setup.sql](../sql/local_setup.sql)
  2. Used when schema changes or you want clean seed data

- [check.sh](../scripts/check.sh)
  1. Verify [db.php](../api/db.php) exists
  2. Verify symlink is healthy
  3. Verify XAMPP Apache + MySQL processes are running
  4. Ping the app URL

- [MakeFile](../Makefile) targets
  ```
  make setup      → runs setup.sh
  make sync       → rsyncs repo changes to htdocs (run after editing files)
  make db-reset   → runs db-reset.sh
  make check      → runs check.sh
  make open       → opens http://localhost/reservehub in browser
  make help       → lists all targets
  ```

---

## 3. What changes in the developer workflow:

Before:

1. `cp -r repo/ /Applications/XAMPP/.../htdocs/reservehub`
2. Open phpMyAdmin, manually import SQL
3. Manually copy db.php
4. Repeat step 1 on every code change

after:

1. `make setup` (once, ever)
2. Edit files in VS Code → run `make sync` → refresh browser
3. `make db-reset` when you need a clean DB

---

## 4. Scope Boundaries

What we did not add:

- No Windows PowerShell scripts in this pass (add later if needed)
- No changes to PHP files, SQL files, or XAMPP config
- No new dependencies introduced
- The scripts only automate what [SETUP.md](SETUP.md) already describes.
