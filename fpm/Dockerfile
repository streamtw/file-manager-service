FROM php:8.2-fpm

WORKDIR /var/www/html

RUN apt-get update && \
    apt-get install -y \
        # libfreetype6-dev \
        # libjpeg-dev \
        # libjpeg62-turbo-dev \
        libpng-dev \
        # zip required by composer
        libzip-dev \
        vim \
        # zip required by composer
        zip

# RUN pecl install imagick && docker-php-ext-enable imagick

# RUN docker-php-ext-configure gd --with-freetype --with-jpeg

RUN docker-php-ext-install \
    exif \
    fileinfo \
    gd \
    opcache \
    pdo \
    pdo_mysql \
    # zip required by composer
    zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
