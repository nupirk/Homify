<?php

namespace App\Support;

/**
 * One canonical form for email addresses.
 *
 * PostgreSQL compares text case-sensitively, so without this "John@X.com" and
 * "john@x.com" are two different accounts: login fails for the address the
 * user typed, and password recovery reports success while sending nothing.
 * Every write and every lookup goes through normalize().
 */
class EmailAddress
{
    public static function normalize(?string $email): string
    {
        return strtolower(trim((string) $email));
    }
}
