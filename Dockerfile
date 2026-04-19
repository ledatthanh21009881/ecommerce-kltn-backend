FROM php:8.2-fpm

# Cài MySQL driver
RUN docker-php-ext-install pdo pdo_mysql mysqli

# (khuyên dùng thêm)
RUN apt-get update && apt-get install -y \
    zip unzip git curl

WORKDIR /var/www