#!/usr/bin/env bash
#
# Fetch the pinned PdfCraft web build into ./pdfcraft.
#
# PdfCraft is a third-party Rust/WASM application (MIT OR Apache-2.0) that the
# editor embeds. It is vendored rather than built here: building it needs the
# Rust toolchain, Trunk and a network fetch of the OCR models, and the release
# archive is the supported artefact. This script is the upgrade path - bump
# PDFCRAFT_VERSION and PDFCRAFT_SHA256 together, run it, and commit the result.
set -euo pipefail

PDFCRAFT_VERSION="0.20.0"
PDFCRAFT_SHA256="5cd9da8025e5e4c587069f9ee5fbfeeb859f8fbc35bfdb0c63fc1f139d7240e6"

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
dest="${repo_root}/pdfcraft"
url="https://github.com/storytold/pdfcraft/releases/download/v${PDFCRAFT_VERSION}/pdfcraft-web-${PDFCRAFT_VERSION}.zip"

work="$(mktemp -d)"
trap 'rm -rf "${work}"' EXIT

echo "Downloading ${url}"
# Pin the protocol so a redirect cannot downgrade the download to plain HTTP.
curl -fsSL --proto '=https' --tlsv1.2 "${url}" -o "${work}/pdfcraft-web.zip"

# `sha256sum` is GNU coreutils; macOS ships `shasum` instead, and a contributor
# bumping the pin on a Mac should not have to install anything.
if command -v sha256sum >/dev/null 2>&1; then
    actual="$(sha256sum "${work}/pdfcraft-web.zip" | cut -d' ' -f1)"
else
    actual="$(shasum -a 256 "${work}/pdfcraft-web.zip" | cut -d' ' -f1)"
fi
if [[ "${actual}" != "${PDFCRAFT_SHA256}" ]]; then
    echo "ERROR: checksum mismatch for pdfcraft-web-${PDFCRAFT_VERSION}.zip" >&2
    echo "  expected ${PDFCRAFT_SHA256}" >&2
    echo "  actual   ${actual}" >&2
    exit 1
fi

unzip -q "${work}/pdfcraft-web.zip" -d "${work}/unpacked"

source="${work}/unpacked/pdfcraft-web-${PDFCRAFT_VERSION}"
if [[ ! -f "${source}/index.html" ]]; then
    echo "ERROR: the archive does not contain index.html; refusing to replace the bundle" >&2
    exit 1
fi

# Build the replacement beside the live bundle and swap it in only once it is
# complete, so a failed download or unpack cannot leave the app without an
# editor.
staged="${work}/staged"
mkdir -p "${staged}"
cp -a "${source}/." "${staged}/"

rm -rf "${dest}"
mv "${staged}" "${dest}"

echo "PdfCraft ${PDFCRAFT_VERSION} vendored into ${dest}"
