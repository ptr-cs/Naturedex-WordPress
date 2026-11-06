#!/bin/sh
# Installs and seeds the site. Safe to run again: it skips what already exists.
set -eu
wp() { command wp --allow-root --path=/var/www/html "$@"; }

if ! wp core is-installed; then
  wp core install \
    --url="$NATUREDEX_HOME_URL" \
    --title=Naturedex \
    --admin_user="$NATUREDEX_ADMIN_USER" \
    --admin_password="$NATUREDEX_ADMIN_PASSWORD" \
    --admin_email="$NATUREDEX_ADMIN_EMAIL" \
    --skip-email
  # The display name appears on field notes and would otherwise be the username.
  wp user update "$NATUREDEX_ADMIN_USER" \
    --display_name='Naturedex Field Notes' --nickname='Naturedex Field Notes'
fi
wp plugin activate naturedex-core
wp theme activate naturedex
wp naturedex seed
