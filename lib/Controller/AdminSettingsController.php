<?php

/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace OCA\PdfEditor\Controller;

use OCA\PdfEditor\AppInfo\Application;
use OCA\PdfEditor\Config\AppConfig;
use OCA\PdfEditor\Settings\AdminSettings;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

class AdminSettingsController extends Controller
{
    public function __construct(
        string $appName,
        IRequest $request,
        private readonly AppConfig $config,
    ) {
        parent::__construct($appName, $request);
    }

    #[AuthorizedAdminSetting(settings: AdminSettings::class)]
    #[NoCSRFRequired]
    public function index(): TemplateResponse
    {
        Util::addScript(Application::APP_ID, 'adminSettings');
        Util::addStyle(Application::APP_ID, 'settings');

        return new TemplateResponse(
            $this->appName,
            'adminSettings',
            $this->currentSettings(),
            TemplateResponse::RENDER_AS_BLANK,
        );
    }

    /**
     * Persist the posted settings.
     *
     * A missing parameter keeps the stored value instead of falling back to the
     * default: the form always posts every field, but a partial request must
     * not silently reset the ones it omits.
     */
    #[AuthorizedAdminSetting(settings: AdminSettings::class)]
    public function settings(): DataResponse
    {
        $this->config->setMaxFileSizeMb($this->stringParam(
            'maxSizeMb',
            (string) $this->maxSizeMb(),
        ));

        return new DataResponse($this->currentSettings());
    }

    /**
     * @return array{maxSizeMb: int}
     */
    private function currentSettings(): array
    {
        return [
            'maxSizeMb' => $this->maxSizeMb(),
        ];
    }

    private function maxSizeMb(): int
    {
        return intdiv($this->config->getMaxFileSize(), 1024 * 1024);
    }

    private function stringParam(string $name, string $default): string
    {
        $value = $this->request->getParam($name, $default);

        return is_string($value) ? $value : $default;
    }
}
