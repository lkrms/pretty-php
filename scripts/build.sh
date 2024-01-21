#!/usr/bin/env bash

set -euo pipefail
shopt -s extglob

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
usage: ${0##*/} [worktree]        build the current working tree
       ${0##*/} man               build a man page for the current working tree
       ${0##*/} man worktree      build the working tree and a man page for it
       ${0##*/} [man] latest      build the closest version reachable from HEAD
       ${0##*/} [man] v<VERSION>  build a tagged version${1:+
}
EOF
    exit
}

[[ ${BASH_SOURCE[0]} -ef scripts/build.sh ]] ||
    die "must run from root of package folder"

PACKAGE=$(composer show --self --name-only) &&
    PACKAGE=${PACKAGE##*/} ||
    die "error getting package name"

REPO=$PWD
DIST=build/dist
PHAR=$DIST/$PACKAGE.phar
MAN=$DIST/$PACKAGE.1

BUILD_PHAR=$(($# ? 0 : 1))
BUILD_MAN=0
FROM_GIT=0
VERSION=
while (($#)); do
    case "$1" in
    worktree)
        BUILD_PHAR=1
        FROM_GIT=0
        VERSION=
        ;;
    latest)
        BUILD_PHAR=1
        FROM_GIT=1
        VERSION=
        ;;
    v[0-9]*)
        BUILD_PHAR=1
        FROM_GIT=1
        VERSION=$1
        ;;
    man)
        BUILD_MAN=1
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

if [[ ${CI-} == true ]] && ((!FROM_GIT)); then
    printf '==> building in CI environment: %s\n' "$REPO"
    TEMP_DIR=$REPO
    rm -rf "$DIST"
else
    TEMP_DIR=$(mktemp -d) &&
        trap 'rm -rf "$TEMP_DIR"' EXIT ||
        die "error creating temporary directory"
fi

if ((FROM_GIT)); then
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
elif ((BUILD_PHAR)) && [[ $TEMP_DIR != "$REPO" ]]; then
    printf '==> copying %s working tree to %s\n' "$PACKAGE" "$TEMP_DIR"
    cp -Lpr "$REPO"/!(build|tools|var|vendor) "$TEMP_DIR" ||
        die "error copying working tree to $TEMP_DIR"
fi

if ((BUILD_PHAR)); then
    printf '==> installing %s production dependencies in %s\n' "$PACKAGE" "$TEMP_DIR"
    composer install -d "$TEMP_DIR" --no-plugins --no-interaction --no-dev

    printf "==> running 'box compile' in %s\\n" "$TEMP_DIR"
    { [[ -f $TEMP_DIR/box.json ]] ||
        [[ -f $TEMP_DIR/box.json.dist ]] ||
        cp -v "$REPO/box.json" "$TEMP_DIR/box.json"; } &&
        php -d phar.readonly=off tools/box compile -d "$TEMP_DIR" --no-interaction

    printf '==> finalising build\n'
    TEMP_PHAR=("$TEMP_DIR/$DIST"/*.phar)
    [[ ${#TEMP_PHAR[@]} -eq 1 ]] ||
        die "output missing or invalid"
    if [[ ! $PHAR -ef $TEMP_PHAR ]]; then
        rm -f "$PHAR" &&
            mkdir -pv "$DIST" &&
            cp -pv "$TEMP_PHAR" "$PHAR" ||
            die "error copying $TEMP_PHAR to $PHAR"
    fi

    printf ' -> PHP archive created at %s/%s\n' "$REPO" "$PHAR"
fi

if ((BUILD_MAN)); then
    # Run the script from the bin directory to ensure the command name in the
    # man page isn't <package>.phar
    if ((BUILD_PHAR)); then
        RUN=$TEMP_DIR/bin/$PACKAGE
    else
        RUN=bin/$PACKAGE
        # If packages are installed, assume they're current, otherwise install
        # production dependencies
        if ! composer show 2>/dev/null | grep . >/dev/null; then
            printf '==> installing %s production dependencies in %s\n' "$PACKAGE" "$REPO"
            composer install --no-plugins --no-interaction --no-dev
        fi
    fi
    printf '==> generating man page for %s\n' "$RUN"
    rm -f "$MAN" &&
        mkdir -pv "$DIST" &&
        "$RUN" _man | pandoc --standalone --to man -o "$MAN" ||
        die "error creating man page at $MAN"

    printf ' -> man page created at %s/%s\n' "$REPO" "$MAN"
fi
