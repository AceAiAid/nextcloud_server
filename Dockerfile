FROM php:8.2-apache

# 安装必要的 PHP 扩展和依赖
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libzip-dev \
    zip \
    unzip \
    libxml2-dev \
    libfreetype6-dev \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd mysqli pdo_mysql zip opcache intl \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && a2enmod rewrite headers env dir mime setenvif \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev \
    && chown -R www-data:www-data . \
    && mkdir -p data config custom_apps themes \
    && echo 'opcache.enable=1\n\
opcache.enable_cli=1\n\
opcache.interned_strings_buffer=8\n\
opcache.max_accelerated_files=10000\n\
opcache.memory_consumption=128\n\
opcache.save_comments=1\n\
opcache.revalidate_freq=1' > /usr/local/etc/php/conf.d/opcache-recommended.ini \
    && echo 'memory_limit = 512M' > /usr/local/etc/php/conf.d/memory-limit.ini