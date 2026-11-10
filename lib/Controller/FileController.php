<?php

/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace OCA\PdfEditor\Controller;

use OCA\PdfEditor\Config\AppConfig;
use OCA\PdfEditor\Exception\NoUserSessionException;
use OCA\PdfEditor\Service\FileService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\StreamResponse;
use OCP\Files\File;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use Psr\Log\LoggerInterface;

class FileController extends Controller
{
    private const ERR_FILE_NOT_FOUND = 'File not found';
    private const ERR_ACCESS_DENIED = 'Access denied';
    private const ERR_NOT_A_FILE = 'Not a file';
    private const ERR_INTERNAL_SERVER_ERROR = 'Internal server error';

    public function __construct(
        string $appName,
        IRequest $request,
        private readonly FileService $fileService,
        private readonly AppConfig $appConfig,
        private readonly IURLGenerator $urlGenerator,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Validate that the file is one the editor can open, and that it is not
     * larger than the configured limit.
     *
     * @return DataResponse|null Error response if validation fails, null otherwise.
     */
    private function validateFile(File $file, int $fileId): ?DataResponse
    {
        if (!$file->isReadable()) {
            return new DataResponse(['error' => self::ERR_ACCESS_DENIED], Http::STATUS_FORBIDDEN);
        }

        // The MIME type decides when it identifies a PDF, and the extension is
        // the fallback for a file Nextcloud has not resolved. The same rule the
        // Files action uses, so the two never disagree about what opens.
        if (!\OCA\PdfEditor\AppInfo\Application::isSupportedFile($file->getName(), $file->getMimeType())) {
            $this->logger->info('PDF Editor: refusing unsupported file type', [
                'fileId' => $fileId,
                'name' => $file->getName(),
            ]);
            return new DataResponse(
                ['error' => 'Unsupported file type.'],
                Http::STATUS_UNSUPPORTED_MEDIA_TYPE,
            );
        }

        if ($file->getSize() > $this->fileService->getMaxFileSize()) {
            $this->logger->info('PDF Editor: refusing oversized file', [
                'fileId' => $fileId,
                'size' => $file->getSize(),
            ]);
            return new DataResponse(
                ['error' => 'File too large.'],
                Http::STATUS_REQUEST_ENTITY_TOO_LARGE,
            );
        }

        return null;
    }

    /**
     * Map a thrown exception to the response the client should see.
     */
    private function handleError(\Throwable $e, int $fileId, ?string $logMessage = null): DataResponse
    {
        $logMessage ??= $e->getMessage();

        if ($e instanceof NotFoundException) {
            $this->logger->warning('PDF Editor: file not found', ['fileId' => $fileId]);
            return new DataResponse(['error' => self::ERR_FILE_NOT_FOUND], Http::STATUS_NOT_FOUND);
        }

        if ($e instanceof NotPermittedException) {
            $this->logger->warning('PDF Editor: access denied', ['fileId' => $fileId]);
            return new DataResponse(['error' => self::ERR_ACCESS_DENIED], Http::STATUS_FORBIDDEN);
        }

        if ($e instanceof NoUserSessionException) {
            $this->logger->warning('PDF Editor: unauthorized access attempt');
            return new DataResponse(['error' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
        }

        if ($e instanceof \InvalidArgumentException) {
            return new DataResponse(['error' => self::ERR_NOT_A_FILE], Http::STATUS_BAD_REQUEST);
        }

        $this->logger->error('PDF Editor: error handling file', [
            'fileId' => $fileId,
            'error' => $logMessage,
        ]);
        return new DataResponse(
            ['error' => self::ERR_INTERNAL_SERVER_ERROR],
            Http::STATUS_INTERNAL_SERVER_ERROR,
        );
    }

    /**
     * Load a document by id and return its metadata with a download URL.
     *
     * The download URL is session-authenticated, so the client never needs a
     * second credential to fetch the bytes.
     */
    #[NoAdminRequired]
    public function load(int $fileId): DataResponse
    {
        try {
            $file = $this->fileService->resolveFile($fileId);

            $errorResponse = $this->validateFile($file, $fileId);
            if ($errorResponse !== null) {
                return $errorResponse;
            }

            $meta = $this->fileService->describe($file);
            $meta['url'] = $this->urlGenerator->linkToRouteAbsolute(
                $this->appName . '.file.download',
                ['fileId' => $fileId],
            );
            $meta['contentType'] = 'application/pdf';
            $meta['maxFileSize'] = $this->appConfig->getMaxFileSize();

            $this->logger->info('PDF Editor: loading file', [
                'fileId' => $fileId,
                'name' => $file->getName(),
                'size' => $file->getSize(),
            ]);

            return new DataResponse($meta);
        } catch (\Throwable $e) {
            return $this->handleError($e, $fileId);
        }
    }

    /**
     * Stream document bytes to the browser.
     *
     * Streamed rather than buffered so a large PDF does not have to be held in
     * PHP memory to reach the client.
     */
    #[NoAdminRequired]
    public function download(int $fileId): DataResponse|StreamResponse
    {
        try {
            $file = $this->fileService->resolveFile($fileId);

            // Same checks as load(), so the download cannot hand back a file
            // the client was told it could not open.
            $errorResponse = $this->validateFile($file, $fileId);
            if ($errorResponse !== null) {
                return $errorResponse;
            }

            $stream = $file->fopen('r');
            if ($stream === false) {
                return new DataResponse(
                    ['error' => 'Could not open file'],
                    Http::STATUS_INTERNAL_SERVER_ERROR,
                );
            }

            $response = new StreamResponse($stream);
            $response->addHeader('Content-Type', 'application/pdf');
            $response->addHeader('Content-Length', (string) $file->getSize());
            $response->addHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->addHeader('Content-Disposition', $this->contentDisposition($file->getName()));

            return $response;
        } catch (\Throwable $e) {
            return $this->handleError($e, $fileId);
        }
    }

    /**
     * Get file metadata without the download URL.
     */
    #[NoAdminRequired]
    public function getFile(int $fileId): DataResponse
    {
        try {
            $file = $this->fileService->resolveFile($fileId);

            return new DataResponse($this->fileService->describe($file));
        } catch (\Throwable $e) {
            return $this->handleError($e, $fileId);
        }
    }

    /**
     * Write edited document bytes back to a file.
     *
     * The client posts a base64 data URL of the saved PDF. The etag it loaded
     * is required and checked, so a save based on a stale copy is refused
     * instead of overwriting someone else's edit.
     */
    #[NoAdminRequired]
    public function save(int $fileId): DataResponse
    {
        try {
            $file = $this->fileService->resolveFile($fileId);

            $errorResponse = $this->validateFile($file, $fileId);
            if ($errorResponse !== null) {
                return $errorResponse;
            }

            if (!$file->isUpdateable()) {
                return new DataResponse(['error' => self::ERR_ACCESS_DENIED], Http::STATUS_FORBIDDEN);
            }

            $dataUrl = $this->request->getParam('image');
            if (!is_string($dataUrl) || $dataUrl === '') {
                return new DataResponse(['error' => 'Missing file data'], Http::STATUS_BAD_REQUEST);
            }

            $binary = $this->decodePdfDataUrl($dataUrl);
            if ($binary === null) {
                return new DataResponse(['error' => 'Invalid PDF data'], Http::STATUS_BAD_REQUEST);
            }

            if (strlen($binary) > $this->fileService->getMaxFileSize()) {
                return new DataResponse(
                    ['error' => 'File too large.'],
                    Http::STATUS_REQUEST_ENTITY_TOO_LARGE,
                );
            }

            $etag = $this->request->getParam('etag');
            if (!is_string($etag) || $etag === '') {
                return new DataResponse(['error' => 'Missing file version'], Http::STATUS_BAD_REQUEST);
            }

            return $this->writeWithLock($fileId, $binary, $etag);
        } catch (\Throwable $e) {
            return $this->handleError($e, $fileId);
        }
    }

    /**
     * Write the new content while holding an exclusive lock on the file.
     *
     * The lock goes through `Node::lock()`, which resolves the storage's own
     * lock key (`files/<md5(storageId::path)>`), the same key the Files app and
     * every other writer use. A hand-built key would not match, so a
     * concurrent write could slip through between the etag check and the write
     * despite the advertised lock.
     *
     * The version is re-checked *inside* the lock, so two saves based on the
     * same etag cannot both pass and the later one silently discard the first
     * edit: the second sees the new etag and is refused with 409.
     */
    private function writeWithLock(int $fileId, string $binary, string $etag): DataResponse
    {
        $file = null;
        $locked = false;

        try {
            // Re-resolve immediately before locking, and lock the node that is
            // resolved, so a move between the caller's resolve and here cannot
            // lock one path while the write lands on another.
            $file = $this->fileService->resolveFile($fileId);
            $file->lock(ILockingProvider::LOCK_EXCLUSIVE);
            $locked = true;

            // The lock is advisory, so the etag is read again now that it is
            // held rather than trusting the value from before the lock.
            if ($etag !== $file->getEtag()) {
                $this->logger->info('PDF Editor: refusing save, file changed', ['fileId' => $fileId]);
                return new DataResponse(
                    ['error' => 'The file was changed by someone else. Reload it before saving.'],
                    Http::STATUS_CONFLICT,
                );
            }

            $file->putContent($binary);
            clearstatcache();

            $this->logger->info('PDF Editor: saved file', [
                'fileId' => $fileId,
                'size' => $file->getSize(),
            ]);

            return new DataResponse([
                'etag' => $file->getEtag(),
                'size' => $file->getSize(),
                'mtime' => $file->getMTime(),
            ]);
        } catch (LockedException) {
            return new DataResponse(['error' => 'The file is locked.'], Http::STATUS_CONFLICT);
        } catch (NotPermittedException) {
            return new DataResponse(['error' => self::ERR_ACCESS_DENIED], Http::STATUS_FORBIDDEN);
        } finally {
            if ($locked && $file !== null) {
                try {
                    $file->unlock(ILockingProvider::LOCK_EXCLUSIVE);
                } catch (\Throwable $e) {
                    $this->logger->warning('PDF Editor: failed to release save lock', [
                        'fileId' => $fileId,
                        'exception' => $e,
                    ]);
                }
            }
        }
    }

    /**
     * Build a `Content-Disposition` value for a file name.
     *
     * The name is user-controlled, so control characters (notably CR and LF,
     * which would let a crafted name inject a header) are dropped, and the
     * value is emitted as an RFC 5987 `filename*` with a quoted ASCII fallback,
     * so a name outside ASCII still round-trips.
     */
    private function contentDisposition(string $fileName): string
    {
        // Strip anything outside printable ASCII for the quoted fallback, and
        // percent-encode the full name for `filename*`.
        $ascii = preg_replace('/[^\x20-\x7e]/', '_', $fileName) ?? 'document.pdf';
        $ascii = str_replace(['"', '\\'], '_', $ascii);

        return sprintf(
            "attachment; filename=\"%s\"; filename*=UTF-8''%s",
            $ascii,
            rawurlencode($fileName),
        );
    }

    /**
     * Decode a base64 PDF data URL into raw bytes.
     *
     * The declared data-URL type is only a hint; the bytes are what is checked.
     * A PDF must start with the `%PDF-` header and carry an `%%EOF` marker, so
     * a truncated upload or one that is not a PDF at all cannot replace a
     * readable file with a broken one.
     *
     * @return string|null Raw bytes, or null when the payload is not a PDF.
     */
    private function decodePdfDataUrl(string $dataUrl): ?string
    {
        if (!preg_match('#^data:application/pdf;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            return null;
        }

        // Bound the encoded payload before decoding: base64 expands by 4/3, so
        // a huge string would be allocated in full before any size check ran.
        if (strlen($m[1]) > (int) ceil($this->fileService->getMaxFileSize() * 4 / 3) + 8) {
            return null;
        }

        $binary = base64_decode($m[1], true);
        if ($binary === false || $binary === '') {
            return null;
        }

        return $this->validatePdfBytes($binary) ? $binary : null;
    }

    /**
     * Whether the bytes look like a complete PDF.
     *
     * The header must be present at the very start and the end-of-file marker
     * must appear in the trailing bytes: a PDF reader tolerates junk before
     * the header, but an editor's own output does not need it, and requiring
     * the header at offset zero refuses a file that merely contains PDF bytes
     * later on.
     */
    private function validatePdfBytes(string $binary): bool
    {
        if (!str_starts_with($binary, '%PDF-')) {
            return false;
        }

        // The trailer may be followed by a little whitespace, so the marker is
        // searched for in the last kilobyte rather than at the exact end.
        $tail = substr($binary, -1024);

        return str_contains($tail, '%%EOF');
    }
}
