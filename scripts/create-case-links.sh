#!/usr/bin/env bash
# Linux is case-sensitive; the painel & site .aspx pages reference master
# pages and configs with inconsistent casing (e.g. ~/Homen.master vs the
# real Homen.Master on disk). Create lowercase symlinks so both resolve.
set -euo pipefail

base=${1:?usage: create-case-links.sh <directory>}
cd "$base"

count=0
# Mono's WebForms compiler enumerates the directory and identifies master
# pages by the *real* on-disk filename, then compares against the casing in
# MasterPageFile=. With a symlink Homen.master -> Homen.Master it still
# resolves to "Homen.Master" and treats them as separate files. Use a real
# copy instead so both casings co-exist as distinct files on disk.
copy_alt_case() {
  local file=$1
  local dir bn stem ext lower_ext lower_stem
  dir=$(dirname "$file")
  bn=$(basename "$file")
  stem=${bn%.*}
  ext=${bn##*.}
  lower_ext=$(echo "$ext" | tr '[:upper:]' '[:lower:]')
  upper_ext_first=$(echo "${ext:0:1}" | tr '[:lower:]' '[:upper:]')${ext:1}
  lower_stem=$(echo "$stem" | tr '[:upper:]' '[:lower:]')
  # Variant 1: same stem, lowercase extension (Homen.Master -> Homen.master)
  for variant in "${stem}.${lower_ext}" "${stem}.${upper_ext_first}" "${lower_stem}.${lower_ext}"; do
    if [ "$variant" != "$bn" ] && [ ! -e "$dir/$variant" ]; then
      cp -p "$file" "$dir/$variant"
      count=$((count+1))
    fi
  done
}

for f in $(find . -type f \( -name '*.Master' -o -name '*.master' \)); do
  copy_alt_case "$f"
done

# Also for .aspx, .ashx, .asmx, .ascx (referenced via Server.MapPath etc.)
for f in $(find . -type f \( -name '*.aspx' -o -name '*.ashx' -o -name '*.asmx' -o -name '*.ascx' \)); do
  copy_alt_case "$f"
done

echo "Created $count case-folded copies under $base"
