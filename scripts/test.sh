#!/usr/bin/env bash

set -euo pipefail

# die [<message>]
function die() {
    local s=$?
    printf '%s: %s\n' "${0##*/}" "${1-command failed}" >&2
    ((!s)) && exit 1 || exit "$s"
}

# run <command> [<argument>...]
function run() {
    printf '==> running:%s\n' "$(printf ' %q' "$@")" >&2
    local s=0
    "$@" || s=$?
    printf '\n' >&2
    return "$s"
}

function run_with_php_versions() {
    local php versions=()
    while [[ $1 == [78][0-9] ]]; do
        if type -P "php$1" >/dev/null; then
            versions[${#versions[@]}]=php$1
        fi
        shift
    done
    for php in "${versions[@]-php}"; do
        run "$php" "$@" || return
    done
}

[[ ${BASH_SOURCE[0]} -ef scripts/test.sh ]] ||
    die "must run from root of package folder"

run bin/pretty-php --diff
run_with_php_versions 82 74 vendor/bin/phpstan
run_with_php_versions 82 81 80 74 vendor/bin/phpunit

run scripts/build.sh
run scripts/build.sh man
run scripts/build.sh man worktree
run_with_php_versions 82 81 80 74 build/dist/pretty-php.phar --verbose
