#!/usr/bin/env bash
# Apply GitHub branch rulesets from .github/rulesets/*.json
# Requires: gh CLI, admin access to the repository
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
RULESETS_DIR="${ROOT}/.github/rulesets"
DRY_RUN=false

usage() {
  cat <<'EOF'
Usage: apply-branch-rules.sh [--dry-run]

Creates or updates repository rulesets defined in .github/rulesets/*.json.

Environment:
  GITHUB_REPOSITORY  Optional. Defaults to gh repo view --json nameWithOwner.

Requires gh auth with admin:repo or repo scope.
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --dry-run)
      DRY_RUN=true
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

if ! command -v gh >/dev/null 2>&1; then
  echo "Error: gh CLI is not installed. See https://cli.github.com/" >&2
  exit 1
fi

if ! gh auth status >/dev/null 2>&1; then
  echo "Error: gh is not authenticated. Run: gh auth login" >&2
  exit 1
fi

REPO="${GITHUB_REPOSITORY:-$(gh repo view --json nameWithOwner -q .nameWithOwner)}"
OWNER="${REPO%%/*}"
NAME="${REPO##*/}"

strip_comment_fields() {
  python3 -c '
import json, sys
data = json.load(sys.stdin)
data.pop("_comment", None)
print(json.dumps(data))
'
}

find_ruleset_id_by_name() {
  local ruleset_name="$1"
  gh api "repos/${OWNER}/${NAME}/rulesets" --paginate \
    | python3 -c "
import json, sys
name = sys.argv[1]
for item in json.load(sys.stdin):
    if item.get('name') == name:
        print(item['id'])
        break
" "$ruleset_name"
}

apply_ruleset() {
  local file="$1"
  local payload
  payload="$(strip_comment_fields < "$file")"
  local ruleset_name
  ruleset_name="$(echo "$payload" | python3 -c 'import json,sys; print(json.load(sys.stdin)["name"])')"

  echo "→ ${ruleset_name}"

  if $DRY_RUN; then
    echo "$payload" | python3 -m json.tool
    echo
    return 0
  fi

  local existing_id
  existing_id="$(find_ruleset_id_by_name "$ruleset_name" || true)"

  if [[ -n "$existing_id" ]]; then
    echo "  Updating ruleset id ${existing_id}"
    gh api \
      --method PUT \
      "repos/${OWNER}/${NAME}/rulesets/${existing_id}" \
      --input - <<< "$payload" >/dev/null
  else
    echo "  Creating ruleset"
    gh api \
      --method POST \
      "repos/${OWNER}/${NAME}/rulesets" \
      --input - <<< "$payload" >/dev/null
  fi

  echo "  Done."
}

shopt -s nullglob
files=("${RULESETS_DIR}"/*.json)
if [[ ${#files[@]} -eq 0 ]]; then
  echo "No ruleset JSON files found in ${RULESETS_DIR}" >&2
  exit 1
fi

echo "Repository: ${REPO}"
if $DRY_RUN; then
  echo "Mode: dry-run (no API writes)"
fi
echo

for file in "${files[@]}"; do
  apply_ruleset "$file"
done

echo
echo "Rulesets applied. Verify in GitHub: Settings → Rules → Rulesets"
echo "Remember: enable Actions bypass for release-it if Release workflow fails on main."
