#!/usr/bin/env bash
# 配布物に必ず入っているべきファイル（release-required-files.txt）が、
# zip に入っているかを確かめる（Issue #1811）。
#
# 実行: bash .github/scripts/release-archive-check.sh [zip のパス]
# zip を省くと、現在の HEAD から git archive で作って確かめる。
# 欠けがなければ "0 violations" と出して終了コード 0、あれば 1 で終わる。
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
list="${root}/.github/scripts/release-required-files.txt"

zip="${1:-}"
if [ -z "$zip" ]; then
    tmp="$(mktemp -d)"
    trap 'rm -rf "$tmp"' EXIT
    zip="${tmp}/evo.zip"
    git -C "$root" archive --format=zip HEAD -o "$zip"
fi

entries="$(zipinfo -1 "$zip")"
violations=0
while IFS= read -r path; do
    case "$path" in ''|'#'*) continue ;; esac
    if ! grep -Fxq -- "$path" <<<"$entries"; then
        echo "missing  ${path}"
        violations=$((violations + 1))
    fi
done <"$list"

echo "${violations} violations"
[ "$violations" -eq 0 ]
