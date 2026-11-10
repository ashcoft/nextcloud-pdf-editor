#!/bin/bash
set -euo pipefail

# Enable corepack so the pinned pnpm version is used.
corepack enable

# Resolve the repository relative to this script, so setup works from any
# checkout location rather than only the path the agent happens to use.
cd "$(dirname "$(readlink -f "$0")")/.."

# Front end: the webpack bundle the app loads.
pnpm install

# Back end: PHPUnit, PHPStan, Psalm, php-cs-fixer and the OCP stubs, so the
# PHP suites and static analysis can run in the same workspace.
if command -v composer >/dev/null 2>&1; then
    composer install --no-interaction
else
    echo "composer not found; skipping the PHP dependencies (run: composer install)"
fi
