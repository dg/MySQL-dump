# To My Agents!

It is my fervent wish that this file guide every AI coding agent working with code in this repository.

## Project

`dg/mysql-dump` is a tiny single-purpose library: dump a MySQL database to SQL (optionally gzipped) and import it back. Public surface is two top-level classes wrapping `mysqli`.

- Minimum PHP: **8.2** (composer.json `require`). Code uses modern features: typed properties, constructor property promotion with `readonly`, first-class callable / `\Closure` typed callbacks, nullsafe operator (`?->`), `str_starts_with` / `str_ends_with`. Do not introduce 8.3+ syntax (e.g. typed class constants, `#[\Override]`) without bumping the constraint.
- No build, lint, or test tooling is configured. There is no `phpunit`, `phpstan`, `ecs`, `vendor/bin/tester` etc. The `tests/` directory is empty. Do not invent commands.
- Autoload is **classmap** over `src/`, so the classes are in the global namespace (no namespace declaration). Keep it that way — adding a namespace is a breaking API change.

## Architecture

Two independent classes, each constructed with a `mysqli` connection and an optional charset (default `utf8mb4`, set via `$connection->set_charset()` in the constructor).

- `src/MySQLDump.php` — writes `CREATE TABLE`, optional `DROP`, row `INSERT`s, views, triggers, stored functions, stored procedures, and scheduled events to a stream. Public bitmask flags `NONE | DROP | CREATE | DATA | TRIGGERS | ROUTINES` (and `ALL = 31`) selected via the `$tables` array, with `'*'` as the default. Entry points: `save(string $file)` (auto-gzips when filename ends in `.gz`) and `write($handle)` (any writable stream resource; defaults to `php://output`). `MAX_SQL_SIZE = 1e6` is the cutoff for chunking multi-row `INSERT` statements.
  - **Flag semantics caveat:** `DROP | CREATE | DATA | TRIGGERS` apply per-table (read from `$tables[$name]` with `$tables['*']` as fallback). `ROUTINES` is database-level and read **only from `$tables['*']`** — same data structure, asymmetric meaning. If `'*'` is removed, routines silently disable. When `ROUTINES` is on, `DROP`/`CREATE` bits on `$tables['*']` also gate `DROP`/`CREATE` emission for functions, procedures, events, and triggers.
  - **Singular public methods** for individual objects: `dumpTable($handle, $name)`, `dumpFunction($handle, $name)`, `dumpProcedure($handle, $name)`, `dumpEvent($handle, $name)`. `write()` enumerates and batches them; routines/events are wrapped in a single `DELIMITER ;;` block. Standalone singular calls wrap their own `DELIMITER`.
  - **Trigger bodies must come from `SHOW CREATE TRIGGER`**, never from the `Statement` column of `SHOW TRIGGERS` (i.e. `information_schema.TRIGGERS.ACTION_STATEMENT`). That column is MySQL's normalized re-rendering of the body, in which escaping inside string literals is lost (`''` collapses to `'`), producing an invalid dump. The same applies to routines, which is why `writeRoutine()` uses `SHOW CREATE`. Enumerate with ``SHOW TRIGGERS WHERE `Table` = '…'``, not `LIKE` — `_` in a table name would be a wildcard.
  - `stripDefiner()` removes the `DEFINER=` clause from every `SHOW CREATE` output (routines, events, triggers). Its regex must tolerate a backtick-quoted user/host containing spaces, so a plain `\S+` is not enough.
  - **Known gaps:** `sql_mode`, `character_set_client`, `collation_connection` from `SHOW CREATE FUNCTION/PROCEDURE/EVENT/TRIGGER` are **not** emitted — imports on a server with a different sql_mode may behave differently. Real `mysqldump` emits `SET SESSION sql_mode = '...'` before each routine; this library does not.
- `src/MySQLImport.php` — streams a `.sql` or `.sql.gz` file back into the connection, splitting on statement boundaries. Recognizes `DELIMITER` directive so multi-statement routine bodies import correctly. Exposes `$onProgress` (`?\Closure` of signature `function (int $count, ?float $percent)`) for progress reporting (percent is null for gzipped input, where total size is unknown).

`examples/` holds runnable usage snippets (`writeToFile.php`, `sendAsFile.php`, `importFromFile.php`); treat them as the canonical usage reference and update them in sync with any API change.

## Conventions

- Single-line `<?php declare(strict_types=1);` header (see commit `47949fc`).
- Typed properties throughout. The `mysqli` connection is a promoted `private readonly` constructor parameter. The `$onProgress` callback is a nullable `?\Closure` (invoked via `$this->onProgress?->__invoke(...)`). `$handle` stream-resource params stay untyped (`resource` is not a PHP type) and keep their `@param resource` docblock.
- Errors are reported by throwing the bare `\Exception` (global namespace). Match that style; do not introduce custom exception classes.
