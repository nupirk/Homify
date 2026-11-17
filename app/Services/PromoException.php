<?php

namespace App\Services;

use Exception;

/**
 * Thrown when a promo code cannot be applied. Carries the
 * customer-facing reason (message is safe to show verbatim).
 */
class PromoException extends Exception
{
}
