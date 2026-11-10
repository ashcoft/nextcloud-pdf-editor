/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

import { PdfCraftBridge, type BridgeOutput } from '../../src/editor/pdfcraftBridge'

/**
 * A frame whose `contentWindow` is a stub window we can drive.
 *
 * The bridge talks to the editor with `postMessage`, so the test needs a
 * message target that records what the host sends and can reply as the editor
 * would. jsdom's iframe cannot run the real WASM app, so the frame's window is
 * replaced with a recorder; the messages themselves are the real ones the
 * contract defines.
 */
function makeFrame() {
  const frame = document.createElement('iframe')
  document.body.appendChild(frame)

  const sent: Array<Record<string, unknown>> = []
  const stubWindow = {
    postMessage: jest.fn((message: Record<string, unknown>) => {
      sent.push(message)
    }),
  }

  Object.defineProperty(frame, 'contentWindow', { value: stubWindow, configurable: true })

  return { frame, stubWindow, sent }
}

/**
 * Deliver a message to the bridge as the editor would.
 *
 * `source` and `origin` are set to what the bridge accepts, so the test drives
 * the same path a real `postMessage` does rather than calling a private method.
 */
function deliver(source: unknown, data: Record<string, unknown>, origin = window.location.origin) {
  const event = new MessageEvent('message', { data })
  // `source` and `origin` are read-only accessors on MessageEvent, so they are
  // defined rather than assigned.
  Object.defineProperty(event, 'source', { value: source })
  Object.defineProperty(event, 'origin', { value: origin })
  window.dispatchEvent(event)
}

