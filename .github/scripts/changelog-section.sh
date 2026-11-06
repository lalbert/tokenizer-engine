#!/usr/bin/env bash
# Prints the CHANGELOG.md section of a version (without its heading).
# Usage: .github/scripts/changelog-section.sh 1.1.0 [CHANGELOG.md]
set -euo pipefail

version="${1:?usage: $0 VERSION [CHANGELOG.md]}"
file="${2:-CHANGELOG.md}"

section="$(awk -v version="$version" '
    /^## \[/ {
        if (found) exit
        if (index($0, "## [" version "]") == 1) { found = 1; next }
    }
    /^\[[^]]+\]: / { if (found) exit }
    found { print }
' "$file")"

if [ -z "$(printf '%s' "$section" | tr -d '[:space:]')" ]; then
    echo "No section for version $version in $file (run .github/scripts/prepare-release.sh $version first)." >&2
    exit 1
fi

# Trim leading and trailing blank lines.
printf '%s\n' "$section" | sed -e '/./,$!d' | sed -e ':a' -e '/^\n*$/{$d;N;ba' -e '}'
