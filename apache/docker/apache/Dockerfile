# Usamos la imagen oficial de Apache HTTPD
FROM httpd

# Instalamos las dependencias necesarias para usar PHP-FPM con Apache
RUN apt-get update && apt-get install -y \
    apt-transport-https \
    curl \
    libapache2-mod-fcgid \
    && rm -rf /var/lib/apt/lists/*

# Habilitamos los módulos necesarios en Apache
# RUN a2enmod proxy \
#     && a2enmod proxy_fcgi \
#     && a2enmod rewrite \
#     && a2enmod headers \
#     && a2enmod setenvif \
#     && a2enmod ssl \
#     && a2enmod mpm_event

# Copiamos el archivo de configuración personalizado de Apache
# COPY ./httpd.conf /usr/local/apache2/conf/httpd.conf

ARG UID=1000
ARG GID=1000

# Create a new user with the specified UID and GID, reusing an existing group if GID exists
RUN if getent group ${GID}; then \
      group_name=$(getent group ${GID} | cut -d: -f1); \
      useradd -m -u ${UID} -g ${GID} -s /bin/bash www; \
    else \
      groupadd -g ${GID} www && \
      useradd -m -u ${UID} -g www -s /bin/bash www; \
      group_name=www; \
    fi

COPY . /usr/local/apache2/htdocs
RUN chown -R www-data:www-data /usr/local/apache2/htdocs

# Exponemos el puerto 80
EXPOSE 80