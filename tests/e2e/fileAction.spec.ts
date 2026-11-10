import { test, expect, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

/**
 * End-to-end coverage for the built init script (js/pdf-editor.js).
 *
 * The script runs on the Files page and must register the "Open with PDF
 * Editor" file action, which is what makes a single click on a PDF open the
 * app's editor.
 *
 * The Nextcloud Files runtime is not available in CI, so the page provides a
 * minimal stub of the pieces the script touches (@nextcloud/files registry,
 * @nextcloud/l10n and @nextcloud/router). The real bundle is loaded from a URL
 * (so its automatic public path resolves) and exercised in a browser.
 */

const bundlePath = resolve(__dirname, '../../js/pdf-editor.js')

/** Install the Nextcloud stubs and serve the built bundle. */
async function loadInitScript(page: Page): Promise<void> {
  await page.addInitScript(() => {
    const w = window as unknown as Record<string, unknown>
    w.OC = { PERMISSION_READ: 1 }
    w.OCA = {}
    w.t = (_app: string, text: string) => text
    w.n = (_app: string, singular: string) => singular

    // Minimal @nextcloud/files registry, exposed on the global scope the
    // bundle reads it from.
    w._nc_files_scope = {
      v4_0: {
        fileActions: new Map(),
        registry: { dispatchTypedEvent: () => {} },
      },
    }
  })

  await page.route('**/*', (route) => {
    const url = route.request().url()
    if (url.endsWith('/pdf-editor.js')) {
      return route.fulfill({
        contentType: 'application/javascript',
        body: readFileSync(bundlePath, 'utf8'),
      })
    }
    return route.fulfill({
      contentType: 'text/html',
      body: '<!doctype html><html><head></head><body></body></html>',
    })
  })

  await page.goto('/apps/pdf_editor/')
  await page.addScriptTag({ url: '/apps/pdf_editor/js/pdf-editor.js' })
}

test('registers the file action', async ({ page }) => {
  const errors: string[] = []
  page.on('pageerror', (error) => errors.push(error.message))

  await loadInitScript(page)

  const ids = await page.evaluate(() => {
    const actions = (
      window as unknown as {
        _nc_files_scope: { v4_0: { fileActions: Map<string, unknown> } }
      }
    )._nc_files_scope.v4_0.fileActions
    return [...actions.keys()]
  })

  expect(ids).toContain('pdf-editor-open')
  expect(errors).toEqual([])
})

test('the action is the default, so a PDF opens on a single click', async ({ page }) => {
  await loadInitScript(page)

  const defaultValue = await page.evaluate(() => {
    const scope = (window as unknown as { _nc_files_scope: { v4_0: { fileActions: Map<string, { default?: unknown }> } } })
      ._nc_files_scope
    return scope.v4_0.fileActions.get('pdf-editor-open')?.default ?? null
  })

  // Asserted against the concrete value rather than "not null", so a `HIDDEN`
  // default could not pass.
  expect(defaultValue).toBe('default')
})

test('the action applies to PDFs only', async ({ page }) => {
  await loadInitScript(page)

  const results = await page.evaluate(() => {
    const actions = (
      window as unknown as {
        _nc_files_scope: {
          v4_0: {
            fileActions: Map<
              string,
              { enabled?: (context: { nodes: unknown[] }) => boolean }
            >
          }
        }
      }
    )._nc_files_scope.v4_0.fileActions

    const file = (mime: string, basename: string) => ({ type: 'file', mime, basename })
    const folder = { type: 'folder', mime: 'httpd/unix-directory', basename: 'report.pdf' }
    const cases = {
      pdf: file('application/pdf', 'report.pdf'),
      // The extension fallback covers an unresolved MIME type.
      octetPdf: file('application/octet-stream', 'report.pdf'),
      png: file('image/png', 'photo.png'),
      folder,
    }

    const out: Record<string, boolean | undefined> = {}
    for (const [name, node] of Object.entries(cases)) {
      out[name] = actions.get('pdf-editor-open')?.enabled?.({ nodes: [node] })
    }
    return out
  })

  expect(results).toEqual({
    pdf: true,
    octetPdf: true,
    png: false,
    folder: false,
  })
})
