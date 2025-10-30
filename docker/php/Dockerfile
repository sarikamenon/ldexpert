FROM php:8.2-fpm-bullseye

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    git curl zip unzip \
    libicu-dev libzip-dev libpng-dev libonig-dev libxml2-dev \
    default-mysql-client gnupg ca-certificates \
    && docker-php-ext-install intl pdo_mysql bcmath exif pcntl zip \
    && rm -rf /var/lib/apt/lists/*

# Coverage tools: PCOV (fast) and Xdebug (off by default)
RUN pecl install pcov xdebug \
    && docker-php-ext-enable pcov \
    && rm -rf /tmp/pear \
    && echo "pcov.enabled=0" > /usr/local/etc/php/conf.d/pcov.ini \
    && echo "zend_extension=xdebug" > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.mode=off" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node 20 (for asset builds)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get update && apt-get install -y nodejs \
    && npm --version && node --version

# Google Chrome (latest stable) for Dusk
RUN curl -fsSL https://dl.google.com/linux/linux_signing_key.pub | gpg --dearmor -o /usr/share/keyrings/google-linux.gpg \
    && echo "deb [arch=amd64 signed-by=/usr/share/keyrings/google-linux.gpg] http://dl.google.com/linux/chrome/deb/ stable main" > /etc/apt/sources.list.d/google-chrome.list \
    && apt-get update && apt-get install -y google-chrome-stable \
    && google-chrome --version

WORKDIR /var/www/html

# Align www-data uid/gid to host (common dev env)
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data
USER www-data

CMD ["php-fpm"]

