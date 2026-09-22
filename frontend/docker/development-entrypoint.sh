#!/bin/sh

set -eu

LOCK_HASH="$(sha256sum package-lock.json | cut -d ' ' -f 1)"
LOCK_MARKER="node_modules/.autobi-package-lock"

if [ ! -f "$LOCK_MARKER" ] || [ "$(cat "$LOCK_MARKER")" != "$LOCK_HASH" ]; then
    npm ci
    printf '%s' "$LOCK_HASH" >"$LOCK_MARKER"
fi

exec npm run dev -- --hostname 0.0.0.0
