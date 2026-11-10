<?php

/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace OCA\PdfEditor\Tests\Unit;

use OCA\PdfEditor\AppInfo\Application;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for the Application bootstrap and its static helpers.
 *
 * @covers \OCA\PdfEditor\AppInfo\Application
 */
class ApplicationTest extends TestCase
{
    public function testAppId(): void
    {
        $this->assertSame('pdf_editor', Application::APP_ID);
    }

    public function testCanonicalMimeType(): void
    {
        $this->assertSame('application/pdf', Application::PDF_MIME);
    }

    public function testIsPdfFile(): void
    {
        $this->assertTrue(Application::isPdfFile('report.pdf'));
        $this->assertTrue(Application::isPdfFile('REPORT.PDF'));
        $this->assertFalse(Application::isPdfFile('photo.png'));
        $this->assertFalse(Application::isPdfFile('pdf'));
    }

    public function testIsSupportedFileCoversPdf(): void
    {
        $this->assertTrue(Application::isSupportedFile('report.pdf', 'application/pdf'));
        $this->assertFalse(Application::isSupportedFile('movie.mp4', 'video/mp4'));
    }

    public function testIsSupportedFileAcceptsAPdfByMimeWithoutTheExtension(): void
    {
        // A PDF whose name lost its extension still opens: the MIME decides.
        $this->assertTrue(Application::isSupportedFile('report', 'application/pdf'));
        $this->assertTrue(Application::isSupportedFile('report', 'application/x-pdf'));
    }

    public function testIsSupportedFileUsesTheExtensionOnlyWhenTheMimeIsGeneric(): void
    {
        // Nextcloud has not resolved the type yet.
        $this->assertTrue(Application::isSupportedFile('report.pdf', 'application/octet-stream'));
        $this->assertTrue(Application::isSupportedFile('report.pdf', ''));

        // A PNG named `.pdf` is refused: the MIME identifies the real format,
        // so the action and the API both decline it.
        $this->assertFalse(Application::isSupportedFile('art.pdf', 'image/png'));
        $this->assertFalse(Application::isSupportedFile('notes.txt', 'text/plain'));
    }

    public function testMaxFileSizeIsBelowTheHostOpenLimit(): void
    {
        // PdfCraft refuses a file over 512 MiB before it is copied into the
        // app, so the app's own cap must stay below that.
        $this->assertLessThanOrEqual(512 * 1024 * 1024, Application::MAX_FILE_SIZE);
        $this->assertGreaterThan(0, Application::MAX_FILE_SIZE);
    }
}
