#!/usr/bin/env bash

set -euo pipefail
shopt -s extglob globstar nullglob

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

PACKAGE=${PWD##*/}
VERSION=$(git describe 2>/dev/null | grep -Eo '^v?[0-9]+(\.[0-9]+){2,}') || VERSION=
BUILD=$PACKAGE${VERSION:+-$VERSION}
BUILD_DIR=build/$PACKAGE
BUILD_TAR=${BUILD_DIR%/*}/$BUILD.tar.gz
BUILD_PHAR=${BUILD_DIR%/*}/$BUILD.phar
rm -rf "$BUILD_DIR" "$BUILD_TAR" "$BUILD_PHAR" &&
    mkdir -pv "$BUILD_DIR" &&
    cp -Rv !(build*|docs|phpdoc*|phpstan*|phpunit*|tests*|var|vendor|LICENSE*|README*|*.md|*.txt|*.code-workspace) "$BUILD_DIR/" &&
    { [[ -z $VERSION ]] ||
        export COMPOSER_ROOT_VERSION=$VERSION; } &&
    # Remove --classmap-authoritative if support for classes generated at runtime is required
    composer install -d "$BUILD_DIR" --no-dev --no-plugins --optimize-autoloader --classmap-authoritative &&
    rm -fv "$BUILD_DIR"/**/.DS_Store &&
    rm -fv "$BUILD_DIR"/vendor/**/.gitignore &&
    rm -rfv "$BUILD_DIR"/vendor/bin &&
    rm -rfv "$BUILD_DIR"/vendor/*/*/{docs,phpdoc*,phpstan*,phpunit*,tests*,LICENSE*,README*,*.md,*.txt,composer.json,.github} ||
    _die "error preparing $PWD/$BUILD_DIR"
echo

echo "==> Creating $BUILD_TAR"
tar -czvf "$BUILD_TAR" --strip-components 1 "$BUILD_DIR"
echo
echo "==> Successfully created $BUILD_TAR"
echo

php -d phar.readonly=off vendor/bin/phar-composer build "$BUILD_DIR/" "$BUILD_PHAR" &&
    { [[ -z $VERSION ]] ||
        { rm -fv "${BUILD_PHAR%-"$VERSION".phar}.phar" &&
            cp -av "$BUILD_PHAR" "${BUILD_PHAR%-"$VERSION".phar}.phar"; }; }
