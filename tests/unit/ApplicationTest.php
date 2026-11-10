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
        $this->assertTrue(Application::isSupportedFile('report.pdf'));
        $this->assertFalse(Application::isSupportedFile('movie.mp4'));
    }

    public function testMaxFileSizeIsBelowTheHostOpenLimit(): void
    {
        // PdfCraft refuses a file over 512 MiB before it is copied into the
        // app, so the app's own cap must stay below that.
        $this->assertLessThanOrEqual(512 * 1024 * 1024, Application::MAX_FILE_SIZE);
        $this->assertGreaterThan(0, Application::MAX_FILE_SIZE);
    }
}
