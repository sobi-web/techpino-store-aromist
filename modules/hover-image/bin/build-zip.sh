#!/usr/bin/env bash
# Build an installable zip (without tests / dev scripts) into ./dist/.
set -euo pipefail
cd "$(dirname "$0")/.."
slug="aromist-hover-image"
version=$(grep -m1 "Version:" "$slug.php" | awk '{print $NF}')
out="dist/$slug-$version.zip"
tmp=$(mktemp -d)
mkdir -p dist "$tmp/$slug"
rsync -a --exclude ".git" --exclude "dist" --exclude "tests" --exclude "bin" --exclude ".DS_Store" --exclude "*.log" ./ "$tmp/$slug/"
( cd "$tmp" && zip -qr "$OLDPWD/$out" "$slug" )
rm -rf "$tmp"
echo "Built $out"
