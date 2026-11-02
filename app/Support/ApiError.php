<?php

namespace App\Support;

use Throwable;

/**
 * What an API error response may say about an unexpected exception.
 *
 * Controllers used to put $e->getMessage() straight into the JSON, which sent
 * SQL, table names and server paths to the phone whatever APP_DEBUG said. The
 * exception is logged here instead, and the detail only goes out in debug.
 */
final class ApiError
{
    public const GENERIC = 'Something went wrong. Please try again.';

    public static function message(Throwable $e): string
    {
        report($e);

        return config('app.debug') ? $e->getMessage() : self::GENERIC;
    }
}
