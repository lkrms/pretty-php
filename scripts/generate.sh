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
       ${0##*/} --check                check code and documentation
       ${0##*/} --fixtures             generate test fixtures
       ${0##*/} --preset-fixtures      generate preset test fixtures
       ${0##*/} [--fixtures] phpXY...  use PHP versions to generate fixtures${1:+
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

# generate <file> <command> [<argument>...]
function generate() {
    local FILE=$1
    shift
    if ((CHECK)); then
        if [[ ! -f $FILE ]]; then
            printf '==> would create %s\n' "$FILE"
            STATUS=1
            return
        fi
        if ! diff --unified --label "$FILE" --label "$FILE" --color=always "$FILE" <("$@"); then
            printf '==> would replace %s\n' "$FILE"
            STATUS=1
        else
            printf '==> nothing to do: %s\n' "$FILE"
        fi
        return
    fi
    printf '==> generating %s\n' "$FILE"
    "$@" >"$FILE"
}

[[ ${BASH_SOURCE[0]} -ef scripts/generate.sh ]] ||
    die "must run from root of package folder"

ASSETS=1
CHECK=0
FIXTURES=1
PRESET_FIXTURES=1
STATUS=0
if [[ ${1-} == -* ]]; then
    ASSETS=0
    FIXTURES=0
    PRESET_FIXTURES=0
fi
while [[ ${1-} == -* ]]; do
    case "$1" in
    --assets)
        ASSETS=1
        ;;
    --check)
        ASSETS=1
        CHECK=1
        FIXTURES=0
        PRESET_FIXTURES=0
        ;;
    --fixtures)
        FIXTURES=1
        PRESET_FIXTURES=1
        CHECK=0
        ;;
    --preset-fixtures)
        PRESET_FIXTURES=1
        CHECK=0
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

if ((FIXTURES)); then (
    (($#)) || set -- php84 php83 php82 php81 php80 php74

    for PHP in "$@"; do
        type -P "$PHP" >/dev/null ||
            die "command not found: $PHP"
    done

    run rm -rf tests/fixtures/Formatter/*.json tests/fixtures/Formatter/out/*

    for PHP in "$@"; do
        run "$PHP" -dshort_open_tag=on scripts/update-out-fixtures.php
    done

    run scripts/update-fixture-index.php "$#"
); fi

if ((PRESET_FIXTURES)); then (
    (($#)) || set -- php

    for DIR in tests/fixtures/App/PrettyPHPCommand/preset/*; do
        PRESET=${DIR##*/}
        for FILE in "$DIR"/*.in; do
            printf '==> generating %s\n' "${FILE%.in}.out"
            "$1" -dshort_open_tag=on bin/pretty-php -qq --no-config --preset "$PRESET" --output "${FILE%.in}.out" "$FILE"
        done
    done
); fi

if ((ASSETS)); then
    (($#)) || set -- php

    function list-rules() {
        cat <<'EOF' &&
# Rules

Formatting rules applied by `pretty-php` are as follows.

This file is generated by [generate.sh][], so any changes should be made there or to [get-rules.php][].

> Suggested priority ranges:
>
> - 0-99: normalise content, suppress illegal whitespace, save data for later
> - 100-199: apply horizontal whitespace, unconditional vertical whitespace
> - 200-299: apply vertical whitespace
> - 300-399: apply indentation, register alignment callbacks
> - 400-499: apply preset-specific formatting
> - 500-599: process blocks
> - 600: process alignment callbacks
> - 601-699: process other callbacks
> - 900-999: finalise

EOF
            scripts/get-rules.php &&
            cat <<'EOF'

[generate.sh]: ../scripts/generate.sh
[get-rules.php]: ../scripts/get-rules.php
EOF
    }

    # yes = collapse options in synopsis to "[options]"
    generate docs/Usage.md "$1" bin/pretty-php _md yes
    generate docs/Rules.md list-rules
    generate resources/prettyphp-schema.json "$1" bin/pretty-php _json_schema "JSON schema for pretty-php configuration files"

    if ((CHECK)); then
        unset FORCE
    else
        FORCE=
    fi

    "$1" vendor/bin/sli generate builder \
        --forward=format,with,withExtensions,withPsr12,withoutExtensions \
        --desc '' --api ${FORCE+--force} 'Lkrms\PrettyPHP\Formatter' ||
        STATUS=$?

    exit "$STATUS"
fi
