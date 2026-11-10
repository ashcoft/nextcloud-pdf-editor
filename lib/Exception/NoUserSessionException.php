<?php

/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace OCA\PdfEditor\Exception;

use RuntimeException;

/**
 * Raised when an endpoint is reached without an authenticated user.
 *
 * A dedicated type rather than a bare RuntimeException, so the controller can
 * answer 401 for a missing session while leaving every other runtime failure on
 * the 500 path.
 */
class NoUserSessionException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No user session');
    }
}
