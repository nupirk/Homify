<?php

namespace App\Services;

use Exception;

/**
 * Thrown when an invite/referral code cannot be applied. Message is
 * customer-facing and safe to show verbatim.
 */
class ReferralException extends Exception
{
}
