<?php

/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace OCA\PdfEditor\Config;

use OCP\AppFramework\Services\IAppConfig;

/**
 * App-wide editor settings, read and written from the admin settings page.
 *
 * Only settings the editor actually honours are kept here: the largest file it
 * will load.
 */
class AppConfig
{
    /** The app id, used when declaring the delegated settings scope. */
    public const APP_CONFIG_KEY = 'pdf_editor';

    public const DEFAULT_MAX_SIZE_MB = 200;

    /** Bounds for the admin-configurable size limit. */
    public const MIN_MAX_SIZE_MB = 1;
    public const MAX_MAX_SIZE_MB = 512;

    private const KEY_MAX_SIZE_MB = 'MaxSizeMb';

    public function __construct(
        private readonly IAppConfig $config,
    ) {
    }

    /**
     * The largest file the editor will load, in bytes.
     */
    public function getMaxFileSize(): int
    {
        $raw = trim($this->config->getAppValueString(
            self::KEY_MAX_SIZE_MB,
            (string) self::DEFAULT_MAX_SIZE_MB,
        ));

        // Reject a value that is not wholly numeric before casting: a cast
        // would accept "50abc" as 50 and silently apply a limit the admin did
        // not set.
        if (!ctype_digit($raw)) {
            return self::DEFAULT_MAX_SIZE_MB * 1024 * 1024;
        }

        $value = (int) $raw;
        if ($value < self::MIN_MAX_SIZE_MB || $value > self::MAX_MAX_SIZE_MB) {
            return self::DEFAULT_MAX_SIZE_MB * 1024 * 1024;
        }

        return $value * 1024 * 1024;
    }

    public function setMaxFileSizeMb(string $megabytes): void
    {
        // Reject a value that is not wholly numeric: a cast would accept
        // "50abc" as 50 and silently configure a limit the admin did not ask
        // for.
        $value = ctype_digit(trim($megabytes)) ? (int) $megabytes : 0;
        if ($value < self::MIN_MAX_SIZE_MB || $value > self::MAX_MAX_SIZE_MB) {
            $value = self::DEFAULT_MAX_SIZE_MB;
        }

        $this->config->setAppValueString(self::KEY_MAX_SIZE_MB, (string) $value);
    }
}
