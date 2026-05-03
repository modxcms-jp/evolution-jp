#!/bin/sh
set -e

mkdir -p /var/www/html/temp/logs

exec "$@"
