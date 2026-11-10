<?php

/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace OCA\PdfEditor\Settings;

use OCA\PdfEditor\Config\AppConfig;
use OCA\PdfEditor\Controller\AdminSettingsController;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\IDelegatedSettings;

/**
 * Delegated admin settings: the settings form is the app's own controller,
 * which is also what the admin settings route serves.
 *
 * Declaring it delegated (rather than building a second form here) keeps the
 * two entry points to the same settings identical, and lets the app's own
 * AuthorizedAdminSetting attribute govern who may post changes.
 */
class AdminSettings implements IDelegatedSettings
{
    public function __construct(
        private readonly AdminSettingsController $controller,
    ) {
    }

    #[\Override]
    public function getName(): ?string
    {
        return null;
    }

    #[\Override]
    public function getAuthorizedAppConfig(): array
    {
        // Only the keys this settings form writes are delegated. A wildcard
        // would let a delegated admin change every future app-config key.
        // Nextcloud matches these with `preg_match`, which needs delimiters.
        return [
            AppConfig::APP_CONFIG_KEY => [
                '/^MaxSizeMb$/',
            ],
        ];
    }

    #[\Override]
    public function getForm(): TemplateResponse
    {
        return $this->controller->index();
    }

    #[\Override]
    public function getSection(): string
    {
        return 'pdf_editor';
    }

    #[\Override]
    public function getPriority(): int
    {
        return 76;
    }
}
