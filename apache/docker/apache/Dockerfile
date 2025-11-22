# Usamos la imagen oficial de Apache HTTPD
FROM httpd

# Instalamos las dependencias necesarias para usar PHP-FPM con Apache
RUN apt-get update && apt-get install -y \
    apt-transport-https \
    curl \
    libapache2-mod-fcgid \
    && rm -rf /var/lib/apt/lists/*

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