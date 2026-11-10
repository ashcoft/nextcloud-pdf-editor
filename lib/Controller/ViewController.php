<?php

/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace OCA\PdfEditor\Controller;

use OCA\PdfEditor\AppInfo\Application;
use OCA\PdfEditor\Service\RecentDocumentsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

class ViewController extends Controller
{
    public function __construct(
        string $appName,
        IRequest $request,
        private readonly RecentDocumentsService $recentDocuments,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Start page: recent documents and a link to the Files app.
     *
     * CSRF is not required: this is a plain page navigation (bookmark, app
     * menu) that carries no request token, just like the Files app's own
     * entry point.
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): TemplateResponse
    {
        return new TemplateResponse(Application::APP_ID, 'start', [
            'recentDocuments' => $this->recentDocuments->getRecentDocuments(),
        ]);
    }

    /**
     * Open the editor for a specific file.
     *
     * Accessed from the Files sidebar action and from the start page, so it
     * must work without CSRF validation.
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function view(string $fileIds = ''): TemplateResponse
    {
        return new TemplateResponse(Application::APP_ID, 'main', [
            'fileIds' => $fileIds,
        ]);
    }
}
