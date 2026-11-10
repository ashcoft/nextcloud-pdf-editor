/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

import { generateUrl } from '@nextcloud/router'
import { DefaultType, FileType, type IFileAction, type INode } from '@nextcloud/files'
import { isPdfExtension, isPdfMime } from './editor/mimeTypes'

type TranslateFn = (app: string, text: string) => string

/** Inline document icon used for the file action. */
export const PDF_EDITOR_ICON = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
  <path d="M6 2h8l6 6v14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1zm7 1.5V9h5.5L13 3.5zM7.5 12h9v1.6h-9V12zm0 3.2h9v1.6h-9v-1.6zm0 3.2h6v1.6h-6v-1.6z"/>
</svg>`

/**
 * Whether a node is a PDF document the editor can open.
 *
 * One rule decides, so a file whose name and MIME disagree - `application/pdf`
 * named `notes.txt` - is judged once: the MIME type is authoritative when it
 * identifies a PDF, and the extension is consulted only when the MIME says
 * nothing useful (Nextcloud had not resolved it after an upload, for example).
 *
 * Only files are considered, so a folder named `archive.pdf` never matches.
 */
export function isPdfNode(node: Pick<INode, 'type' | 'mime' | 'basename'>): boolean {
  if (node.type !== FileType.File) {
    return false
  }

  if (isPdfMime(node.mime)) {
    return true
  }

  return isPdfExtension(node.basename)
}

/**
 * Build the URL of the app's own full-page editor for a file id.
 */
export function buildEditorUrl(fileId: number | string): string {
  return `${generateUrl('/apps/pdf_editor/view')}?fileIds=${encodeURIComponent(String(fileId))}`
}

interface ActionOptions {
  translate: TranslateFn
  iconSvgInline?: string
  navigate?: (url: string) => void
}

function defaultNavigate(url: string): void {
  window.location.href = url
}

/**
 * Create the file action that opens a PDF in the editor.
 *
 * A single action is enough: there is one supported format. It is registered
 * as the default action for a PDF, so a document opens the editor on a single
 * click; the app's own page is where the editor lives.
 */
export function createPdfEditorFileActions({
  translate,
  iconSvgInline = PDF_EDITOR_ICON,
  navigate = defaultNavigate,
}: ActionOptions): IFileAction[] {
  return [
    {
      id: 'pdf-editor-open',
      displayName: () => translate('pdf_editor', 'Open with PDF Editor'),
      iconSvgInline: () => iconSvgInline,
      default: DefaultType.DEFAULT,
      enabled: ({ nodes }) => nodes.length === 1 && isPdfNode(nodes[0]),
      exec: async ({ nodes }) => {
        const node = nodes[0]
        if (node?.fileid === undefined) {
          return false
        }
        navigate(buildEditorUrl(node.fileid))
        return null
      },
    },
  ]
}
