/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

import {
  extensionOf,
  isPdfExtension,
  isPdfMime,
  isSupportedExtension,
} from '../../src/editor/mimeTypes'

describe('mimeTypes', () => {
  it('recognises PDF mime types', () => {
    expect(isPdfMime('application/pdf')).toBe(true)
    expect(isPdfMime('application/x-pdf')).toBe(true)
    expect(isPdfMime('text/pdf')).toBe(true)
    expect(isPdfMime('image/png')).toBe(false)
    expect(isPdfMime('application/octet-stream')).toBe(false)
  })

  it('extracts a lower-case extension', () => {
    expect(extensionOf('Report.PDF')).toBe('pdf')
    expect(extensionOf('no-extension')).toBe('')
  })

  it('recognises the pdf extension', () => {
    expect(isPdfExtension('a.pdf')).toBe(true)
    expect(isPdfExtension('a.PDF')).toBe(true)
    expect(isPdfExtension('a.txt')).toBe(false)
    expect(isSupportedExtension('a.pdf')).toBe(true)
    expect(isSupportedExtension('a.png')).toBe(false)
  })
})
