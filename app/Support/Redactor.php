<?php

namespace App\Support;

/**
 * Helpers for keeping secrets and PII out of logs.
 *
 * Log files are frequently shipped to third-party aggregators, read by
 * support staff, and retained far longer than the data they describe — so
 * anything that lands there should be useless to an attacker.
 */
class Redactor
{
    /**
     * Mask a phone number, keeping only enough digits to identify it in
     * support conversations (first 3 + last 2). Never log a full MSISDN.
     */
    public static function phone(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '(none)';
        }

        $digits = preg_replace('/[^0-9]/', '', $phone);

        if ($digits === '' || strlen($digits) <= 5) {
            return str_repeat('*', strlen((string) $phone));
        }

        $head = substr($digits, 0, 3);
        $tail = substr($digits, -2);
        $mask = str_repeat('*', max(0, strlen($digits) - 5));

        return $head . $mask . $tail;
    }

    /**
     * Mask an email address for logs: keep the first character of the local
     * part and the whole domain so support can still triage delivery issues.
     */
    public static function email(?string $email): string
    {
        if ($email === null || $email === '' || !str_contains($email, '@')) {
            return '(none)';
        }

        [$local, $domain] = explode('@', $email, 2);
        $head = $local === '' ? '' : substr($local, 0, 1);

        return $head . str_repeat('*', max(0, strlen($local) - 1)) . '@' . $domain;
    }

    /**
     * Replace the values of the given keys with a fixed placeholder.
     * Handles nested arrays one level deep so shapes like
     * ['bank_transfer_details' => ['account_number' => ...]] are covered.
     */
    public static function redactKeys(array $data, array $keys): array
    {
        $keys = array_map('strval', $keys);

        foreach ($data as $key => $value) {
            if (in_array((string) $key, $keys, true)) {
                $data[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::redactKeys($value, $keys);
            }
        }

        return $data;
    }

    /**
     * Keys that must never be written to a log line, in any request shape.
     */
    public static function sensitiveKeys(): array
    {
        return [
            'otp',
            'otp_code',
            'password',
            'password_confirmation',
            'token',
            'access_token',
            'refresh_token',
            'authorization',
            'secret',
            'signature',
            'account_number',
            'account_name',
            'esewa_number',
            'khalti_number',
            'bank_name',
            'branch',
            'bank_code',
            'card_number',
            'cvv',
        ];
    }
}
