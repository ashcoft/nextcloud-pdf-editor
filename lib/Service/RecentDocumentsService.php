<?php

/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace OCA\PdfEditor\Service;

use OCA\PdfEditor\AppInfo\Application;
use OCP\Files\File;
use OCP\Files\Folder;
use Psr\Log\LoggerInterface;

/**
 * Lists recently modified PDFs for the start page.
 *
 * The list is built from the user's own folder, so it can only ever contain
 * files that user can already see.
 */
class RecentDocumentsService
{
    /** How many documents the start page shows. */
    private const LIMIT = 24;

    public function __construct(
        private readonly FileService $fileService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return list<array{id: int, name: string, path: string, extension: string, mtime: int}>
     */
    public function getRecentDocuments(): array
    {
        try {
            $folder = $this->fileService->getUserFolder();
        } catch (\Throwable) {
            return [];
        }

        $documents = $this->collect($folder);
        if ($documents === []) {
            return [];
        }

        usort(
            $documents,
            static fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime'],
        );

        return array_slice($documents, 0, self::LIMIT);
    }

    /**
     * @return list<array{id: int, name: string, path: string, extension: string, mtime: int}>
     */
    private function collect(Folder $folder): array
    {
        $results = [];
        $seen = [];

        // Search the PDF MIME types directly rather than every `application/*`
        // file: on a large tree the broad search loads and walks many unrelated
        // files just to keep 24. The alias types are included because a file
        // can carry one until it is re-scanned.
        foreach (Application::PDF_MIME_TYPES as $mimeType) {
            try {
                $nodes = $folder->searchByMime($mimeType);
            } catch (\Throwable $e) {
                $this->logger->warning('PDF Editor: could not list documents', [
                    'mimeType' => $mimeType,
                    'exception' => $e,
                ]);
                continue;
            }

            foreach ($nodes as $node) {
                if (!$node instanceof File) {
                    continue;
                }

                $id = (int) $node->getId();
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;

                $entry = $this->describe($node, $folder);
                if ($entry !== null) {
                    $results[] = $entry;
                }
            }
        }

        return $results;
    }

    /**
     * @return array{id: int, name: string, path: string, extension: string, mtime: int}|null
     */
    private function describe(File $file, Folder $root): ?array
    {
        if (!Application::isSupportedFile($file->getName(), $file->getMimeType())) {
            return null;
        }

        // A file the editor will refuse to open must not be listed, or the
        // card would lead straight to a 413.
        if ($file->getSize() > $this->fileService->getMaxFileSize()) {
            return null;
        }

        $relative = $root->getRelativePath($file->getPath());
        if ($relative === null) {
            return null;
        }

        return [
            'id' => (int) $file->getId(),
            'name' => $file->getName(),
            'path' => '/' . ltrim($relative, '/'),
            'extension' => strtolower(pathinfo($file->getName(), PATHINFO_EXTENSION)),
            'mtime' => $file->getMTime(),
        ];
    }
}
