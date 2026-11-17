<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a booking cannot be completed because a payment
 * requirement has not been met (e.g. a prepaid order that is not yet
 * paid, or a PAS order declared "online" before the gateway payment
 * has landed). Carries an HTTP status so the controller can respond.
 */
class PaymentRequirementException extends RuntimeException
{
    public const HTTP_STATUS = 422;

    public function __construct(string $message, public int $statusCode = self::HTTP_STATUS)
    {
        parent::__construct($message);
    }
}
