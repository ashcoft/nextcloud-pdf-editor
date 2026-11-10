/**
 * SPDX-FileCopyrightText: 2026 PDF Editor Contributors
 * SPDX-License-Identifier: MIT
 */

import { readRecentDocuments, resolveFileId } from '../../src/mount'

function container(dataset: Record<string, string>): HTMLElement {
  const element = document.createElement('div')
  for (const [key, value] of Object.entries(dataset)) {
    element.dataset[key] = value
  }
  return element
}

describe('readRecentDocuments', () => {
  it('parses a valid list', () => {
    const element = container({
      recentDocuments: JSON.stringify([
        { id: 1, name: 'a.pdf', path: '/a.pdf', extension: 'pdf', mtime: 100 },
      ]),
    })

    const documents = readRecentDocuments(element)
    expect(documents).toHaveLength(1)
    expect(documents[0]!.name).toBe('a.pdf')
  })

  it('returns an empty list when the attribute is absent', () => {
    expect(readRecentDocuments(container({}))).toEqual([])
  })

  it('returns an empty list when the JSON is malformed', () => {
    expect(readRecentDocuments(container({ recentDocuments: '{not json' }))).toEqual([])
  })

  it('returns an empty list when the value is not an array', () => {
    expect(readRecentDocuments(container({ recentDocuments: '{"a":1}' }))).toEqual([])
  })

  it('drops entries missing required fields', () => {
    const element = container({
      recentDocuments: JSON.stringify([
        { id: 1, name: 'a.pdf', path: '/a.pdf', extension: 'pdf', mtime: 100 },
        { id: 2, name: 'b.pdf' },
      ]),
    })

    expect(readRecentDocuments(element)).toHaveLength(1)
  })
})

describe('resolveFileId', () => {
  it('prefers the container attribute', () => {
    window.history.replaceState({}, '', '/?fileIds=99')
    expect(resolveFileId(container({ fileIds: '12' }))).toBe('12')
  })

  it('falls back to the query string', () => {
    window.history.replaceState({}, '', '/?fileIds=99')
    expect(resolveFileId(container({}))).toBe('99')
  })

  it('accepts the singular query parameter', () => {
    window.history.replaceState({}, '', '/?fileId=7')
    expect(resolveFileId(container({}))).toBe('7')
  })

  it('returns null when nothing is set', () => {
    window.history.replaceState({}, '', '/')
    expect(resolveFileId(container({}))).toBeNull()
  })
})
