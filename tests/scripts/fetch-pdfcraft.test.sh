#!/usr/bin/env bash
#
# Tests for scripts/fetch-pdfcraft.sh.
#
# `curl`, `unzip` and the rename that installs the bundle are stubbed, so the
# suite needs no network and never downloads the ~33 MB archive. The interrupt
# case is deterministic: the `mv` stub signals the script and then waits for it
# to exit, so the EXIT trap has always finished before the assertion.
set -uo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
source_script="${repo_root}/scripts/fetch-pdfcraft.sh"

work="$(mktemp -d)"
trap 'rm -rf "${work}"' EXIT

failures=0
pass() { echo "ok   - $1"; }
fail() { echo "FAIL - $1"; failures=$((failures + 1)); }

version="$(sed -n 's/^PDFCRAFT_VERSION="\([^"]*\)"/\1/p' "${source_script}" | head -1)"
if [[ -z "${version}" ]]; then
    fail "could not read PDFCRAFT_VERSION from ${source_script}"
    exit 1
fi

# The "download" is a fixed byte string: the script only hashes it, and `unzip`
# is stubbed, so no real archive is needed. Its digest is what the pinned copy
# below is checked against.
printf 'stub release archive for %s\n' "${version}" > "${work}/download"
if command -v sha256sum >/dev/null 2>&1; then
    sha="$(sha256sum "${work}/download" | cut -d' ' -f1)"
else
    sha="$(shasum -a 256 "${work}/download" | cut -d' ' -f1)"
fi

# A copy of the real script, re-pinned to the fixture and laid out as the real
# one expects (scripts/ beside pdfcraft/).
mkdir -p "${work}/repo/scripts"
sed -e "s|^PDFCRAFT_VERSION=\".*\"|PDFCRAFT_VERSION=\"${version}\"|" \
    -e "s|^PDFCRAFT_SHA256=\".*\"|PDFCRAFT_SHA256=\"${sha}\"|" \
    "${source_script}" > "${work}/repo/scripts/fetch-pdfcraft.sh"

mkdir -p "${work}/bin"
cat > "${work}/bin/curl" <<STUB
#!/usr/bin/env bash
out=""
while [[ \$# -gt 0 ]]; do
    if [[ "\$1" == "-o" ]]; then out="\$2"; shift 2; continue; fi
    shift
done
cp "${work}/download" "\${out}"
STUB
# `unzip` writes the unpacked tree the script expects instead of extracting.
cat > "${work}/bin/unzip" <<STUB
#!/usr/bin/env bash
dest=""
while [[ \$# -gt 0 ]]; do
    if [[ "\$1" == "-d" ]]; then dest="\$2"; shift 2; continue; fi
    shift
done
mkdir -p "\${dest}/pdfcraft-web-${version}"
printf 'NEW BUNDLE\n' > "\${dest}/pdfcraft-web-${version}/index.html"
STUB
cat > "${work}/bin/mv" <<'STUB'
#!/usr/bin/env bash
# The install rename moves a staged bundle into place. When asked, interrupt the
# script the instant before that rename, then wait for it to exit, so the test
# observes exactly what the EXIT trap left behind.
if [[ -n "${PDFCRAFT_TEST_INTERRUPT:-}" ]]; then
    for arg in "$@"; do
        case "${arg}" in
            *.pdfcraft-staged.*)
                kill -TERM "${PPID}" 2>/dev/null
                waited=0
                while kill -0 "${PPID}" 2>/dev/null && [[ "${waited}" -lt 100 ]]; do
                    sleep 0.05
                    waited=$((waited + 1))
                done
                ;;
        esac
    done
fi
exec /usr/bin/mv "$@"
STUB
chmod +x "${work}/bin/curl" "${work}/bin/unzip" "${work}/bin/mv"

seed_bundle() {
    rm -rf "${work}/repo/pdfcraft" "${work}/repo"/.pdfcraft-*
    mkdir -p "${work}/repo/pdfcraft"
    printf 'OLD BUNDLE\n' > "${work}/repo/pdfcraft/index.html"
}

run_fetch() {
    PATH="${work}/bin:${PATH}" bash "${work}/repo/scripts/fetch-pdfcraft.sh" >"${work}/out" 2>&1
}

# 1. A completed fetch installs the new bundle.
seed_bundle
run_fetch
if [[ "$(cat "${work}/repo/pdfcraft/index.html" 2>/dev/null)" == "NEW BUNDLE" ]]; then
    pass "installs the new bundle"
else
    fail "the new bundle was not installed"
fi

# 2. An interrupt during the swap restores the previous bundle. Bash runs the
# EXIT trap on SIGTERM, and it must put the backup back rather than delete the
# only copy of the live bundle.
seed_bundle
# The kill makes the shell report the terminated job; drop that noise.
{ PDFCRAFT_TEST_INTERRUPT=1 run_fetch; } 2>/dev/null
if [[ "$(cat "${work}/repo/pdfcraft/index.html" 2>/dev/null)" == "OLD BUNDLE" ]]; then
    pass "restores the previous bundle when interrupted mid-swap"
else
    fail "the previous bundle was lost when interrupted mid-swap"
fi

echo
if [[ "${failures}" -eq 0 ]]; then
    echo "All fetch-pdfcraft tests passed."
else
    echo "${failures} test(s) failed."
fi
exit "${failures}"
