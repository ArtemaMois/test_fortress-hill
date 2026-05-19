FROM php:8.2-fpm

# Установка системных зависимостей
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    zip \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mysqli \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip

# Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Рабочая директория
WORKDIR /var/www/html

# Права
RUN chown -R www-data:www-data /var/www/html

EXPOSE 9000

CMD ["php-fpm"]
