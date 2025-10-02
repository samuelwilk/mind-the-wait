FROM php:8.3-fpm

# System deps
RUN apt-get update && apt-get install -y \
    git unzip libicu-dev libpq-dev libzip-dev libonig-dev libxml2-dev \
    && docker-php-ext-install intl pdo pdo_pgsql opcache zip \
    && rm -rf /var/lib/apt/lists/*

# Recommended PHP settings for dev
COPY docker/php.ini /usr/local/etc/php/php.ini

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Create app dir
WORKDIR /var/www/app

# Install Symfony CLI (handy inside container for cache:clear etc.)
RUN curl -sS https://get.symfony.com/cli/installer | bash \
 && mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

# Optional Xdebug (toggle with ARG)
ARG INSTALL_XDEBUG=false
RUN if [ "$INSTALL_XDEBUG" = "true" ]; then \
        pecl install xdebug && docker-php-ext-enable xdebug ; \
    fi

# Keep www-data permissions sane
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data
USER www-data
