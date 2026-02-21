#!/usr/bin/env bash
set -euo pipefail

from_tag=""
to_ref="HEAD"
output_path=""
include_commit_subjects=0

usage() {
    cat <<'USAGE'
Usage:
  generate_release_draft.sh [--from <release-tag>] [--to <ref>] [--output <file>] [--include-commit-subjects]

Options:
  --from                     Base tag (default: latest reachable release-* tag)
  --to                       Target ref (default: HEAD)
  --output                   Output markdown file path (default: stdout)
  --include-commit-subjects  Append commit subjects as supplemental info
  -h, --help
USAGE
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --from)
            from_tag="${2:-}"
            shift 2
            ;;
        --to)
            to_ref="${2:-}"
            shift 2
            ;;
        --output)
            output_path="${2:-}"
            shift 2
            ;;
        --include-commit-subjects)
            include_commit_subjects=1
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1" >&2
            usage >&2
            exit 1
            ;;
    esac
done

if ! git rev-parse --verify "$to_ref" >/dev/null 2>&1; then
    echo "Target ref not found: $to_ref" >&2
    exit 1
fi

if [[ -z "$from_tag" ]]; then
    from_tag="$(git describe --tags --match 'release-*' --abbrev=0 "$to_ref" 2>/dev/null || true)"
fi

if [[ -z "$from_tag" ]]; then
    echo "Base release tag is required. Use --from <release-tag>." >&2
    exit 1
fi

if ! git rev-parse --verify "$from_tag" >/dev/null 2>&1; then
    echo "Base tag not found: $from_tag" >&2
    exit 1
fi

range="${from_tag}..${to_ref}"
remote_url="$(git config --get remote.origin.url || true)"
repo_url=""

if [[ "$remote_url" =~ ^git@github.com:(.+)\.git$ ]]; then
    repo_url="https://github.com/${BASH_REMATCH[1]}"
elif [[ "$remote_url" =~ ^https://github.com/(.+)\.git$ ]]; then
    repo_url="https://github.com/${BASH_REMATCH[1]}"
elif [[ "$remote_url" =~ ^https://github.com/.+ ]]; then
    repo_url="${remote_url%.git}"
fi

compare_url=""
if [[ -n "$repo_url" ]]; then
    compare_url="${repo_url}/compare/${from_tag}...${to_ref}"
fi

tmp_prs="$(mktemp)"
tmp_dirs="$(mktemp)"
tmp_files="$(mktemp)"
tmp_files_all="$(mktemp)"
trap 'rm -f "$tmp_prs" "$tmp_dirs" "$tmp_files" "$tmp_files_all"' EXIT

# Parse merge commits and prefer PR titles from merge body.
git log --merges --pretty='%s%x1f%b%x1e' "$range" | \
awk -v RS='\x1e' -v FS='\x1f' '
    NF {
        subject=$1
        body=$2
        pr=""
        if (match(subject, /Merge pull request #([0-9]+)/, m)) {
            pr=m[1]
        } else {
            next
        }

        title=""
        n=split(body, lines, /\n/)
        for (i=1; i<=n; i++) {
            line=lines[i]
            gsub(/^[[:space:]]+|[[:space:]]+$/, "", line)
            if (line != "") {
                title=line
                break
            }
        }
        if (title == "") title=subject
        print pr "\t" title
    }
' > "$tmp_prs"

# Directory-level summary from diff.
git diff --name-only "$range" > "$tmp_files_all"
grep -Ev '^(\.agent/|\.codex/|\.claude/)' "$tmp_files_all" > "$tmp_files" || true
if [[ ! -s "$tmp_files" ]]; then
    cp "$tmp_files_all" "$tmp_files"
fi
awk -F/ '
    NF {
        top=$1
        if ($0 ~ /^[^.\/]+$/) top="(root)"
        count[top]++
    }
    END {
        for (k in count) print count[k] "\t" k
    }
' "$tmp_files" | sort -rn > "$tmp_dirs"

commit_count="$(git rev-list --count "$range")"
file_count="$(wc -l < "$tmp_files" | tr -d ' ')"
generated_date="$(date +%F)"

{
    echo "# リリースノート（下書き）"
    echo
    echo "- 生成日: ${generated_date}"
    echo "- 比較範囲: ${from_tag}..${to_ref}"
    echo "- コミット数: ${commit_count}"
    echo "- 変更ファイル数: ${file_count}"
    if [[ -n "$compare_url" ]]; then
        echo "- Compare: ${compare_url}"
    fi
    echo

    echo "## 概要"
    echo "- [ここに今回リリースの要点を1-3行で記載]"
    echo

    echo "## 変更サマリー（差分ベース）"
    if [[ -s "$tmp_dirs" ]]; then
        head -n 10 "$tmp_dirs" | while IFS=$'\t' read -r count dir; do
            echo "- ${dir}: ${count} files"
        done
    else
        echo "- 差分なし"
    fi
    echo

    echo "## 主な Pull Request"
    if [[ -s "$tmp_prs" ]]; then
        while IFS=$'\t' read -r pr title; do
            if [[ -z "$pr" ]]; then
                echo "- ${title}"
            elif [[ -n "$repo_url" ]]; then
                echo "- #${pr}: ${title} (${repo_url}/pull/${pr})"
            else
                echo "- #${pr}: ${title}"
            fi
        done < "$tmp_prs"
    else
        echo "- なし（マージPRが見つからないため、差分ベースで要約する）"
    fi
    echo

    echo "## 変更ファイル（先頭20）"
    if [[ -s "$tmp_files" ]]; then
        head -n 20 "$tmp_files" | sed 's/^/- /'
    else
        echo "- なし"
    fi
    echo

    if [[ "$include_commit_subjects" -eq 1 ]]; then
        echo "## 参考: コミット件名"
        git log --no-merges --pretty='- %s (%h)' "$range"
        echo
    fi

    echo "## 最終確認"
    echo "- [ ] manager/includes/version.inc.php の版数と日付が正しい"
    echo "- [ ] GitHub Actions Build Release Package が成功"
    echo "- [ ] リリース資材(zip)の添付を確認"
} > "${output_path:-/dev/stdout}"
