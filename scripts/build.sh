#!/usr/bin/env bash

set -euo pipefail
shopt -s extglob

# usage [<error-message>]
function usage() {
    if (($#)); then
        cat >&2 && false || die "$@"
    else
        cat
    fi <<EOF
usage: ${0##*/}             build the current working tree
       ${0##*/} latest      build the closest version reachable from HEAD
       ${0##*/} v<VERSION>  build a given version${1:+
}
EOF
    exit
}

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
    cd "$REPO" ||
    die "error resolving ${BASH_SOURCE[0]}"

PACKAGE=$(composer show --self --name-only) &&
    PACKAGE=${PACKAGE##*/} ||
    die "error getting package name"

LATEST=0
VERSION=
while (($#)); do
    case "$1" in
    latest)
        LATEST=1
        ;;
    v[0-9]*)
        LATEST=1
        VERSION=$1
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

TEMP_DIR=$(mktemp -d) &&
    trap 'rm -rf "$TEMP_DIR"' EXIT ||
    die "error creating temporary directory"

if ((LATEST)); then
    # Get the closest annotated version tag reachable from HEAD
    [[ -n $VERSION ]] || {
        printf "==> getting closest %s version tag from 'git describe'\\n" "$PACKAGE"
        VERSION=$(git describe --match "v[0-9]*" --abbrev=0 |
            tee /dev/stderr)
    } || die "error getting $PACKAGE version"
    # Create an empty repo in $TEMP_DIR, fetch $VERSION into it from $REPO, and
    # check it out
    printf '==> checking out %s %s in %s\n' "$PACKAGE" "$VERSION" "$TEMP_DIR"
    git init "$TEMP_DIR" &&
        git -C "$TEMP_DIR" remote add origin "$REPO" &&
        git -C "$TEMP_DIR" fetch --progress --no-recurse-submodules --depth=1 origin +refs/tags/"$VERSION":refs/tags/"$VERSION" &&
        git -C "$TEMP_DIR" -c advice.detachedHead=false checkout --progress --force refs/tags/"$VERSION" ||
        die "error checking out $PACKAGE $VERSION"
else
    printf "==> getting state of %s working tree from 'git describe'\\n" "$PACKAGE"
    VERSION=$(git describe --dirty --match "v[0-9]*" --long |
        tee /dev/stderr |
        awk '/^v?[0-9]+(\.[0-9]+){0,3}-[0-9]+-g[0-9a-f]+(-dirty)?$/ { sub(/-0-/, "-"); sub(/g/, ""); print }') ||
        VERSION=
    printf ' -> %s version: %s\n' "$PACKAGE" "${VERSION:-<none>}"
    printf '==> copying %s working tree to %s\n' "$PACKAGE" "$TEMP_DIR"
    cp -Lpr "$REPO"/!(apigen|box|build|tests*|var|vendor) "$TEMP_DIR" ||
        die "error copying working tree to $TEMP_DIR"
fi

DIST_DIR=build/dist
DIST_MANIFEST=$DIST_DIR/manifest.json
BUILD=$PACKAGE${VERSION:+-$VERSION}
PHAR=$DIST_DIR/$BUILD.phar
MANIFEST=(
    package "$PACKAGE"
    version "$VERSION"
    assets phar "${PHAR#"$DIST_DIR/"}"
)

printf '==> installing %s production dependencies in %s\n' "$PACKAGE" "$TEMP_DIR"
composer install -d "$TEMP_DIR" --no-plugins --no-interaction --no-dev
printf '==> installing humbug/box in %s/box\n' "$REPO"
composer install -d box --no-plugins --no-interaction
printf "==> running 'box compile' in %s\\n" "$TEMP_DIR"
{ [[ -f $TEMP_DIR/box.json ]] ||
    [[ -f $TEMP_DIR/box.json.dist ]] ||
    cp -v "$REPO/box.json" "$TEMP_DIR/box.json"; } &&
    php -d phar.readonly=off box/vendor/bin/box compile -d "$TEMP_DIR" --no-interaction

printf '==> finalising build\n'
TEMP_PHAR=$TEMP_DIR/build/dist/pretty-php.phar
rm -f "$DIST_MANIFEST" "$PHAR" &&
    mkdir -pv "$DIST_DIR" &&
    cp -pv "$TEMP_PHAR" "$PHAR" ||
    die "error copying $TEMP_PHAR to $PHAR"
printf ' -> PHP archive created at %s/%s\n' "$REPO" "$PHAR"

./scripts/create-manifest.php "${MANIFEST[@]}" >"$DIST_MANIFEST" &&
    printf ' -> build manifest created at %s/%s\n' "$REPO" "$DIST_MANIFEST" ||
    die 'unable to create build manifest'
