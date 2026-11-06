#!/usr/bin/env bash
# Turns the [Unreleased] section of CHANGELOG.md into a dated release section
# and updates the comparison links.
# Usage: .github/scripts/prepare-release.sh 1.1.0 [YYYY-MM-DD]
set -euo pipefail

version="${1:?usage: $0 VERSION [DATE]}"
date="${2:-$(date -u +%F)}"
file="CHANGELOG.md"
repo="https://github.com/lalbert/tokenizer-engine"

if ! [[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?$ ]]; then
    echo "Invalid version \"$version\" (expected semver, e.g. 1.1.0)." >&2
    exit 1
fi

if grep -q "^## \[$version\]" "$file"; then
    echo "$file already has a section for $version." >&2
    exit 1
fi

previous="$(grep -m1 -oE '^## \[[0-9][^]]*\]' "$file" | sed -E 's/^## \[(.*)\]$/\1/')"
if [ -z "$previous" ]; then
    echo "No previous version found in $file." >&2
    exit 1
fi

awk -v version="$version" -v date="$date" -v previous="$previous" -v repo="$repo" '
    /^## \[Unreleased\]/ && !done_heading {
        print "## [Unreleased]"; print ""; print "## [" version "] - " date; done_heading = 1; next
    }
    /^\[Unreleased\]: / {
        print "[Unreleased]: " repo "/compare/" version "...HEAD"
        print "[" version "]: " repo "/compare/" previous "..." version
        next
    }
    { print }
' "$file" > "$file.tmp" && mv "$file.tmp" "$file"

.github/scripts/changelog-section.sh "$version" "$file" > /dev/null
echo "CHANGELOG.md prepared for $version ($date). Open a pull request, merge it, then push the tag:"
echo "  git tag -a $version -m $version && git push origin $version"
