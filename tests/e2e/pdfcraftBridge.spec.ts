import { test, expect, type Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

/**
 * End-to-end coverage for the host bridge (src/editor/pdfcraftBridge.ts),
 * driven through the built bundle.
 *
 * PdfCraft talks to its framing page with `postMessage` under `?host=parent`,
 * so the app's own page (`js/pdf-editor.js` mounting App.vue) can be exercised
 * in a real browser against a stub editor that speaks the contract. The stub
 * stands in for the 59 MB WASM app, which a headless browser cannot start
 * without a GPU; the messages it exchanges are the real ones the contract
 * defines, so the app's side of the protocol runs for real.
 *
 * A Nextcloud server is not started; every request is routed.
 */

const bundlePath = resolve(__dirname, '../../js/pdf-editor.js')
const jsDir = resolve(__dirname, '../../js')

const PDF_BYTES = '%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n'

/** A same-origin page that behaves like PdfCraft under `?host=parent`. */
const EDITOR_STUB = `<!doctype html><html><head><meta charset="utf-8"></head><body>
<canvas id="pdfcraft"></canvas>
<script>
  const app = 'pdfcraft'
  const post = (message) => window.parent.postMessage(message, window.location.origin)
  // Announce readiness the moment the frame loads, as the real app does while
  // its own boot is still running.
  post({ type: app + ':ready', version: '1', appVersion: 'stub' })
  window.addEventListener('message', (event) => {
    const data = event.data || {}
    if (data.type === app + ':open') {
      window.__opened = { name: data.name, size: data.bytes ? data.bytes.byteLength : 0 }
      // Save what was opened, as the user pressing Save would.
      post({ type: app + ':save', id: 'save-1', name: data.name, saveAs: false, bytes: data.bytes })
    }
  })
</script>
</body></html>`

async function serveApp(page: Page): Promise<void> {
  const saved: string[] = []

  await page.addInitScript(() => {
    const w = window as unknown as Record<string, unknown>
    w._oc_webroot = ''
    w.OC = { webroot: '', PERMISSION_READ: 1 }
    w.OCA = {}
    w.t = (_app: string, text: string) => text
    w.n = (_app: string, singular: string) => singular
  })

  await page.route('**/*', (route) => {
    const url = new URL(route.request().url())
    const path = url.pathname

    // `generateUrl` may prefix the app route with `/apps/index.php` depending
    // on how the root URL resolves, so routes match by suffix rather than by an
    // exact path.
    const endsWith = (suffix: string): boolean => path.endsWith(suffix)

    if (endsWith('/js/pdf-editor.js')) {
      return route.fulfill({
        contentType: 'application/javascript',
        body: readFileSync(bundlePath, 'utf8'),
      })
    }
    // The lazily imported Vue/App chunks are requested relative to the entry
    // script's public path, so they arrive under the app's js/ directory too.
    if (path.includes('/js/') && path.endsWith('.js')) {
      const name = path.slice(path.lastIndexOf('/js/') + '/js/'.length)
      if (!name.includes('/') && !name.includes('..')) {
        return route.fulfill({
          contentType: 'application/javascript',
          body: readFileSync(resolve(jsDir, name), 'utf8'),
        })
      }
    }
    if (endsWith('/api/load/1')) {
      return route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({
          id: 1,
          name: 'report.pdf',
          size: PDF_BYTES.length,
          mimeType: 'application/pdf',
          path: '/alice/files/report.pdf',
          directory: '/',
          etag: 'etag-a',
          canSave: true,
          url: `${url.origin}/apps/pdf_editor/api/download/1`,
          theme: 'light',
          contentType: 'application/pdf',
          maxFileSize: 209715200,
        }),
      })
    }
    if (endsWith('/api/download/1')) {
      return route.fulfill({ contentType: 'application/pdf', body: PDF_BYTES })
    }
    if (endsWith('/api/save/1')) {
      saved.push(route.request().postData() ?? '')
      return route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({ etag: 'etag-b', size: PDF_BYTES.length, mtime: 1 }),
      })
    }
    if (endsWith('/pdfcraft/index.html')) {
      return route.fulfill({ contentType: 'text/html', body: EDITOR_STUB })
    }
    return route.fulfill({
      contentType: 'text/html',
      body: `<!doctype html><html><head></head><body>
        <div id="pdf-editor-container" data-file-ids="1"></div>
      </body></html>`,
    })
  })

  await page.goto('/apps/pdf_editor/view?fileIds=1')
  await page.addScriptTag({ url: '/apps/pdf_editor/js/pdf-editor.js' })

  return saved
}

test('opens the document in the editor and saves it back', async ({ page }) => {
  const saved = await serveApp(page)

  // The editor stub saves what it is given, so the app must POST it back.
  await expect.poll(() => saved.length, { timeout: 15_000 }).toBeGreaterThan(0)

  const frame = page.frameLocator('iframe[title="PdfCraft"]')
  // The frame is a real same-origin document the app opened the PDF into.
  await expect(frame.locator('#pdfcraft')).toBeAttached()

  const opened = await frame.locator('body').evaluate(
    () => (window as unknown as { __opened?: { name: string; size: number } }).__opened,
  )
  expect(opened?.name).toBe('report.pdf')
  expect(opened?.size).toBeGreaterThan(0)

  // The save carried the etag the document was loaded from, so the server can
  // refuse a stale write.
  expect(saved[0]).toContain('etag-a')
})

test('shows the document name in the toolbar once it is open', async ({ page }) => {
  await serveApp(page)

  await expect(page.locator('.pdf-editor-toolbar__file')).toHaveText('report.pdf', {
    timeout: 15_000,
  })
})
