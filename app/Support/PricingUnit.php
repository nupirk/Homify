<?php

namespace App\Support;

/**
 * How a pricing tier is billed.
 *
 * The database type is `unit_enum` (PostgreSQL) / a plain string column
 * (SQLite). These constants are the single source of truth for what the admin
 * form offers and what the API accepts.
 *
 * The five current bases describe the quantity the price applies to:
 *   duration - per unit of time (a session/hour of work)
 *   piece    - per item
 *   nos      - per counted number of things (e.g. 3 windows)
 *   unit     - per generic unit (room, square foot, kg...)
 *   complete - per whole job/package
 *
 * Older rows may still carry one of LEGACY values. They are valid to read and
 * to keep when editing, but they are not offered for new tiers.
 */
final class PricingUnit
{
    public const DURATION = 'duration';
    public const PIECE = 'piece';
    public const NOS = 'nos';
    public const UNIT = 'unit';
    public const COMPLETE = 'complete';

    /** Values the admin form offers for new or changed tiers. */
    public const OPTIONS = [
        self::DURATION => 'Duration',
        self::PIECE => 'Piece',
        self::NOS => 'Nos',
        self::UNIT => 'Unit',
        self::COMPLETE => 'Complete',
    ];

    /** Values written before the billing-basis options existed. */
    public const LEGACY = [
        'hour' => 'Hour',
        'sqft' => 'Sq ft',
        'room' => 'Room',
        'kg' => 'Kg',
        'liter' => 'Litre',
        'fixed' => 'Fixed',
    ];

    public const DEFAULT = self::DURATION;

    /**
     * Every accepted value, including legacy ones so an existing tier can be
     * saved again without silently changing how it is billed.
     */
    public static function values(): array
    {
        return array_keys(self::OPTIONS + self::LEGACY);
    }

    /** Human label for any known value. */
    public static function label(?string $value): string
    {
        $value = (string) $value;

        return self::OPTIONS[$value] ?? self::LEGACY[$value] ?? ucfirst($value);
    }

    /**
     * Options to render in a select, with the current value kept selectable
     * even when it is a legacy one.
     */
    public static function optionsFor(?string $current = null): array
    {
        $options = self::OPTIONS;

        if ($current !== null && $current !== '' && !array_key_exists($current, $options)) {
            $options[$current] = self::label($current);
        }

        return $options;
    }

    public static function isValid(?string $value): bool
    {
        return in_array((string) $value, self::values(), true);
    }
}
