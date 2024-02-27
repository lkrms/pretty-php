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
usage: ${0##*/}                        generate everything
       ${0##*/} --assets               generate code and documentation
       ${0##*/} --fixtures             generate test fixtures
       ${0##*/} [--fixtures] phpXY...  use PHP versions to generate fixtures${1:+
}
EOF
    exit
}

# generate <file> <command> [<argument>...]
function generate() {
    local FILE=$1
    shift
    printf '==> generating %s\n' "$FILE"
    "$@" >"$FILE"
}

[[ ${BASH_SOURCE[0]} -ef scripts/generate.sh ]] ||
    die "must run from root of package folder"

ASSETS=1
FIXTURES=1
if [[ ${1-} == -* ]]; then
    ASSETS=0
    FIXTURES=0
fi
while [[ ${1-} == -* ]]; do
    case "$1" in
    --assets)
        ASSETS=1
        ;;
    --fixtures)
        FIXTURES=1
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

if ((FIXTURES)); then
    (($#)) || set -- php83 php82 php81 php80 php74

    for PHP in "$@"; do
        type -P "$PHP" >/dev/null ||
            die "command not found: $PHP"
    done

    rm -rf tests/fixtures/Formatter/versions.json tests/fixtures/Formatter/out/*

    for PHP in "$@"; do
        "$PHP" scripts/generate-test-output.php
    done

    for DIR in tests/fixtures/Command/FormatPhp/preset/*; do
        PRESET=${DIR##*/}
        for FILE in "$DIR"/*.in; do
            bin/pretty-php --no-config --preset "$PRESET" --output "${FILE%.in}.out" "$FILE"
        done
    done
fi

if ((ASSETS)); then
    # yes = collapse options in synopsis to "[options]"
    generate docs/Usage.md bin/pretty-php _md yes
    generate resources/prettyphp-schema.json bin/pretty-php _json_schema "JSON schema for pretty-php configuration files"

    vendor/bin/sli generate builder --forward=format,with,withExtensions,withPsr12,withoutExtensions --force 'Lkrms\PrettyPHP\Formatter'
fi
