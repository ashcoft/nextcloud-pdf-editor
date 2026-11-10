<?php

/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace OCA\PdfEditor\Tests\Unit;

use OCA\PdfEditor\Config\AppConfig;
use OCA\PdfEditor\Tests\Fixtures\InMemoryAppConfig;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for the app-wide editor settings.
 *
 * @covers \OCA\PdfEditor\Config\AppConfig
 */
#[AllowMockObjectsWithoutExpectations]
class AppConfigTest extends TestCase
{
    private function config(?InMemoryAppConfig $store = null): AppConfig
    {
        return new AppConfig($store ?? new InMemoryAppConfig());
    }

    public function testDefaults(): void
    {
        $config = $this->config();

        $this->assertSame(200 * 1024 * 1024, $config->getMaxFileSize());
    }

    public function testMaxFileSizeRoundTrips(): void
    {
        $config = $this->config();
        $config->setMaxFileSizeMb('128');

        $this->assertSame(128 * 1024 * 1024, $config->getMaxFileSize());
    }

    public function testMaxFileSizeRejectsOutOfRangeValues(): void
    {
        $config = $this->config();

        $config->setMaxFileSizeMb('0');
        $this->assertSame(200 * 1024 * 1024, $config->getMaxFileSize());

        $config->setMaxFileSizeMb('99999');
        $this->assertSame(200 * 1024 * 1024, $config->getMaxFileSize());
    }

    public function testMaxFileSizeRejectsANonNumericValue(): void
    {
        $config = $this->config();
        $config->setMaxFileSizeMb('50abc');

        $this->assertSame(200 * 1024 * 1024, $config->getMaxFileSize());
    }

    public function testCorruptStoredSizeFallsBackToTheDefault(): void
    {
        $store = new InMemoryAppConfig();
        $store->setAppValue('MaxSizeMb', 'chartreuse');

        $this->assertSame(200 * 1024 * 1024, $this->config($store)->getMaxFileSize());
    }
}
