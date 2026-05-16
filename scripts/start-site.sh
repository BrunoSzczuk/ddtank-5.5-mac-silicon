#!/usr/bin/env bash
set -e

# Ensure php-fpm runtime dir exists with the right perms
PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo "7.3")
PHP_FPM_BIN=$(command -v php-fpm${PHP_VER} || command -v php-fpm7.3 || command -v php-fpm)
if [ -z "$PHP_FPM_BIN" ]; then
  echo "FATAL: no php-fpm binary found" >&2
  exit 1
fi
mkdir -p /run/php

# XSP4 hosting Novo Site on 8090
( cd /opt/site && xsp4 --port 8090 --address 0.0.0.0 --nonstop ) \
  > /var/log/xsp/site.log 2>&1 &
SITE_PID=$!

# XSP4 hosting Painel on 8091
( cd /opt/painel && xsp4 --port 8091 --address 0.0.0.0 --nonstop ) \
  > /var/log/xsp/painel.log 2>&1 &
PAINEL_PID=$!

# XSP4 hosting Request (Flash client gateway) on 8092
( cd /opt/request && xsp4 --port 8092 --address 127.0.0.1 --nonstop ) \
  > /var/log/xsp/request.log 2>&1 &
REQUEST_PID=$!

# php-fpm
"$PHP_FPM_BIN" --nodaemonize --fpm-config /etc/php/${PHP_VER}/fpm/php-fpm.conf \
  > /var/log/php-fpm.log 2>&1 &
PHP_PID=$!

# nginx in foreground
exec nginx -g 'daemon off;'
