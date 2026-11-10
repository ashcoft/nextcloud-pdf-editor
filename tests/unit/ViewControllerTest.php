<?php

/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace OCA\PdfEditor\Tests\Unit;

use OCA\PdfEditor\Controller\ViewController;
use OCA\PdfEditor\Service\RecentDocumentsService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for the page controller.
 *
 * @covers \OCA\PdfEditor\Controller\ViewController
 */
#[AllowMockObjectsWithoutExpectations]
class ViewControllerTest extends TestCase
{
    private function controller(array $recent = []): ViewController
    {
        $recentDocuments = $this->createMock(RecentDocumentsService::class);
        $recentDocuments->method('getRecentDocuments')->willReturn($recent);

        return new ViewController(
            'pdf_editor',
            $this->createMock(IRequest::class),
            $recentDocuments,
        );
    }

    public function testIndexRendersTheStartPageWithRecentDocuments(): void
    {
        $recent = [[
            'id' => 1,
            'name' => 'a.pdf',
            'path' => '/a.pdf',
            'extension' => 'pdf',
            'mtime' => 100,
        ]];

        $response = $this->controller($recent)->index();

        $this->assertInstanceOf(TemplateResponse::class, $response);
        $this->assertSame('start', $response->getTemplateName());
        $this->assertSame($recent, $response->getParams()['recentDocuments']);
    }

    public function testViewRendersTheEditorWithTheFileId(): void
    {
        $response = $this->controller()->view('42');

        $this->assertSame('main', $response->getTemplateName());
        $this->assertSame('42', $response->getParams()['fileIds']);
    }

    public function testViewDefaultsToNoFile(): void
    {
        $response = $this->controller()->view();

        $this->assertSame('', $response->getParams()['fileIds']);
    }
}
