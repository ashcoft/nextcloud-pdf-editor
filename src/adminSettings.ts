/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

const APP_ID = 'pdf_editor'

/**
 * Wire the admin settings form: post every field on Save and report the
 * outcome in the status region, so it is announced to screen readers too.
 *
 * Exported so tests can drive it against a rendered form.
 */
export function initSettingsForm(): void {
  const saveButton = document.getElementById('pdf-editor-save')
  const status = document.getElementById('pdf-editor-status')
  if (!(saveButton instanceof HTMLButtonElement) || !(status instanceof HTMLElement)) {
    return
  }

  saveButton.addEventListener('click', async () => {
    const params = new URLSearchParams()
    const maxSize = document.getElementById('pdf-editor-max-size')
    if (maxSize instanceof HTMLInputElement) {
      params.append('maxSizeMb', maxSize.value)
    }

    saveButton.disabled = true
    status.textContent = ''

    try {
      await axios.post(generateUrl(`apps/${APP_ID}/settings/save`), params)
      status.textContent = t(APP_ID, 'Settings have been successfully saved')
    } catch (error) {
      status.textContent = t(APP_ID, 'Error when trying to save settings')
      // Log only the HTTP status: the axios error carries
      // config.headers.requesttoken, so it must never be logged whole.
      const httpStatus = (error as { response?: { status?: number } } | null)?.response?.status
      console.error('PDF Editor: failed to save settings', httpStatus ?? 'request failed')
    } finally {
      saveButton.disabled = false
    }
  })
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initSettingsForm, { once: true })
} else {
  initSettingsForm()
}
