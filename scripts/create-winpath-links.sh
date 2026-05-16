#!/usr/bin/env bash
# Mono 6.12's MONO_IOMAP=all does NOT convert backslash to forward slash for
# File.Exists() calls. The compiled assemblies hardcode Windows-style paths
# like "map\1001\fore.map", so we create symlinks whose filename literally
# contains backslashes pointing at the real Linux paths.
set -euo pipefail

base=${1:?usage: create-winpath-links.sh <service-dir>}
cd "$base"

if [ ! -d map ]; then
  echo "No map/ folder under $base, skipping."
  exit 0
fi

count=0

# map\<id>\fore.map and map\<id>\dead.map -> map/<id>/<file>
if [ -d map ]; then
  for d in map/*/; do
    id=$(basename "$d")
    for f in "$d"fore.map "$d"dead.map; do
      [ -f "$f" ] || continue
      bn=$(basename "$f")
      linkname="map\\${id}\\${bn}"
      target="map/${id}/${bn}"
      if [ ! -L "$linkname" ]; then
        ln -s "$target" "$linkname"
        count=$((count+1))
      fi
    done
  done
fi

# bomb\<file>.bomb -> bomb/<file>.bomb
if [ -d bomb ]; then
  for f in bomb/*.bomb; do
    [ -f "$f" ] || continue
    bn=$(basename "$f")
    linkname="bomb\\${bn}"
    target="bomb/${bn}"
    if [ ! -L "$linkname" ]; then
      ln -s "$target" "$linkname"
      count=$((count+1))
    fi
  done
fi

echo "Created $count winpath symlinks under $base"
