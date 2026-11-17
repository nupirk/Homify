<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The schema was written against PostgreSQL: enum types, column comments and
 * constraints that PostgreSQL can alter in place. SQLite supports none of
 * those, so migrations route their raw DDL through here.
 *
 * On PostgreSQL every statement runs exactly as written. On SQLite the
 * PostgreSQL-only statements are skipped (SQLite has no types to enforce, and
 * the application validates the same rules) and the two ALTER TABLE forms
 * SQLite lacks are rewritten.
 */
final class SchemaCompat
{
    public static function isPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    /**
     * Primary key fragment for the tables created with raw DDL.
     *
     * BIGSERIAL is a PostgreSQL alias for bigint + sequence. SQLite accepts the
     * name as a type but then the column is not a rowid alias, so inserts never
     * get an id back; it needs INTEGER PRIMARY KEY AUTOINCREMENT instead.
     */
    public static function serialPrimaryKey(): string
    {
        return self::isPostgres()
            ? 'id BIGSERIAL PRIMARY KEY'
            : 'id INTEGER PRIMARY KEY AUTOINCREMENT';
    }

    /**
     * Run raw DDL that may use PostgreSQL-only syntax.
     */
    public static function statement(string $sql): void
    {
        if (self::isPostgres()) {
            DB::statement($sql);

            return;
        }

        $normalized = strtoupper(trim((string) preg_replace('/\s+/', ' ', $sql)));

        if (self::isPostgresOnly($normalized)) {
            return;
        }

        // SQLite has no IF [NOT] EXISTS on ALTER TABLE ... COLUMN.
        if (str_contains($normalized, 'ADD COLUMN IF NOT EXISTS')) {
            self::tolerant(fn () => DB::statement(
                (string) preg_replace('/ADD COLUMN IF NOT EXISTS/i', 'ADD COLUMN', $sql)
            ));

            return;
        }

        if (str_contains($normalized, 'DROP COLUMN IF EXISTS')) {
            self::tolerant(fn () => DB::statement(
                (string) preg_replace('/DROP COLUMN IF EXISTS/i', 'DROP COLUMN', $sql)
            ));

            return;
        }

        DB::statement($sql);
    }

    /**
     * Case-insensitive LIKE. SQLite's LIKE is already case-insensitive for the
     * ASCII text these searches use, and has no ILIKE operator.
     */
    public static function likeOperator(): string
    {
        return self::isPostgres() ? 'ILIKE' : 'LIKE';
    }

    /**
     * SQL expression for a boolean-ish flag inside a JSON column.
     *
     * PostgreSQL stores JSON as-is and ->> yields 'true'. SQLite's json_extract
     * returns 1 for a JSON true, so a bare comparison with 'true' silently
     * misses those rows; this normalises both spellings to 'true'.
     */
    public static function jsonFlagExpression(string $column, string $key): string
    {
        if (self::isPostgres()) {
            return "({$column}->>'{$key}')";
        }

        return "(CASE WHEN json_extract({$column}, '$.{$key}') IN (1, 'true') THEN 'true' ELSE 'false' END)";
    }

    /**
     * Drop a unique constraint created by $table->unique().
     *
     * PostgreSQL stores it as a table constraint; SQLite (and MySQL) create a
     * named index, which has to be dropped with DROP INDEX.
     */
    public static function dropUnique(string $table, string $name): void
    {
        if (self::isPostgres()) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}");

            return;
        }

        DB::statement("DROP INDEX IF EXISTS {$name}");
    }

    private static function isPostgresOnly(string $normalized): bool
    {
        foreach (['DO $$', 'CREATE TYPE ', 'DROP TYPE ', 'ALTER TYPE ', 'COMMENT ON '] as $statement) {
            if (str_contains($normalized, $statement)) {
                return true;
            }
        }

        return (bool) preg_match('/^ALTER TABLE .*\b(ADD|DROP) CONSTRAINT\b/', $normalized)
            || (bool) preg_match('/^ALTER TABLE .*\bALTER COLUMN\b/', $normalized);
    }

    /**
     * Re-running a migration by hand should not fail on an already-applied column.
     */
    private static function tolerant(callable $run): void
    {
        try {
            $run();
        } catch (Throwable $e) {
            $message = strtolower($e->getMessage());

            if (! str_contains($message, 'duplicate column') && ! str_contains($message, 'no such column')) {
                throw $e;
            }
        }
    }
}
