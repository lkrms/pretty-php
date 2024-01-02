#!/usr/bin/env bash

set -euo pipefail

# die [<message>]
function die() {
    local s=$?
    printf '%s: %s\n' "${0##*/}" "${1-command failed}" >&2
    ((!s)) && exit 1 || exit "$s"
}

# run-in-dir <dir> <command> [<argument>...]
function run-in-dir() (
    local dir=$1 s=0
    shift
    cd "$dir" || exit
    printf '==> [%s] running:%s\n' "$dir" "$(printf ' %q' "$@")" >&2
    "$@" || s=$?
    printf '\n' >&2
    return "$s"
)

[[ ${BASH_SOURCE[0]} -ef scripts/install-tools.sh ]] ||
    die "must run from root of package folder"

case "${0##*/}" in
update-tools.sh)
    subcommand=update
    ;;
*)
    subcommand=install
    ;;
esac

for file in tools/*/composer.lock; do
    run-in-dir "${file%/*}" composer "$subcommand" --no-interaction --no-progress
done
