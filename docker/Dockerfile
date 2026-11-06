# Build from the repository root: docker build -f docker/Dockerfile .
# WordPress and PHP versions must match scripts/playground.mjs, which is what the
# automated tests run against.

FROM node:24-slim AS frontend
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY scripts/build-frontend.mjs scripts/
COPY frontend frontend
RUN node scripts/build-frontend.mjs

FROM wordpress:cli-2.12.0-php8.4 AS cli

FROM wordpress:7.1.4-php8.4-apache
# The base image only gets OS fixes when it's republished, so apply them here.
# Rebuilding picks up new ones.
RUN apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get upgrade -y --no-install-recommends \
 && rm -rf /var/lib/apt/lists/*
COPY --from=cli /usr/local/bin/wp /usr/local/bin/wp

WORKDIR /usr/src/wordpress
# Code ships only in the image, so nothing can change it on a running site.
RUN rm -rf wp-content/plugins/akismet wp-content/plugins/hello.php \
      wp-content/themes/twenty* wp-config-sample.php readme.html license.txt
COPY wordpress/plugins/naturedex-core wp-content/plugins/naturedex-core
COPY wordpress/themes/naturedex wp-content/themes/naturedex
COPY --from=frontend /build/wordpress/themes/naturedex/assets/dist wp-content/themes/naturedex/assets/dist
COPY docker/wp-config.php wp-config.php
COPY docker/htaccess .htaccess
RUN printf 'ok\n' > healthz.html && chown -R www-data:www-data /usr/src/wordpress

COPY docker/php.ini /usr/local/etc/php/conf.d/naturedex.ini
# Loads after the image's security.conf, which would otherwise override it.
COPY docker/apache.conf /etc/apache2/conf-enabled/zz-naturedex.conf
COPY docker/entrypoint.sh /usr/local/bin/naturedex-entrypoint
COPY docker/bootstrap.sh /usr/local/bin/naturedex-bootstrap
RUN a2enmod headers rewrite

WORKDIR /var/www/html
ENTRYPOINT ["naturedex-entrypoint"]
CMD ["apache2-foreground"]
