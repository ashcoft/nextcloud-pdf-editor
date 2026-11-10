<?php

/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace OCA\PdfEditor\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class AdminSection implements IIconSection
{
    public function __construct(
        private readonly IURLGenerator $url,
        private readonly IL10N $l10n,
    ) {
    }

    #[\Override]
    public function getID(): string
    {
        return 'pdf_editor';
    }

    #[\Override]
    public function getName(): string
    {
        return $this->l10n->t('PDF Editor');
    }

    #[\Override]
    public function getPriority(): int
    {
        return 76;
    }

    #[\Override]
    public function getIcon(): string
    {
        return $this->url->imagePath('pdf_editor', 'app.svg');
    }
}
