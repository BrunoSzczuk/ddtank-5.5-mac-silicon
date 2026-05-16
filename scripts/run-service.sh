#!/usr/bin/env bash
# Launch a single emulator service ("Center" | "Fighting" | "Road")
# from its directory using Mono.
set -euo pipefail

service=${1:?service name required (Center|Fighting|Road)}
case "$service" in
  Center|Fighting|Road) ;;
  *) echo "Unknown service: $service"; exit 1 ;;
esac

cd "/opt/ddt/${service}"
exec mono --debug "${service}.Service.exe"
