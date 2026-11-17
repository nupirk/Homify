<?php

namespace Tests\Feature;

use App\Helpers\StatusEnumHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * The status constants and the Postgres enums must describe the same world.
 *
 * They did not: `service_request_status_enum` was created without 'enroute'
 * while partner/payment code wrote it, so those writes raised 22P02 and the
 * request 500ed. A constant list is only a convenience unless something checks
 * it against the database, which is what this test does.
 */
class StatusEnumAlignmentTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    /** @return string[] */
    private function enumValues(string $type): array
    {
        return array_map(
            fn ($row) => $row->v,
            DB::select("select unnest(enum_range(NULL::{$type})) as v")
        );
    }

    public function test_order_status_constants_exist_in_the_database_enum(): void
    {
        $missing = array_values(array_diff(
            StatusEnumHelper::getOrderStatusValues(),
            $this->enumValues('order_status_enum')
        ));

        $this->assertSame(
            [],
            $missing,
            'Code can write order statuses the enum rejects: ' . implode(', ', $missing)
        );
    }

    public function test_service_request_status_constants_exist_in_the_database_enum(): void
    {
        $missing = array_values(array_diff(
            StatusEnumHelper::getServiceRequestStatusValues(),
            $this->enumValues('service_request_status_enum')
        ));

        $this->assertSame(
            [],
            $missing,
            'Code can write service-request statuses the enum rejects: ' . implode(', ', $missing)
        );
    }
}
