<?php

/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace OCA\PdfEditor\AppInfo;

use OCA\PdfEditor\Listener\LoadEditor;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;

class Application extends App implements IBootstrap
{
    public const APP_ID = 'pdf_editor';

    /**
     * Canonical MIME type for PDF documents.
     *
     * Nextcloud maps `.pdf` to this type out of the box, so unlike the PSD
     * case no MIME registration repair step is needed.
     */
    public const PDF_MIME = 'application/pdf';

    /**
     * Extensions the editor can open and write back.
     *
     * PdfCraft reads and writes PDF only, so there is exactly one format.
     */
    public const EDITABLE_EXTENSIONS = ['pdf'];

    /**
     * Largest document the editor loads, in bytes.
     *
     * Shared by the API that streams files and the start page, so a listed
     * document is always one the editor will open. PdfCraft's own host open
     * limit is 512 MiB; this stays below it so the app never hands over a file
     * the editor would refuse.
     */
    public const MAX_FILE_SIZE = 200 * 1024 * 1024;

    public function __construct()
    {
        parent::__construct(self::APP_ID);
    }

    #[\Override]
    public function register(IRegistrationContext $context): void
    {
        // Inject the file action / init script on the Files app and on the
        // app's own pages. Public share pages are skipped: the editor and its
        // file API require a logged-in user.
        $context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadEditor::class);
        $context->registerEventListener(
            BeforeTemplateRenderedEvent::class,
            LoadEditor::class
        );
    }

    #[\Override]
    public function boot(IBootContext $context): void
    {
        // Nothing to boot: PDF is a MIME type Nextcloud already knows.
    }

    /**
     * Whether a file name is a PDF document.
     */
    public static function isPdfFile(string $fileName): bool
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        return in_array($extension, self::EDITABLE_EXTENSIONS, true);
    }

    /**
     * Whether a file name is one the editor can open at all.
     */
    public static function isSupportedFile(string $fileName): bool
    {
        return self::isPdfFile($fileName);
    }
}
