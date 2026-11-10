# PDF Editor

[![Latest Release](https://img.shields.io/github/v/release/ashcoft/nextcloud-pdf-editor)](https://github.com/ashcoft/nextcloud-pdf-editor/releases)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![Nextcloud Version](https://img.shields.io/badge/Nextcloud-34--35-blue)](https://nextcloud.com)
[![PHP Version](https://img.shields.io/badge/PHP-8.4-purple)](https://php.net)
[![Node Version](https://img.shields.io/badge/Node-%E2%89%A524-339933?logo=node.js&logoColor=white)](https://nodejs.org)
[![Powered by PdfCraft](https://img.shields.io/badge/Powered%20by-PdfCraft-b70228)](https://github.com/storytold/pdfcraft)

[![Node tests](https://img.shields.io/github/actions/workflow/status/ashcoft/nextcloud-pdf-editor/node-test.yml?label=tests)](https://github.com/ashcoft/nextcloud-pdf-editor/actions/workflows/node-test.yml)
[![E2E tests](https://img.shields.io/github/actions/workflow/status/ashcoft/nextcloud-pdf-editor/e2e.yml?label=e2e)](https://github.com/ashcoft/nextcloud-pdf-editor/actions/workflows/e2e.yml)
[![Coverage](https://codecov.io/gh/ashcoft/nextcloud-pdf-editor/branch/main/graph/badge.svg)](https://codecov.io/gh/ashcoft/nextcloud-pdf-editor)
[![Quality Gate](https://sonarcloud.io/api/project_badges/measure?project=ashcoft_pdf-editor&metric=alert_status)](https://sonarcloud.io/dashboard?id=ashcoft_pdf-editor)

A native Nextcloud app for reading and editing PDF documents, built on
[PdfCraft](https://github.com/storytold/pdfcraft) — an open-source PDF workbench written from scratch
in Rust.

The app is a thin PHP backend that resolves files, streams them to the browser, and writes edits back,
paired with a Vue 3 front end. The editor itself is PdfCraft's web build, embedded in an iframe and
driven by a small bridge, so reading, organising, combining, splitting, annotating, signing and OCR come
from upstream rather than from code maintained here.

- **Supported format:** PDF
- **Nextcloud:** 34 – 35
- **PHP:** 8.4+

## Features

### Editing

Everything PdfCraft offers, in the browser:

- Read a PDF with a fast, native-feeling viewer
- Reorder, rotate, insert, delete and extract pages
- Combine several PDFs into one, and split one into several
- Add, edit and remove text, shapes and images
- Annotate and comment, and fill in forms
- Sign, redact and protect a document
- Recognise text (OCR) in a scan

### Integration

- A PDF opens in the editor on a single click: the app is its default viewer
- The file is handed to the editor over PdfCraft's own host contract, so no temporary copy is written
  to the server
- **Save** writes the editor's output back to the file under the etag lock, so a stale editor cannot
  overwrite someone else's work
- The toolbar shows the document name and a dirty marker, and warns before leaving with unsaved changes
- Start page with recent PDFs and a shortcut to the Files app
- Admin setting for the maximum file size

## Architecture

```
appinfo/           App metadata and routes
lib/               PHP backend
  AppInfo/         Application bootstrap and format helpers
  Config/          App-wide settings
  Controller/      HTTP endpoints (pages, files, bundle, admin settings)
  Listener/        Load the front end
  Service/         File resolution, recent documents, the vendored bundle
pdfcraft/          Vendored PdfCraft web build (fetched and committed)
scripts/           fetch-pdfcraft.sh: download and verify a pinned release
src/               Vue 3 front end
  editor/          The bridge to the embedded editor, and format helpers
  utils/           Nextcloud API helpers
  App.vue          Editor host: toolbar, iframe, status
  StartPage.vue    Start page
templates/         PHP templates the Vue components mount into
css/               Stylesheet entry points
tests/             PHPUnit (backend), Jest (front end) and Playwright (browser) suites
```

### How a file is opened

1. The file action navigates to `/apps/pdf_editor/view?fileIds=<id>`.
2. `FileController::load` resolves the id against the user's own folder, validates the type and size, and
   returns metadata plus a session-authenticated download URL.
3. The browser fetches the bytes through that URL.
4. The host page hands the bytes to the embedded editor over PdfCraft's host contract
   (`pdfcraft:open`). Nothing is written to the server to open a file.
5. `PdfCraftController::asset` serves the editor bundle itself, with `application/wasm` on the module.

### How a save works

The editor's Save posts the produced PDF back to the host as `pdfcraft:save`. The host posts it as a
base64 data URL together with the etag it loaded. The server takes an exclusive lock on the file,
**re-checks the etag inside the lock**, and only then writes. Two saves based on the same version
therefore cannot both succeed: the second sees the new etag and is refused with `409`. On success the
host answers `pdfcraft:saved`, which clears the editor's unsaved flag.

The uploaded bytes are validated by content: a PDF must start with `%PDF-` and carry an `%%EOF` marker,
so a truncated or non-PDF payload cannot replace a readable file.

## The embedded editor

PdfCraft is a Rust/WASM application, not a library, so it is integrated by embedding it rather than by
importing an API:

- `pdfcraft/` holds the vendored web build. `scripts/fetch-pdfcraft.sh` downloads the pinned release
  and verifies its SHA-256, so the tree can be regenerated and audited.
- `PdfCraftController` serves it, and `PdfCraftBundleService` refuses any path that would leave the
  bundle directory.
- `src/editor/pdfcraftBridge.ts` is the bridge. Unlike an editor with no host API, PdfCraft publishes a
  host contract: loaded with `?host=parent` in a same-origin frame, it talks to the page with
  `postMessage` (`pdfcraft:ready`, `pdfcraft:open`, `pdfcraft:save`, `pdfcraft:saved`, `pdfcraft:dirty`,
  `pdfcraft:failed`), the same contract every Craft app speaks.

`tests/e2e/pdfcraftBridge.spec.ts` drives the real bridge against a stub editor that speaks the contract,
in a browser. **Any bump of the vendored version must keep that test passing.**

PdfCraft is MIT OR Apache-2.0; its licences ship in `pdfcraft/`.

## Requirements

- Nextcloud 34 or 35
- PHP 8.4 or newer
- Node.js 24 or newer and pnpm 10 or newer (to build the front end)
- A browser with WebAssembly SIMD; WebGPU is used where available, with a WebGL2 fallback

## Installation

### From a release archive

Download the archive from the releases page, extract it into your `apps` directory, and enable it:

```bash
occ app:enable pdf_editor
```

### From source

```bash
git clone https://github.com/ashcoft/nextcloud-pdf-editor.git
cd nextcloud-pdf-editor

# Front end
corepack enable
pnpm install
pnpm run build

# Back end (only needed to run the tests or static analysis)
composer install

# Place the app in Nextcloud and enable it
ln -s "$(pwd)" /path/to/nextcloud/apps/pdf_editor
occ app:enable pdf_editor
```

## Configuration

Settings live under **Administration settings → PDF Editor**.

| Setting | Default | Meaning |
| --- | --- | --- |
| Maximum file size (MB) | 200 | Largest file the editor will open, 1–512 |

## Development

```bash
pnpm run dev          # webpack watch
pnpm run build        # production bundle
pnpm test             # Jest (front end)
pnpm run test:coverage
pnpm run test:e2e     # Playwright (front end, real browser)
pnpm run lint         # ESLint
pnpm run stylelint    # Stylelint
pnpm run check-types  # vue-tsc

composer test:unit    # PHPUnit (back end)
composer psalm        # Psalm
composer phpstan      # PHPStan
composer rector       # Rector (applies changes; the workflow runs it in a PR)
composer cs:check     # php-cs-fixer, dry run
composer lint         # php -l over every PHP file
```

`make test` and `make lint` run the front-end and back-end suites together. `make appstore` produces a
release archive in `build/artifacts`.

See [AGENTS.md](AGENTS.md) for the conventions this repository follows.

## Continuous integration

Every workflow lives in `.github/workflows/`. Most are the Nextcloud organization templates, kept in
sync by the `Update workflows` workflow.

| Workflow | What it runs |
| --- | --- |
| `lint-php` | `php -l` on every supported PHP version |
| `lint-php-cs` | php-cs-fixer, dry run |
| `lint-info-xml` | `appinfo/info.xml` against the App Store schema |
| `lint-eslint` | ESLint |
| `lint-typescript` | vue-tsc |
| `lint-stylelint` | Stylelint |
| `phpstan` | PHPStan |
| `psalm`, `psalm-matrix` | Psalm, against each supported OCP version |
| `node-test` | Jest, with coverage uploaded to Codecov |
| `phpunit-sqlite`, `phpunit-mariadb`, `phpunit-oci` | PHPUnit against a real Nextcloud on each database |
| `e2e` | Playwright against the built bundle |
| `codeql` | CodeQL for the Actions and TypeScript sources |
| `version-check` | `appinfo/info.xml` and `package.json` versions agree |
| `pdfcraft-bundle` | the committed editor bundle matches the pinned PdfCraft release |
| `release` | semantic-release, then build and attach the app archives |
| `rector`, `update-nextcloud-ocp`, `pnpm-audit`, `renovate`, `pdfcraft-update` | Scheduled dependency and code maintenance |

`phpunit-*` install the app into a real Nextcloud server checkout and run it with
`occ app:enable`, so the app is exercised against the framework rather than only in isolation.

## Security

- Every file access goes through the user's own folder, so a file id a user cannot see is never returned
- Downloads are session-authenticated; the client never receives a second credential
- Saves are protected by an etag check under an exclusive lock
- Uploaded bytes are validated by content, not by the data URL prefix
- The editor bundle is served from a fixed directory, and a request path that would leave it is refused
- Admin settings are restricted with `AuthorizedAdminSetting`

## License

MIT. See [LICENSE](LICENSE).

The embedded editor is [PdfCraft](https://github.com/storytold/pdfcraft), by Storytold and
contributors, licensed MIT OR Apache-2.0. Its licence files and attribution ship with the vendored build
in `pdfcraft/`.
