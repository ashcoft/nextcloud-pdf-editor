/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

/**
 * Read bytes as a base64 data URL.
 *
 * The save endpoint takes a data URL rather than a multipart upload. A large
 * PDF would overflow the argument list of `String.fromCharCode`, so the bytes
 * are converted in chunks.
 */
export function toDataUrl(bytes: Uint8Array, mimeType = 'application/pdf'): string {
  let binary = ''
  const chunkSize = 0x8000
  for (let offset = 0; offset < bytes.length; offset += chunkSize) {
    binary += String.fromCharCode(...bytes.subarray(offset, offset + chunkSize))
  }

  return `data:${mimeType};base64,${window.btoa(binary)}`
}
