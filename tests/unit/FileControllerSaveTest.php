<?php

/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace OCA\PdfEditor\Tests\Unit;

use OCA\PdfEditor\Config\AppConfig;
use OCA\PdfEditor\Controller\FileController;
use OCA\PdfEditor\Service\FileService;
use OCA\PdfEditor\Tests\Fixtures\InMemoryAppConfig;
use OCP\AppFramework\Http;
use OCP\Files\File;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Test suite for the save endpoint of the FileController.
 *
 * The write path is what protects the original document, so it is exercised
 * for real here: the stale-etag refusal, the content validation, the size cap
 * and the lock being released after both a success and a failure.
 *
 * @covers \OCA\PdfEditor\Controller\FileController
 */
#[AllowMockObjectsWithoutExpectations]
class FileControllerSaveTest extends TestCase
{
    private const PDF = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n";

    /** A valid data URL holding a minimal PDF. */
    private function pdfDataUrl(string $body = self::PDF): string
    {
        return 'data:application/pdf;base64,' . base64_encode($body);
    }

    /**
     * Build a controller whose file lookup resolves to $file.
     *
     * @param array<string, mixed> $params Request parameters.
     */
    private function controller(
        File $file,
        array $params = [],
        ?File $lockedFile = null,
    ): FileController {
        $fileService = $this->createMock(FileService::class);
        // The save resolves the file twice: once to validate, and again inside
        // the lock. Both must resolve to the same node unless a test supplies a
        // different one for the locked lookup.
        $fileService->method('resolveFile')->willReturn($lockedFile ?? $file);
        $fileService->method('getMaxFileSize')->willReturn(50 * 1024 * 1024);

        $request = $this->createMock(IRequest::class);
        $request->method('getParam')->willReturnCallback(
            static fn (string $name, $default = null) => $params[$name] ?? $default,
        );

        return new FileController(
            'pdf_editor',
            $request,
            $fileService,
            new AppConfig(new InMemoryAppConfig()),
            $this->createMock(IURLGenerator::class),
            $this->createMock(LoggerInterface::class),
        );
    }

    /**
     * A file mock that records whether it was locked, unlocked and written.
     *
     * Every stub is set in one call because PHPUnit keeps the first stub for a
     * method.
     *
     * @param array{locked?: bool, unlocked?: bool, written?: ?string} $state
     */
    private function file(
        string $etag = 'etag-a',
        bool $updateable = true,
        int $size = 4096,
        array &$state = [],
        ?\Throwable $lockThrows = null,
    ): File {
        $state = ['locked' => false, 'unlocked' => false, 'written' => null];

        $file = $this->createMock(File::class);
        $file->method('getId')->willReturn(42);
        $file->method('getName')->willReturn('report.pdf');
        $file->method('getMimeType')->willReturn('application/pdf');
        $file->method('getEtag')->willReturn($etag);
        $file->method('getSize')->willReturn($size);
        $file->method('getMTime')->willReturn(1234);
        $file->method('isReadable')->willReturn(true);
        $file->method('isUpdateable')->willReturn($updateable);
        $file->method('lock')->willReturnCallback(function (int $type) use (&$state, $lockThrows): void {
            if ($lockThrows !== null) {
                throw $lockThrows;
            }
            $state['locked'] = true;
        });
        $file->method('unlock')->willReturnCallback(function (int $type) use (&$state): void {
            $state['unlocked'] = true;
        });
        $file->method('putContent')->willReturnCallback(function (string $content) use (&$state): void {
            $state['written'] = $content;
        });

        return $file;
    }

    public function testSavesAValidDocument(): void
    {
        $state = [];
        $file = $this->file('etag-a', state: $state);
        $response = $this->controller($file, ['image' => $this->pdfDataUrl(), 'etag' => 'etag-a'])->save(42);

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $this->assertSame(self::PDF, $state['written']);
        $this->assertTrue($state['locked']);
        $this->assertTrue($state['unlocked'], 'the lock is released after a save');
        $this->assertSame('etag-a', $response->getData()['etag']);
    }

    public function testRefusesAStaleEtag(): void
    {
        $state = [];
        // The file's etag has moved on since the client loaded it.
        $file = $this->file('etag-b', state: $state);
        $response = $this->controller($file, ['image' => $this->pdfDataUrl(), 'etag' => 'etag-a'])->save(42);

        $this->assertSame(Http::STATUS_CONFLICT, $response->getStatus());
        $this->assertNull($state['written'], 'a stale save must not write');
        $this->assertTrue($state['unlocked'], 'the lock is released after a refusal');
    }

    public function testRefusesAReadOnlyFile(): void
    {
        $state = [];
        $file = $this->file(updateable: false, state: $state);
        $response = $this->controller($file, ['image' => $this->pdfDataUrl(), 'etag' => 'etag-a'])->save(42);

        $this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
        $this->assertNull($state['written']);
    }

    public function testRejectsAPayloadThatIsNotAPdf(): void
    {
        $state = [];
        $file = $this->file(state: $state);
        $response = $this->controller($file, [
            'image' => 'data:application/pdf;base64,' . base64_encode('not a pdf at all'),
            'etag' => 'etag-a',
        ])->save(42);

        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
        $this->assertNull($state['written']);
    }

    public function testRejectsATruncatedPdf(): void
    {
        $state = [];
        $file = $this->file(state: $state);
        // Header present but no `%%EOF`: a document that was cut short.
        $response = $this->controller($file, [
            'image' => $this->pdfDataUrl('%PDF-1.4 but no end marker'),
            'etag' => 'etag-a',
        ])->save(42);

        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
        $this->assertNull($state['written']);
    }

    public function testRejectsAMissingBody(): void
    {
        $state = [];
        $file = $this->file(state: $state);
        $response = $this->controller($file, ['etag' => 'etag-a'])->save(42);

        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
        $this->assertNull($state['written']);
    }

    public function testRejectsAMissingVersion(): void
    {
        $state = [];
        $file = $this->file(state: $state);
        $response = $this->controller($file, ['image' => $this->pdfDataUrl()])->save(42);

        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
        $this->assertNull($state['written']);
    }

    public function testContentDispositionStripsControlCharacters(): void
    {
        // The name is user-controlled, so a CR/LF in it must not reach the
        // header. The helper is private; reflection drives it directly rather
        // than round-tripping through a download, which would need the server
        // container for the response headers.
        $controller = $this->controller($this->file());
        $method = new \ReflectionMethod(FileController::class, 'contentDisposition');
        $header = $method->invoke($controller, "evil\r\nX-Injected: 1.pdf");

        $this->assertStringNotContainsString("\r", $header);
        $this->assertStringNotContainsString("\n", $header);
        $this->assertStringContainsString('filename=', $header);
    }
}
