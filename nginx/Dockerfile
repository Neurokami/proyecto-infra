# ---- Stage PHP-FPM ----
FROM php:8.2-fpm AS php

# Extensiones necesarias para tu aplicación
RUN echo "Construyendo stage PHP-FPM" \
 && docker-php-ext-install pdo pdo_mysql

# Directorio de trabajo dentro del contenedor PHP
WORKDIR /var/www/html

# NOTA:
# El código se monta en tiempo de ejecución con:
#   - ./html:/var/www/html
# por eso no copiamos aquí los archivos, solo preparamos el entorno.


# ---- Stage Nginx ----
FROM nginx:latest AS nginx

# Log simple para ver en los builds que este stage se ejecuta
RUN echo "Construyendo stage NGINX"

# Copiamos una configuración por defecto de Nginx para el caso
# en que NO montes nada desde el host. Si montas /mnt/raid_1/nginx.conf,
# este archivo será sobrescrito en tiempo de ejecución, lo cual está bien.
COPY nginx.conf /etc/nginx/nginx.conf

# Directorio raíz que debe coincidir con 'root' en nginx.conf
WORKDIR /var/www/html
