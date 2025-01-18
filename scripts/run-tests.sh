#!/usr/bin/env bash

set -euo pipefail

# die [<message>]
function die() {
    local s=$?
    printf '%s: %s\n' "${0##*/}" "${1-command failed}" >&2
    ((!s)) && exit 1 || exit "$s"
}

# usage [<error-message>]
function usage() {
    if (($#)); then
        cat >&2 && false || die "$@"
    else
        cat
    fi <<EOF
usage: ${0##*/}            run all tests
       ${0##*/} --phpstan  run PHPStan
       ${0##*/} --phpunit  run PHPUnit tests
       ${0##*/} --build    build and test phar and man page${1:+
}
EOF
    exit
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
    while [[ -z $1 ]] || [[ $1 == [78][0-9] ]]; do
        if type -P "php$1" >/dev/null; then
            versions[${#versions[@]}]=php$1
        fi
        shift
    done
    for php in "${versions[@]-php}"; do
        run "$php" "$@" || return
    done
}

[[ ${BASH_SOURCE[0]} -ef scripts/run-tests.sh ]] ||
    die "must run from root of package folder"

ASSETS=1
FORMATTING=1
PHPSTAN=1
PHPUNIT=1
BUILD=1
if [[ ${1-} == -* ]]; then
    ASSETS=0
    FORMATTING=0
    PHPSTAN=0
    PHPUNIT=0
    BUILD=0
fi
while [[ ${1-} == -* ]]; do
    case "$1" in
    --phpstan)
        PHPSTAN=1
        ;;
    --phpunit)
        PHPUNIT=1
        ;;
    --build)
        BUILD=1
        ;;
    -h | --help)
        usage
        ;;
    *)
        usage "invalid argument: $1"
        ;;
    esac
    shift
done

if ((ASSETS)); then
    run scripts/generate.sh --check
fi

if ((FORMATTING)); then
    run php83 tools/php-cs-fixer check --diff --verbose
    run bin/pretty-php --diff
fi

if ((PHPSTAN)); then
    run_with_php_versions '' 74 vendor/bin/phpstan
fi

if ((PHPUNIT)); then
    run_with_php_versions 84 83 82 81 80 74 vendor/bin/phpunit
fi

if ((BUILD)); then
    run scripts/build.sh
    run scripts/build.sh man
    run scripts/build.sh man worktree
    run_with_php_versions 84 83 82 81 80 74 build/dist/pretty-php.phar --verbose
fi
