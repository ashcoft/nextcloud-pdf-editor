/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

/**
 * A document listed on the start page.
 *
 * Produced by `RecentDocumentsService` on the server and embedded into the page
 * as JSON, so the shape must stay in sync with that service.
 */
export interface RecentDocument {
  id: number
  name: string
  /** Path relative to the user's files root, e.g. `/Reports/q3.pdf`. */
  path: string
  /** Lower-case file extension. */
  extension: string
  /** Modification time as a Unix timestamp in seconds. */
  mtime: number
}
