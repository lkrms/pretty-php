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

    rm -rf tests/fixtures/Formatter/versions.json tests/fixtures/Formatter/out/*

    for PHP in "$@"; do
        "$PHP" -dshort_open_tag=on scripts/generate-test-output.php
    done
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

EOF
            scripts/list-rules.php
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
        ${FORCE+--force} 'Lkrms\PrettyPHP\Formatter' || STATUS=$?

    exit "$STATUS"
fi
