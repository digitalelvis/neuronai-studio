#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DOCS="$ROOT/docs"
SUMMARY="$DOCS/SUMMARY.md"
PENDING="$DOCS/assets/screenshots/PENDING.md"
ERRORS=0

echo "Validating documentation..."

if [[ ! -f "$SUMMARY" ]]; then
  echo "ERROR: Missing $SUMMARY"
  exit 1
fi

# Extract markdown links from SUMMARY.md: [text](path)
while IFS= read -r link; do
  # Skip external links
  if [[ "$link" == http* ]]; then
    continue
  fi
  # Remove anchor fragment
  path="${link%%#*}"
  target="$DOCS/$path"
  if [[ ! -f "$target" ]]; then
    echo "ERROR: SUMMARY link target not found: $path"
    ERRORS=$((ERRORS + 1))
  fi
done < <(grep -oE '\]\([^)]+\)' "$SUMMARY" | sed 's/](//;s/)$//')

# Verify SCREENSHOT tags in docs are registered in PENDING.md
if [[ -f "$PENDING" ]]; then
  while IFS= read -r tag; do
    if ! grep -q "$tag" "$PENDING"; then
      echo "WARN: Screenshot tag not in PENDING.md: $tag"
    fi
  done < <(grep -rh 'SCREENSHOT:' "$DOCS" --include='*.md' | sed 's/.*SCREENSHOT: //' | sed 's/ .*//')
fi

# Check .gitbook.yaml exists
if [[ ! -f "$DOCS/.gitbook.yaml" ]]; then
  echo "ERROR: Missing docs/.gitbook.yaml"
  ERRORS=$((ERRORS + 1))
fi

if [[ $ERRORS -gt 0 ]]; then
  echo "Documentation validation failed with $ERRORS error(s)."
  exit 1
fi

echo "Documentation validation passed."
