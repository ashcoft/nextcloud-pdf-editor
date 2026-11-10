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
     * Every MIME type that maps to a PDF.
     *
     * A file can carry an alias until Nextcloud re-scans it, so the list is
     * searched and matched as a set. It mirrors `PDF_MIMES` in
     * `src/editor/mimeTypes.ts` so the app agrees with itself.
     */
    public const PDF_MIME_TYPES = [
        'application/pdf',
        'application/x-pdf',
        'application/acrobat',
        'applications/vnd.pdf',
        'text/pdf',
        'text/x-pdf',
    ];

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
     * Whether a MIME type is a PDF.
     *
     * A file can be a PDF without carrying a `.pdf` name (an upload whose name
     * lost its extension, for example), and Nextcloud resolves it to one of
     * these types. The list mirrors `src/editor/mimeTypes.ts` so the file
     * action and the backend agree on what they will open.
     */
    public static function isPdfMime(string $mimeType): bool
    {
        return in_array($mimeType, self::PDF_MIME_TYPES, true);
    }

    /**
     * MIME types that carry no useful format information.
     *
     * A file Nextcloud has not scanned yet, or one whose mapping is stale,
     * reports one of these; only then is the extension allowed to decide.
     */
    public const GENERIC_MIME_TYPES = ['', 'application/octet-stream'];

    /**
     * Whether a file is one the editor can open.
     *
     * The MIME type is authoritative when it identifies a PDF. The extension is
     * consulted only when the MIME carries no format information, so a PNG
     * named `art.pdf` is refused rather than opened as a PDF and then rejected
     * by the content check. This is the same rule the Files action applies, so
     * the action is never offered for a file the API would refuse.
     */
    public static function isSupportedFile(string $fileName, string $mimeType = ''): bool
    {
        if (self::isPdfMime($mimeType)) {
            return true;
        }

        return in_array($mimeType, self::GENERIC_MIME_TYPES, true) && self::isPdfFile($fileName);
    }
}
