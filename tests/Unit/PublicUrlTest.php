<?php

namespace Tests\Unit;

use App\Support\PublicUrl;
use Tests\TestCase;

class PublicUrlTest extends TestCase
{
    public function test_absolute_urls_pass_through_untouched(): void
    {
        $url = 'https://images.unsplash.com/photo-1581578731548-c64695cc6952?w=400';

        $this->assertSame($url, PublicUrl::storage($url));
    }

    public function test_relative_paths_are_mounted_under_storage(): void
    {
        $this->assertSame(
            asset('storage/services/clean.jpg'),
            PublicUrl::storage('services/clean.jpg')
        );
    }

    public function test_mounted_paths_are_not_double_prefixed(): void
    {
        $this->assertSame(
            asset('storage/services/clean.jpg'),
            PublicUrl::storage('/storage/services/clean.jpg')
        );
    }

    public function test_empty_values_return_null(): void
    {
        $this->assertNull(PublicUrl::storage(null));
        $this->assertNull(PublicUrl::storage(''));
        $this->assertNull(PublicUrl::storage('   '));
    }
}
