# Testing Guide

This project uses [PHPUnit](https://phpunit.de/) for unit testing, managed via [Composer](https://getcomposer.org/).

## Prerequisites

- PHP 8.3+ (required by PHPUnit ^13.2 — check your version with `php -v`)
- [Composer](https://getcomposer.org/) installed

## Setup

After cloning the repo or pulling changes that touch `composer.json`, install dependencies:

```
composer install
```

This downloads PHPUnit and any other dependencies into a local `vendor/` folder. `vendor/` is gitignored — everyone generates their own copy locally, based on the exact versions locked in `composer.lock`. You do not need to install PHPUnit separately; Composer handles it.

### Database Driver (Windows Users)
If running native PHP on Windows (without WSL or XAMPP pre-configured), ensure the SQLite extension is enabled in your `php.ini` file:
1. Open your `php.ini` file (run `php --ini` in terminal to find its location).
2. Uncomment the line by removing the leading semicolon `;`:
   ```ini
   extension=pdo_sqlite
3. Save the file.

## Running the tests

From the project root:

```
vendor/bin/phpunit
```

This automatically discovers and runs every `*Test.php` file inside `tests/Unit/` — no manual registration needed. When you add a new test file to that folder, it's picked up on the next run automatically.

## Test output / logs

Every run automatically generates two log files under `tests/logs/`:

- `results.xml` — JUnit-format XML (useful for CI tools to parse)
- `results.html` — human-readable HTML summary

`tests/logs/` is gitignored, since these are regenerated output, not source.

## Project structure

```
project-root/
├── api/                    # Live endpoints — untouched aside from small require_once additions
├── src/
│   └── Helpers/             # Extracted, pure-logic functions (no DB/HTTP side effects)
├── tests/
│   └── Unit/                 # One *Test.php file per Helpers file
├── composer.json
├── composer.lock
└── phpunit.xml
```

## The pattern we're following

Most of the API files in `api/` mix two things: pure logic (validation, sanitization, data transformation) and side effects (DB queries, HTTP headers, session handling). Side-effecting code is hard to unit test meaningfully without mocking a database — so instead, we're incrementally pulling out the **pure logic only** into small helper files under `src/Helpers/`, and unit testing those directly.

Each helper file is named after the API file it was extracted from (e.g. `LoginHelpers.php` came from `login.php`). The original API file then `require_once`s the helper and calls its functions instead of containing the logic inline.

**Not every file needs this.** Some files (e.g. `logout.php`) are pure side effects with no real branching logic — nothing meaningful to unit test in isolation. Those are left as-is for now and are better candidates for future integration/API-level testing rather than unit tests.

### Adding a new helper file — checklist

1. Identify the pure-logic pieces in the target API file (no DB calls, no `$_SERVER`/`$_SESSION`/`echo`, deterministic input → output).
2. Create `src/Helpers/<Name>Helpers.php` with those pieces extracted into standalone functions.
3. Update the original API file to `require_once` the new helper and call its functions instead of the inline logic.
4. Add the new helper file's path to `autoload.files` in `composer.json`.
5. Run `composer dump-autoload` (required any time `autoload` changes — editing `composer.json` alone does not regenerate the autoloader).
6. Write `tests/Unit/<Name>HelpersTest.php` covering the extracted functions, including edge cases (empty/null input, boundary values, malformed data).
7. Run `vendor/bin/phpunit` and confirm everything passes before committing.

## Current coverage

| API file                     | Helper file                       | Test file                            |
| ---------------------------- | --------------------------------- | ------------------------------------ |
| `reserve.php` / `tables.php` | `src/Helpers/TimeHelpers.php`     | `tests/Unit/TimeHelpersTest.php`     |
| `profile.php`                | `src/Helpers/ProfileHelpers.php`  | `tests/Unit/ProfileHelpersTest.php`  |
| `login.php`                  | `src/Helpers/LoginHelpers.php`    | `tests/Unit/LoginHelpersTest.php`    |
| `language.php`               | `src/Helpers/LanguageHelpers.php` | `tests/Unit/LanguageHelpersTest.php` |
| `logout.php`                 | — (no extractable logic)          | —                                    |

_(Update this table as more files are covered.)_

## Known gaps / things intentionally left as-is

- `login.php` compares passwords in plaintext against the database (no hashing). This is a known, pre-existing issue in the inherited codebase — flagged here for visibility, but out of scope for the testing work itself.
- `ProfileHelpersTest.php` documents (rather than "fixes") a validation quirk: a whitespace-only email currently passes `hasValidEmail()`'s check, since PHP's `empty()` doesn't treat `"   "` as empty. Worth a team decision on whether to tighten this later.
