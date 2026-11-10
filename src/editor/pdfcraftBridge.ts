/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 *
 * Bridges a Nextcloud file to the embedded PdfCraft editor.
 *
 * PdfCraft is a third-party WASM application served in a same-origin iframe.
 * Unlike a drop-and-download editor, it publishes a host contract: with
 * `?host=parent` on its URL it talks to the framing page with `postMessage`,
 * using the messages of `craft-web-host` (storytold/craft-libs), the same
 * contract every Craft app speaks. This module is the host side of it.
 *
 * The messages, each a plain object with a `type` of `pdfcraft:<kind>`:
 *
 *  - `ready` (app -> host): the app is listening. It carries the contract
 *    `version` and the app's own `appVersion`; send a file now.
 *  - `open` (host -> app): `{ name, bytes }` holding a PDF, as an ArrayBuffer.
 *  - `save` (app -> host): `{ name, saveAs, bytes, id? }` when the user saves.
 *    With an `id`, the host answers `saved` once the file is stored, or
 *    `failed` if it could not be.
 *  - `saved` (host -> app): `{ id }` confirms a save so the app clears its
 *    unsaved flag.
 *  - `dirty` (app -> host): `{ dirty }` when unsaved work appears or goes away.
 *  - `failed` (app -> app's host): `{ error, id? }` when the app could not
 *    start, take a document, or a save went unconfirmed.
 *
 * Only a same-origin parent is answered, which is what this app is: the bundle
 * is served by the app's own controller, so the frame and the page share an
 * origin.
 */

/** A document the editor produced, ready to be written back. */
export interface BridgeOutput {
  /** File name the editor chose, e.g. `report.pdf`. */
  name: string
  /** Whether the user asked for Save As rather than Save. */
  saveAs: boolean
  /** The saved PDF bytes. */
  bytes: Uint8Array
}

export interface BridgeOptions {
  /** Called once, when the app reports it is listening. */
  onReady?: () => void
  /**
   * Called when the editor produces a document. Resolving `true` tells the
   * editor the file is stored; `false` reports a failure so it keeps its
   * unsaved state.
   */
  onSave: (output: BridgeOutput) => Promise<boolean>
  /** Called whenever the editor's unsaved state changes. */
  onDirty?: (dirty: boolean) => void
  /** Called when the app could not start, take a document, or save. */
  onFailed?: (error: string) => void
}

const APP = 'pdfcraft'
const READY_TIMEOUT_MS = 120_000

/**
 * The bridge to one PdfCraft frame.
 *
 * The message listener is installed in the constructor, because the app
 * announces itself with `ready` while its own `init` is still running: a
 * listener added after the frame's `load` event would miss it and the editor
 * would sit idle with no document.
 */
export class PdfCraftBridge {
  private readySettled = false
  private resolveReady: (() => void) | null = null
  private rejectReady: ((error: Error) => void) | null = null
  private readonly readyPromise: Promise<void>
  private disposed = false

  private readonly listener: (event: MessageEvent) => void

  constructor(
    private readonly frame: HTMLIFrameElement,
    private readonly options: BridgeOptions,
  ) {
    this.readyPromise = new Promise((resolve: () => void, reject: (error: Error) => void) => {
      this.resolveReady = resolve
      this.rejectReady = reject
    })

    // A rejection (dispose, or a timeout in `ready`) must still reach a caller
    // that awaited `ready()`, but it must not crash the page when nobody did:
    // marking it handled here leaves `ready()`'s own race free to reject.
    void this.readyPromise.catch(() => undefined)

    this.listener = (event: MessageEvent): void => {
      this.onMessage(event)
    }
    window.addEventListener('message', this.listener)
  }

  /**
   * Resolve once the editor has booted and is listening.
   *
   * A timeout rather than an indefinite wait, so a frame that never loads
   * (a missing bundle, a blocked script) surfaces as an error instead of a
   * page that hangs on "Loading".
   */
  ready(timeoutMs = READY_TIMEOUT_MS): Promise<void> {
    if (this.readySettled) {
      return Promise.resolve()
    }

    const timeout: Promise<void> = new Promise((_, reject: (error: Error) => void) => {
      window.setTimeout(() => {
        if (!this.readySettled) {
          reject(new Error('The editor did not finish loading.'))
        }
      }, timeoutMs)
    })

    return Promise.race([this.readyPromise, timeout])
  }

  /**
   * Hand a PDF to the editor.
   *
   * The bytes are sent as a transferred ArrayBuffer, so the app receives them
   * without a copy. The caller's array is copied first: the transfer detaches
   * the buffer, and the caller reuses the array for the next attempt.
   */
  open(name: string, bytes: Uint8Array): void {
    const target = this.contentWindow()
    if (target === null) {
      throw new Error('The editor frame is not available.')
    }

    const buffer = bytes.slice().buffer
    target.postMessage(
      { type: `${APP}:open`, name, bytes: buffer },
      window.location.origin,
      [buffer],
    )
  }

  /**
   * Stop listening and reject a pending `ready()`.
   *
   * The frame is discarded with the page, so there is nothing to uninstall
   * inside it; this only stops this side from handling its messages.
   */
  dispose(): void {
    if (this.disposed) {
      return
    }
    this.disposed = true
    window.removeEventListener('message', this.listener)
    this.rejectReadyOnce(new Error('The editor was closed.'))
  }

  private onMessage(event: MessageEvent): void {
    if (this.disposed) {
      return
    }

    // Only the editor's frame, on this origin. The bundle is served by the
    // app, so the frame is same-origin; anything else is not ours to act on.
    if (event.source !== this.frame.contentWindow || event.origin !== window.location.origin) {
      return
    }

    const data = event.data as Record<string, unknown> | null
    if (typeof data !== 'object' || data === null || typeof data.type !== 'string') {
      return
    }

    switch (data.type) {
      case `${APP}:ready`:
        // The contract sends `ready` once, but a second one must not re-open
        // the document or double-fire the host's callback.
        if (!this.readySettled) {
          this.readySettled = true
          this.resolveReady?.()
          this.options.onReady?.()
        }
        break
      case `${APP}:save`:
        void this.handleSave(data)
        break
      case `${APP}:dirty`:
        this.options.onDirty?.(data.dirty === true)
        break
      case `${APP}:failed`:
        this.options.onFailed?.(typeof data.error === 'string' ? data.error : 'The editor reported an error.')
        break
      default:
        // `host`, `saved` and `handoff-done` are host-bound or not used here.
        break
    }
  }

  /**
   * Take a saved document, store it, and confirm the outcome to the editor.
   *
   * The `id` is echoed back so the editor can pair the answer with the save it
   * made; a save without one is treated as done once posted, per the contract.
   */
  private async handleSave(data: Record<string, unknown>): Promise<void> {
    const id = typeof data.id === 'string' ? data.id : undefined
    const name = typeof data.name === 'string' && data.name !== '' ? data.name : 'document.pdf'
    const saveAs = data.saveAs === true

    const bytes = toUint8Array(data.bytes)
    if (bytes === null) {
      this.postFailed('The editor sent a save with no file data.', id)
      return
    }

    let stored: boolean
    try {
      stored = await this.options.onSave({ name, saveAs, bytes })
    } catch {
      stored = false
    }

    if (stored) {
      this.post({ type: `${APP}:saved`, id: id ?? '' })
    } else {
      this.postFailed('The file could not be saved.', id)
    }
  }

  private postFailed(error: string, id?: string): void {
    const message: Record<string, unknown> = { type: `${APP}:failed`, error }
    if (id !== undefined) {
      message.id = id
    }
    this.post(message)
  }

  private post(message: Record<string, unknown>): void {
    this.contentWindow()?.postMessage(message, window.location.origin)
  }

  private contentWindow(): Window | null {
    return this.frame.contentWindow
  }

  private rejectReadyOnce(error: Error): void {
    if (!this.readySettled) {
      this.readySettled = true
      this.rejectReady?.(error)
    }
  }
}

/**
 * Read a message's `bytes` field as a byte array.
 *
 * The contract allows an `ArrayBuffer` or a `Uint8Array`; a structured clone
 * of a transferred buffer arrives as an `ArrayBuffer`.
 */
function toUint8Array(value: unknown): Uint8Array | null {
  if (value instanceof ArrayBuffer) {
    return new Uint8Array(value)
  }
  if (value instanceof Uint8Array) {
    return value
  }
  return null
}