describe('PdfCraftBridge', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('resolves ready() when the editor announces itself', async () => {
    const { frame, stubWindow } = makeFrame()
    const bridge = new PdfCraftBridge(frame, { onSave: jest.fn() })

    const ready = bridge.ready(1000)
    deliver(stubWindow, { type: 'pdfcraft:ready', version: '1', appVersion: '0.20.0' })

    await expect(ready).resolves.toBeUndefined()
    bridge.dispose()
  })

  it('calls onReady exactly once', async () => {
    const { frame, stubWindow } = makeFrame()
    const onReady = jest.fn()
    const bridge = new PdfCraftBridge(frame, { onSave: jest.fn(), onReady })

    deliver(stubWindow, { type: 'pdfcraft:ready' })
    deliver(stubWindow, { type: 'pdfcraft:ready' })

    await bridge.ready(1000)
    expect(onReady).toHaveBeenCalledTimes(1)
    bridge.dispose()
  })

  it('rejects ready() when the editor never loads', async () => {
    const { frame } = makeFrame()
    const bridge = new PdfCraftBridge(frame, { onSave: jest.fn() })

    await expect(bridge.ready(10)).rejects.toThrow('did not finish loading')
    bridge.dispose()
  })

  it('opens a file as a transferred ArrayBuffer without detaching the caller array', () => {
    const { frame, stubWindow } = makeFrame()
    const bridge = new PdfCraftBridge(frame, { onSave: jest.fn() })

    const bytes = new Uint8Array([0x25, 0x50, 0x44, 0x46])
    bridge.open('report.pdf', bytes)

    // Assert the outbound contract: the message shape, the target origin and
    // the transfer list. A regression in either the origin or the transfer
    // would otherwise pass unnoticed.
    expect(stubWindow.postMessage).toHaveBeenCalledTimes(1)
    const [message, targetOrigin, transfer] = stubWindow.postMessage.mock.calls[0]!
    expect(message).toMatchObject({ type: 'pdfcraft:open', name: 'report.pdf' })
    expect(targetOrigin).toBe(window.location.origin)
    expect(transfer).toHaveLength(1)
    expect(transfer[0]).toBeInstanceOf(ArrayBuffer)
    // The caller's array must still be usable: the bridge copies before the
    // transfer, so a second open with the same array works.
    expect(Array.from(bytes)).toEqual([0x25, 0x50, 0x44, 0x46])
    bridge.dispose()
  })

  it('stores a saved document and answers the editor with saved', async () => {
    const { frame, stubWindow, sent } = makeFrame()
    const onSave = jest.fn(async () => true)
    const bridge = new PdfCraftBridge(frame, { onSave })

    deliver(stubWindow, {
      type: 'pdfcraft:save',
      id: 's1',
      name: 'report.pdf',
      saveAs: false,
      bytes: new Uint8Array([1, 2, 3]).buffer,
    })

    // The handler is async; let it settle.
    await Promise.resolve()
    await Promise.resolve()

    expect(onSave).toHaveBeenCalledTimes(1)
    const output = onSave.mock.calls[0]![0] as BridgeOutput
    expect(output.name).toBe('report.pdf')
    expect(output.saveAs).toBe(false)
    expect(Array.from(output.bytes)).toEqual([1, 2, 3])

    const reply = sent.find((m) => m.type === 'pdfcraft:saved')
    expect(reply).toEqual({ type: 'pdfcraft:saved', id: 's1' })
    bridge.dispose()
  })

  it('reports a failed save back to the editor', async () => {
    const { frame, stubWindow, sent } = makeFrame()
    const bridge = new PdfCraftBridge(frame, { onSave: async () => false })

    deliver(stubWindow, {
      type: 'pdfcraft:save',
      id: 's2',
      name: 'report.pdf',
      bytes: new Uint8Array([1]).buffer,
    })

    await Promise.resolve()
    await Promise.resolve()

    const reply = sent.find((m) => m.type === 'pdfcraft:failed')
    expect(reply).toMatchObject({ type: 'pdfcraft:failed', id: 's2' })
    bridge.dispose()
  })

  it('reports a save with no bytes as a failure', async () => {
    const { frame, stubWindow, sent } = makeFrame()
    const onSave = jest.fn()
    const bridge = new PdfCraftBridge(frame, { onSave })

    deliver(stubWindow, { type: 'pdfcraft:save', id: 's3', name: 'report.pdf' })

    await Promise.resolve()
    await Promise.resolve()

    expect(onSave).not.toHaveBeenCalled()
    expect(sent.find((m) => m.type === 'pdfcraft:failed')).toMatchObject({ id: 's3' })
    bridge.dispose()
  })

  it('forwards the dirty flag', () => {
    const { frame, stubWindow } = makeFrame()
    const onDirty = jest.fn()
    const bridge = new PdfCraftBridge(frame, { onSave: jest.fn(), onDirty })

    deliver(stubWindow, { type: 'pdfcraft:dirty', dirty: true })
    deliver(stubWindow, { type: 'pdfcraft:dirty', dirty: false })

    expect(onDirty).toHaveBeenNthCalledWith(1, true)
    expect(onDirty).toHaveBeenNthCalledWith(2, false)
    bridge.dispose()
  })

  it('forwards a startup failure', () => {
    const { frame, stubWindow } = makeFrame()
    const onFailed = jest.fn()
    const bridge = new PdfCraftBridge(frame, { onSave: jest.fn(), onFailed })

    deliver(stubWindow, { type: 'pdfcraft:failed', error: 'no WebGL' })

    expect(onFailed).toHaveBeenCalledWith('no WebGL')
    bridge.dispose()
  })

  it('ignores messages from another window', () => {
    const { frame } = makeFrame()
    const onDirty = jest.fn()
    const bridge = new PdfCraftBridge(frame, { onSave: jest.fn(), onDirty })

    deliver({ not: 'the frame' }, { type: 'pdfcraft:dirty', dirty: true })

    expect(onDirty).not.toHaveBeenCalled()
    bridge.dispose()
  })

  it('ignores messages from another origin', () => {
    const { frame, stubWindow } = makeFrame()
    const onDirty = jest.fn()
    const bridge = new PdfCraftBridge(frame, { onSave: jest.fn(), onDirty })

    deliver(stubWindow, { type: 'pdfcraft:dirty', dirty: true }, 'https://evil.example')

    expect(onDirty).not.toHaveBeenCalled()
    bridge.dispose()
  })

  it('ignores a message with no type', () => {
    const { frame, stubWindow } = makeFrame()
    const onDirty = jest.fn()
    const bridge = new PdfCraftBridge(frame, { onSave: jest.fn(), onDirty })

    deliver(stubWindow, { dirty: true })

    expect(onDirty).not.toHaveBeenCalled()
    bridge.dispose()
  })

  it('stops handling messages once disposed', () => {
    const { frame, stubWindow } = makeFrame()
    const onDirty = jest.fn()
    const bridge = new PdfCraftBridge(frame, { onSave: jest.fn(), onDirty })

    bridge.dispose()
    deliver(stubWindow, { type: 'pdfcraft:dirty', dirty: true })

    expect(onDirty).not.toHaveBeenCalled()
  })
})
