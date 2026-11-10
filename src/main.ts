/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

import { registerFileAction } from '@nextcloud/files'
import { translate as t } from '@nextcloud/l10n'
import { createPdfEditorFileActions } from './fileActions'
import { mountEditorApp, mountStartPage } from './mount'

/**
 * Register the "Open with PDF Editor" file action.
 *
 * Registered in a try/catch: the script is an init script, so this module is
 * evaluated once per page, but registering twice would throw "FileAction ...
 * already registered".
 */
function registerPdfEditorFileActions(): void {
  for (const action of createPdfEditorFileActions({ translate: t })) {
    try {
      registerFileAction(action)
    } catch {
      // The action is already registered; nothing else to do.
    }
  }
}

function showMountError(container: HTMLElement): void {
  // Leave a visible message instead of a blank page with a silent rejection.
  container.textContent = t(
    'pdf_editor',
    'Failed to load the PDF Editor. Please reload the page and try again.',
  )
}

/**
 * Mount the app component that matches the rendered template.
 *
 * The start page renders `#pdf-editor-start`, the editor renders
 * `#pdf-editor-container`. On the Files app neither exists and this is a no-op.
 */
function mountStandaloneApp(): void {
  const startContainer = document.getElementById('pdf-editor-start')
  if (startContainer) {
    mountStartPage(startContainer).catch((error: unknown) => {
      console.error('PDF Editor: failed to mount the start page', error)
      showMountError(startContainer)
    })
    return
  }

  const editorContainer = document.getElementById('pdf-editor-container')
  if (!editorContainer) {
    return
  }

  mountEditorApp(editorContainer).catch((error: unknown) => {
    console.error('PDF Editor: failed to mount the editor', error)
    showMountError(editorContainer)
  })
}

function init(): void {
  registerPdfEditorFileActions()
  mountStandaloneApp()
}

init()

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mountStandaloneApp, { once: true })
}
