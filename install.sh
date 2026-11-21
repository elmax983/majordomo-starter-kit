#!/bin/sh
# Install/update Starter Kit in a MajorDoMo root.
# Usage: ./install.sh [MAJORDOMO_ROOT]   (default: /var/www)
set -eu

ROOT="${1:-/var/www}"
SRC="$(cd "$(dirname "$0")" && pwd)"

if [ ! -f "$ROOT/cycle.php" ]; then
    echo "MajorDoMo root not found: $ROOT (cycle.php is missing)" >&2
    exit 1
fi

mkdir -p "$ROOT/modules/starter_kit" "$ROOT/templates/starter_kit"
cp -a "$SRC/modules/starter_kit/." "$ROOT/modules/starter_kit/"
cp -a "$SRC/templates/starter_kit/." "$ROOT/templates/starter_kit/"

if id www-data >/dev/null 2>&1; then
    chown -R www-data:www-data "$ROOT/modules/starter_kit" "$ROOT/templates/starter_kit" 2>/dev/null || true
fi

echo "Starter Kit installed into $ROOT"
echo "Open Admin -> Modules -> Starter Kit to install demo packages."
