/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

import { toDataUrl } from '../../src/utils/dataUrl'

describe('toDataUrl', () => {
  /** The bytes of an ASCII string, without relying on TextEncoder (absent in jsdom). */
  function ascii(text: string): Uint8Array {
    return new Uint8Array(Array.from(text, (character) => character.charCodeAt(0)))
  }

  it('encodes bytes as a PDF data URL', () => {
    const bytes = ascii('%PDF-1.4')

    expect(toDataUrl(bytes)).toBe(`data:application/pdf;base64,${window.btoa('%PDF-1.4')}`)
  })

  it('round-trips a payload larger than the chunk size', () => {
    // Over 0x8000 bytes, so the chunked conversion is exercised rather than the
    // single-call path that would overflow the argument list on a real file.
    const bytes = new Uint8Array(0x8000 + 100).fill(0x41)

    const dataUrl = toDataUrl(bytes)
    const base64 = dataUrl.slice('data:application/pdf;base64,'.length)
    const decoded = window.atob(base64)

    expect(decoded.length).toBe(bytes.length)
    expect(decoded.charCodeAt(0)).toBe(0x41)
    expect(decoded.charCodeAt(decoded.length - 1)).toBe(0x41)
  })

  it('accepts a custom mime type', () => {
    expect(toDataUrl(new Uint8Array([1]), 'application/octet-stream')).toMatch(
      /^data:application\/octet-stream;base64,/,
    )
  })
})
