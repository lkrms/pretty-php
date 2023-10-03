#!/usr/bin/env bash

set -euo pipefail

die() {
    printf '%s: %s\n' "$0" "$1" >&2
    exit 1
}

[[ ${BASH_SOURCE[0]} -ef scripts/generate.sh ]] ||
    die "must run from root of package folder"

(($#)) || set -- php82 php81 php80 php74

for PHP in "$@"; do
    type -P "$PHP" >/dev/null ||
        die "command not found: $PHP"
done

rm -rf tests/fixtures/in/versions.json tests/fixtures/out.*

for PHP in "$@"; do
    "$PHP" scripts/generate-test-output.php
done

FILE=docs/Usage.md
printf '==> generating %s\n' "$FILE"
bin/pretty-php _md >"$FILE"
