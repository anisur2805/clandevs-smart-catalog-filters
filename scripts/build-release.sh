#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="woo-filter-studio"
VERSION="$(sed -n 's/^ \* Version: //p' "$ROOT_DIR/woo-filter-studio.php" | head -n 1)"
DISTIGNORE_FILE="${ROOT_DIR}/.distignore"

if [[ -z "${VERSION}" ]]; then
  VERSION="0.0.0"
fi

DIST_DIR="${ROOT_DIR}/dist"
BUILD_DIR="${DIST_DIR}/${PLUGIN_SLUG}"
ZIP_FILE="${DIST_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"

RSYNC_ARGS=(
  -a
  --delete
  --delete-excluded
)

if [[ -f "${DISTIGNORE_FILE}" ]]; then
  RSYNC_ARGS+=(--exclude-from="${DISTIGNORE_FILE}")
fi

rm -rf "${BUILD_DIR}" "${ZIP_FILE}"
mkdir -p "${DIST_DIR}"

rsync "${RSYNC_ARGS[@]}" "${ROOT_DIR}/" "${BUILD_DIR}/"

( cd "${DIST_DIR}" && zip -r "${PLUGIN_SLUG}-${VERSION}.zip" "${PLUGIN_SLUG}" >/dev/null )

echo "Created ${ZIP_FILE}"
