/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

/**
 * MIME types and extensions the editor can open.
 *
 * PdfCraft reads and writes PDF only. Nextcloud resolves `.pdf` to
 * `application/pdf`, but a file that has not been re-scanned yet can still
 * carry one of the older aliases, so both are recognised.
 */
export const CANONICAL_PDF_MIME = 'application/pdf'

export const PDF_MIMES = [
  CANONICAL_PDF_MIME,
  'application/x-pdf',
  'application/acrobat',
  'applications/vnd.pdf',
  'text/pdf',
  'text/x-pdf',
] as const

export const PDF_EXTENSIONS = ['pdf']

export function isPdfMime(mime: string): boolean {
  return (PDF_MIMES as readonly string[]).includes(mime)
}

export function extensionOf(name: string): string {
  const dot = name.lastIndexOf('.')
  return dot >= 0 ? name.slice(dot + 1).toLowerCase() : ''
}

/**
 * Whether a file name is a PDF document.
 */
export function isPdfExtension(name: string): boolean {
  return PDF_EXTENSIONS.includes(extensionOf(name))
}

/**
 * Whether a file name is one the editor can open.
 */
export function isSupportedExtension(name: string): boolean {
  return isPdfExtension(name)
}
