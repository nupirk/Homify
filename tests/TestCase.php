<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests insert users with hand-picked ids, so PostgreSQL needs its sequence
     * moved past them before the next auto-increment insert.
     *
     * SQLite has no sequences: an INTEGER PRIMARY KEY takes MAX(rowid) + 1, so
     * there is nothing to reset and the statement would fail as an unknown
     * function.
     */
    protected function syncUserIdSequence(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            "SELECT setval('users_user_id_seq', GREATEST((SELECT COALESCE(MAX(user_id), 1) FROM users), 1))"
        );
    }
}
