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

PDFCRAFT_VERSION="0.21.1"
PDFCRAFT_SHA256="fe9708d46af191aebb56f70eb32cf8b112b0b9452d17ae6ba3a8c4a8665fe748"

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
dest="${repo_root}/pdfcraft"
url="https://github.com/storytold/pdfcraft/releases/download/v${PDFCRAFT_VERSION}/pdfcraft-web-${PDFCRAFT_VERSION}.zip"

work="$(mktemp -d)"
# The staging paths live beside the live bundle rather than under /tmp: the
# replacement below is a rename, which is only atomic (and cheap) on one
# filesystem. A cross-device `mv` would be a copy, and a failure partway
# through it could leave the app without an editor. One EXIT trap removes the
# work directory and both staging paths, so no exit path leaks them.
staged="${repo_root}/.pdfcraft-staged.$$"
backup="${repo_root}/.pdfcraft-backup.$$"
cleanup() { rm -rf "${work}" "${staged}" "${backup}"; }
trap 'cleanup' EXIT

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

# Stage beside the live bundle and swap it in with renames.
rm -rf "${staged}"
mkdir -p "${staged}"
cp -a "${source}/." "${staged}/"

# Swap: move the live bundle aside, move the new one in, then drop the old one.
# If the second move fails, restore the backup so the app keeps its editor.
if [[ -e "${dest}" ]]; then
    mv "${dest}" "${backup}"
fi
if ! mv "${staged}" "${dest}"; then
    echo "ERROR: could not install the new bundle; restoring the previous one" >&2
    if [[ -e "${backup}" ]]; then
        mv "${backup}" "${dest}"
    fi
    exit 1
fi
rm -rf "${backup}"

echo "PdfCraft ${PDFCRAFT_VERSION} vendored into ${dest}"
