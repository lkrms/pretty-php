#!/usr/bin/env bash

set -euo pipefail
shopt -s extglob nullglob globstar

# fail [<status>]
function fail() {
    local s
    ((s = ${1-})) && return "$s" || return 1
}

# die [<message>]
function die() {
    local s=$?
    printf '%s: %s\n' "${0##*/}" "${1-command failed}" >&2
    fail "$s" || exit
}

type -P realpath >/dev/null ||
    # realpath <filename>
    function realpath() {
        local file=$1 dir
        while [[ -L $file ]]; do
            dir=$(dirname "$file") &&
                file=$(readlink "$file") || return
            [[ $file == /* ]] || file=$dir/$file
        done
        dir=$(dirname "$file") &&
            dir=$(cd -P "$dir" &>/dev/null && pwd) &&
            printf '%s/%s' "$dir" "${file##*/}"
    }

REPO=$(realpath "${BASH_SOURCE[0]}") &&
    REPO=${REPO%/*/*} &&
    [[ ${BASH_SOURCE[0]} -ef $REPO/scripts/build.sh ]] &&
    cd "$REPO" || die "error resolving ${BASH_SOURCE[0]}"

PACKAGE=${REPO##*/}
LATEST=0
PHAR=0
TAR=0
while (($#)); do
    case "$1" in
    --latest)
        LATEST=1
        ;;
    --phar)
        PHAR=1
        ;;
    --tar)
        TAR=1
        ;;
    *)
        die "invalid argument: $1"
        ;;
    esac
    shift
done
((PHAR || TAR)) || PHAR=1

SRC_DIR=$REPO
BUILD_DIR=build/$PACKAGE
DIST_DIR=build/dist
DIST_MANIFEST=$DIST_DIR/manifest.json

if ((LATEST)); then
    # Get the closest annotated version tag reachable from 'main'
    VERSION=$(git describe --match "v[0-9]*" --abbrev=0 main) ||
        die "error finding latest version of $PACKAGE"
    # Clone the repo into a temporary directory and check out $VERSION
    TEMP_DIR=$(mktemp -d)/$PACKAGE &&
        trap 'rm -rf "${TEMP_DIR%/*}"' EXIT &&
        git clone --progress "$REPO" "$TEMP_DIR" &&
        git -C "$TEMP_DIR" -c advice.detachedHead=false checkout --progress "$VERSION" &&
        composer install -d "$TEMP_DIR" --no-plugins --no-interaction &&
        SRC_DIR=$TEMP_DIR &&
        # We need to complete the build here because composer won't pick up the
        # root package version from Git otherwise
        BUILD_DIR=$SRC_DIR/build/$PACKAGE ||
        die "error checking out $PACKAGE $VERSION"
else
    printf '==> Getting %s version from repository\n' "$PACKAGE"
    VERSION=$(git describe --dirty --match "v[0-9]*" --long |
        tee /dev/stderr |
        awk '/^v?[0-9]+(\.[0-9]+){0,3}-[0-9]+-g[0-9a-f]+(-dirty)?$/ { sub(/-0-/, "-"); sub(/g/, ""); print }') ||
        VERSION=
    printf ' -> %s version: %s\n' "$PACKAGE" "${VERSION:-<none>}"
fi

rm -rf "$BUILD_DIR" "$DIST_MANIFEST"
mkdir -pv "$BUILD_DIR" "$DIST_DIR"

cp -Rv "$SRC_DIR"/!(apigen|build|docs|phpstan*|phpunit*|scripts|tests*|var|vendor|LICENSE*|README*|*.md|*.code-workspace) "$BUILD_DIR/" &&
    #  Remove --classmap-authoritative if support for classes generated at runtime is required
    composer install -d "$BUILD_DIR" --no-plugins --no-interaction --no-dev --optimize-autoloader --classmap-authoritative &&
    rm -fv "$BUILD_DIR"/**/.DS_Store &&
    rm -fv "$BUILD_DIR"/vendor/**/.gitignore &&
    rm -rfv "$BUILD_DIR"/vendor/bin &&
    rm -rfv "$BUILD_DIR"/vendor/*/*/{docs,phpstan*,phpunit*,tests*,LICENSE*,README*,*.md,composer.json,.git?*} &&
    rm -rfv "$BUILD_DIR"/vendor/lkrms/util/{bin,lib,src/Sync} ||
    die "error preparing $REPO/$BUILD_DIR"
echo

BUILD=$PACKAGE${VERSION:+-$VERSION}
MANIFEST=(package "$PACKAGE" version "$VERSION")

if ((TAR)); then
    BUILD_TAR=$DIST_DIR/$BUILD.tar.gz
    rm -f "$BUILD_TAR"
    echo "==> Creating $BUILD_TAR"
    tar -czvf "$BUILD_TAR" -C "${BUILD_DIR%/*}" "${BUILD_DIR##*/}"
    MANIFEST+=(assets tar "${BUILD_TAR#"$DIST_DIR/"}")
    echo
    echo "==> Successfully created $BUILD_TAR"
    echo
fi

if ((PHAR)); then
    BUILD_PHAR=$DIST_DIR/$BUILD.phar
    rm -f "$BUILD_PHAR"
    php -d phar.readonly=off "$SRC_DIR"/vendor/bin/phar-composer build "$BUILD_DIR/" "$BUILD_PHAR"
    MANIFEST+=(assets phar "${BUILD_PHAR#"$DIST_DIR/"}")
    echo
fi

./scripts/create-manifest "${MANIFEST[@]}" >"$DIST_MANIFEST"
echo "==> Build manifest created at $REPO/$DIST_MANIFEST"
