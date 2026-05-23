FROM php:8.2-fpm

ENV TZ=Asia/Ho_Chi_Minh

RUN apt-get update && apt-get install -y \
    zip unzip git curl tzdata \
    libpng-dev libjpeg-dev libfreetype6-dev \
    && ln -snf /usr/share/zoneinfo/$TZ /etc/localtime \
    && echo $TZ > /etc/timezone \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mysqli gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
