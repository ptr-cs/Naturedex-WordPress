#!/bin/sh
set -eu
# The official entrypoint fills the web root only for Apache. One-off tasks such
# as the cron job and site bootstrap run the same image with a different command,
# so they need the files too.
if [ ! -e /var/www/html/index.php ]; then
  cp -a /usr/src/wordpress/. /var/www/html/
fi
exec docker-entrypoint.sh "$@"
