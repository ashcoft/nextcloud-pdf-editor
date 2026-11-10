/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

import { buildEditorUrl, createPdfEditorFileActions, isPdfNode } from '../../src/fileActions'
import { DefaultType } from '@nextcloud/files'

const translate = (_app: string, text: string) => text

function node(overrides: Record<string, unknown> = {}) {
  return {
    type: 'file',
    mime: 'application/pdf',
    basename: 'report.pdf',
    fileid: 42,
    ...overrides,
  } as never
}

describe('isPdfNode', () => {
  it('accepts a PDF file by its mime type', () => {
    expect(isPdfNode(node())).toBe(true)
  })

  it('rejects a folder', () => {
    expect(isPdfNode(node({ type: 'folder' }))).toBe(false)
  })

  it('rejects an unsupported file', () => {
    expect(isPdfNode(node({ mime: 'text/plain', basename: 'notes.txt' }))).toBe(false)
  })

  it('falls back to the extension only when the mime is generic', () => {
    expect(isPdfNode(node({ mime: 'application/octet-stream', basename: 'x.pdf' }))).toBe(true)
    expect(isPdfNode(node({ mime: '', basename: 'x.pdf' }))).toBe(true)
  })

  it('rejects a non-PDF mime even when the name ends in .pdf', () => {
    // A PNG named `.pdf`: the MIME identifies the real format, and the backend
    // refuses the same file, so the action must not be offered for it.
    expect(isPdfNode(node({ mime: 'image/png', basename: 'art.pdf' }))).toBe(false)
  })

  it('prefers the mime type when the name disagrees', () => {
    // A PDF whose name lost its extension still opens.
    expect(isPdfNode(node({ mime: 'application/pdf', basename: 'report' }))).toBe(true)
  })
})

describe('buildEditorUrl', () => {
  it('points at the app viewer route with the file id', () => {
    expect(buildEditorUrl(7)).toContain('/apps/pdf_editor/view?fileIds=7')
  })
})

describe('createPdfEditorFileActions', () => {
  const actions = () => createPdfEditorFileActions({ translate })
  const action = () => actions()[0]!

  it('registers a single PDF action', () => {
    expect(actions().map((a) => a.id)).toEqual(['pdf-editor-open'])
  })

  it('makes the action the default, so a PDF opens on a single click', () => {
    // Asserted against the constant, not its value, so an upstream enum change
    // does not make this test lie.
    expect(action().default).toBe(DefaultType.DEFAULT)
  })

  it('names the action in the menu', () => {
    expect(action().displayName({} as never)).toBe('Open with PDF Editor')
  })

  it('enables the action for one PDF only', () => {
    expect(action().enabled!({ nodes: [node()] } as never)).toBe(true)
    expect(action().enabled!({ nodes: [node(), node()] } as never)).toBe(false)
    expect(action().enabled!({ nodes: [node({ mime: 'text/plain', basename: 'notes.txt' })] } as never)).toBe(false)
  })

  it('navigates to the editor on exec', async () => {
    const navigate = jest.fn()
    const built = createPdfEditorFileActions({ translate, navigate })[0]!

    const result = await built.exec({ nodes: [node()] } as never)

    expect(result).toBeNull()
    expect(navigate).toHaveBeenCalledWith(expect.stringContaining('fileIds=42'))
  })

  it('returns false when the node has no file id', async () => {
    const navigate = jest.fn()
    const built = createPdfEditorFileActions({ translate, navigate })[0]!

    const result = await built.exec({ nodes: [node({ fileid: undefined })] } as never)

    expect(result).toBe(false)
    expect(navigate).not.toHaveBeenCalled()
  })
})
