#!/usr/bin/env bash

set -euo pipefail
shopt -s extglob

function _die() {
    local STATUS=$?
    echo "${0##*/}: ${1-command failed}" >&2
    (exit "$STATUS") && false || exit
}

function _realpath() {
    local FILE=$1 DIR
    while [[ -L $FILE ]]; do
        DIR=$(dirname "$FILE") &&
            FILE=$(readlink "$FILE") || return
        [[ $FILE == /* ]] || FILE=$DIR/$FILE
    done
    DIR=$(dirname "$FILE") &&
        DIR=$(cd -P "$DIR" &>/dev/null && pwd) &&
        echo "$DIR/${FILE##*/}"
}

FILE=$(_realpath "${BASH_SOURCE[0]}") &&
    cd "${FILE%/*}" || _die "error resolving ${BASH_SOURCE[0]}"

BUILD_DIR=build/phar
BUILD_TARGET=${BUILD_DIR%/*}/${PWD##*/}.phar
rm -rf "$BUILD_DIR" "$BUILD_TARGET" &&
    mkdir -pv "$BUILD_DIR" &&
    cp -Rv !(build*|docs|phpstan*|phpunit*|tests*|var|vendor|LICENSE*|README*|*.md|*.txt|*.code-workspace) "$BUILD_DIR/" &&
    composer install -d "$BUILD_DIR" --no-dev ||
    _die "error preparing $PWD/$BUILD_DIR"

php -d phar.readonly=off vendor/bin/phar-composer build "$BUILD_DIR/" "$BUILD_TARGET"
