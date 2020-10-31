# Read PHP_VERSION and PROJECT_TYPE from build command arguments
ARG PHP_VERSION=7.4
ARG PROJECT_TYPE=fpm
ARG CRAFT_FROM_IMAGE=php-fpm

FROM craftcms/${CRAFT_FROM_IMAGE}:${PHP_VERSION}-dev

# Install additional PHP extensions here
# @link https://github.com/craftcms/docker/tree/7.3#installing-extensions
# USER root
# RUN docker-php-ext-install ...
# USER www-data

# Expose PHP_VERSION and PROJECT_TYPE as env variables in container (runtime)
ENV PHP_VERSION=${PHP_VERSION}
ENV PROJECT_TYPE=${PROJECT_TYPE}

# the user is `www-data`, so we copy the files using the user and group
COPY --chown=www-data:www-data . .

# Install composer from official image into this image
# @link https://hub.docker.com/_/composer/
COPY --from=composer /usr/bin/composer /usr/bin/composer
RUN composer install --no-interaction --prefer-dist