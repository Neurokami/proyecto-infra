FROM php:8.2-fpm AS php

RUN echo "Construyendo stage PHP-FPM" \
 && docker-php-ext-install pdo pdo_mysql

WORKDIR /var/www/html

FROM nginx:latest AS nginx

RUN echo "Construyendo stage NGINX"

COPY nginx.conf /etc/nginx/nginx.conf

WORKDIR /var/www/html
