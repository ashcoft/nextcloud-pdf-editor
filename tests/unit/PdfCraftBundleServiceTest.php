<?php

/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace OCA\PdfEditor\Tests\Unit;

use OCA\PdfEditor\Service\PdfCraftBundleService;
use OCP\Files\NotFoundException;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for the vendored bundle reader.
 *
 * A temporary bundle directory stands in for the fetched tree, so the tests
 * exercise the real path resolution and refusal logic rather than a mock of it.
 *
 * @covers \OCA\PdfEditor\Service\PdfCraftBundleService
 */
class PdfCraftBundleServiceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . '/pdfcraft-bundle-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0o700, true);
        mkdir($this->root . '/models', 0o700, true);
        file_put_contents($this->root . '/index.html', '<html></html>');
        file_put_contents($this->root . '/app.js', 'console.log(1)');
        file_put_contents($this->root . '/models/text-detection.onnx', 'model');
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
        parent::tearDown();
    }

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            @unlink($path);
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $this->remove($path . '/' . $entry);
        }
        @rmdir($path);
    }

    private function service(): PdfCraftBundleService
    {
        return new PdfCraftBundleService($this->root);
    }

    public function testReportsTheBundleAsInstalled(): void
    {
        $this->assertTrue($this->service()->isInstalled());
    }

    public function testReportsAMissingBundleAsNotInstalled(): void
    {
        $service = new PdfCraftBundleService($this->root . '/missing');

        $this->assertFalse($service->isInstalled());
    }

    public function testResolvesAFileInTheBundle(): void
    {
        $resolved = $this->service()->resolve('app.js');

        $this->assertSame(realpath($this->root . '/app.js'), $resolved);
    }

    public function testResolvesTheBundleRootToIndexHtml(): void
    {
        $resolved = $this->service()->resolve('');

        $this->assertSame(realpath($this->root . '/index.html'), $resolved);
    }

    public function testRefusesAPathThatEscapesTheBundle(): void
    {
        $this->expectException(NotFoundException::class);
        $this->service()->resolve('../etc/passwd');
    }

    public function testRefusesAMissingFile(): void
    {
        $this->expectException(NotFoundException::class);
        $this->service()->resolve('nope.js');
    }

    public function testRefusesADirectoryLikePathWithATrailingSlash(): void
    {
        // `index.html/` must not quietly resolve to `index.html`: it would be
        // served with immutable caching under a directory-like URL.
        $this->expectException(NotFoundException::class);
        $this->service()->resolve('index.html/');
    }

    public function testRefusesADoubledSlash(): void
    {
        $this->expectException(NotFoundException::class);
        $this->service()->resolve('models//text-detection.onnx');
    }

    public function testRefusesASymlinkThatLeavesTheBundle(): void
    {
        $outside = sys_get_temp_dir() . '/pdfcraft-outside-' . bin2hex(random_bytes(6));
        file_put_contents($outside, 'secret');

        if (!@symlink($outside, $this->root . '/link.js')) {
            $this->markTestSkipped('symlinks are not available');
        }

        try {
            $this->expectException(NotFoundException::class);
            $this->service()->resolve('link.js');
        } finally {
            @unlink($outside);
        }
    }

    public function testContentTypes(): void
    {
        $service = $this->service();

        $this->assertSame('text/html; charset=utf-8', $service->contentTypeFor('index.html'));
        $this->assertSame('text/javascript; charset=utf-8', $service->contentTypeFor('app.js'));
        $this->assertSame('application/wasm', $service->contentTypeFor('app_bg.wasm'));
        // The OCR models have no registered type.
        $this->assertSame('application/octet-stream', $service->contentTypeFor('models/x.onnx'));
    }

    public function testHashedAssetsMayBeCachedButTheEntryPointMayNot(): void
    {
        $service = $this->service();

        $this->assertSame('no-cache', $service->cacheControlFor(''));
        $this->assertSame('no-cache', $service->cacheControlFor('index.html'));
        // The models keep their names across releases, so they revalidate.
        $this->assertSame('no-cache', $service->cacheControlFor('models/text-detection.onnx'));
        $this->assertSame(
            'public, max-age=31536000, immutable',
            $service->cacheControlFor('pdfcraft-web-c21a6b0e183b9cb3.js'),
        );
    }
}
