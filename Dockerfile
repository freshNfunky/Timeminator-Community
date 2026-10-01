# Timeminator Community — zero-infrastructure container.
#
# The release contract says "unzip + run" on PHP shared hosting. This image
# honours that: it is the shipped source copied into Apache's docroot, with
# PDO/MySQL/SQLite enabled, mod_rewrite on so .htaccess works, and no build
# step. Composer is NOT part of the image — it stays Dev-only.

FROM php:8.3-apache

# PHP extensions — PDO for the database, zip for the in-app updater,
# mbstring for safe string handling in views. Everything else ships in
# the base image.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libzip-dev \
        libsqlite3-dev \
        unzip \
    ; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql pdo_sqlite zip; \
    docker-php-ext-enable pdo_mysql pdo_sqlite zip; \
    rm -rf /var/lib/apt/lists/*

# Apache needs mod_rewrite for .htaccess, and AllowOverride All on the
# docroot for the shipped rules to take effect.
RUN a2enmod rewrite headers; \
    { \
      echo '<Directory /var/www/html/>'; \
      echo '  AllowOverride All'; \
      echo '  Require all granted'; \
      echo '</Directory>'; \
    } > /etc/apache2/conf-available/timeminator.conf; \
    a2enconf timeminator

# A sane php.ini for a shared-hosting-shaped app: log to stderr so docker
# logs can see them, keep display_errors off (installer and bootstrap do
# their own debug toggle), lift the upload limit to match CSV import.
RUN { \
      echo 'display_errors = Off'; \
      echo 'log_errors = On'; \
      echo 'error_log = /dev/stderr'; \
      echo 'upload_max_filesize = 10M'; \
      echo 'post_max_size = 12M'; \
      echo 'max_execution_time = 60'; \
      echo 'session.cookie_httponly = On'; \
      echo 'session.cookie_samesite = Lax'; \
    } > /usr/local/etc/php/conf.d/timeminator.ini

WORKDIR /var/www/html

# Copy the shipped source. .dockerignore keeps vendor/ and local
# config.php / data/ out so a `docker build` on a dev checkout produces
# the same image as one on a release zip.
COPY --chown=www-data:www-data . /var/www/html/

# Create the data directory with the right owner (the installer and the
# SQLite driver need to write here). Containerized runs mount a volume
# over this path.
RUN install -d -o www-data -g www-data -m 0775 /var/www/html/data

# config.php does not ship — it is written by the installer on first
# visit (or by a bind-mount from the host).

EXPOSE 80

# php:8.3-apache's default entrypoint runs apache2-foreground; no override.
