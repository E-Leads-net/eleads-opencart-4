#!/usr/bin/env bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
RELEASES_DIR="$ROOT_DIR/build/releases"
mkdir -p "$RELEASES_DIR"

stage="$(mktemp -d)"
cp "$ROOT_DIR/install.json" "$stage/install.json"
cp -R "$ROOT_DIR/admin" "$stage/admin"
cp -R "$ROOT_DIR/catalog" "$stage/catalog"
cp -R "$ROOT_DIR/system" "$stage/system"
(cd "$stage" && zip -qr "$RELEASES_DIR/eleads-opencart-4.x.ocmod.zip" install.json admin catalog system)
rm -rf "$stage"

echo "Built: $RELEASES_DIR/eleads-opencart-4.x.ocmod.zip"
