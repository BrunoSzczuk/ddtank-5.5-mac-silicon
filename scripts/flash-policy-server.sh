#!/usr/bin/env sh
# Tiny Flash Socket Policy File Server (port 843).
# When a Flash AS3 client calls Socket.connect(host, port), the Flash runtime
# first sends "<policy-file-request/>\0" to 843 of the same host. We answer
# with a permissive cross-domain policy so the runtime stops gating sockets.
set -eu

POLICY='<?xml version="1.0"?>
<!DOCTYPE cross-domain-policy SYSTEM "/xml/dtds/cross-domain-policy.dtd">
<cross-domain-policy>
  <site-control permitted-cross-domain-policies="master-only"/>
  <allow-access-from domain="*" to-ports="*" />
</cross-domain-policy>
'

# socat is available in alpine images via apk add socat; fall back to ncat if not.
exec socat -T 5 TCP-LISTEN:843,reuseaddr,fork \
  SYSTEM:"printf '%s\\0' '$POLICY'"
