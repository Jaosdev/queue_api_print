FROM php:8.2-apache

# Instalar dependencias del sistema
RUN apt-get update && \
    apt-get install -y \
    unzip \
    zip \
    git \
    curl \
    supervisor \
    netcat-openbsd \
    libzip-dev \
    libfreetype6-dev \
    libjpeg-dev \
    libpng-dev \
    wkhtmltopdf \
    fontconfig \
    libxrender1 \
    libx11-6 \
    libxcb1 \
    libxext6 && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install gd zip && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# Instalar Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copiar configuración del virtual host
COPY config/vhost.conf /etc/apache2/sites-available/000-default.conf

# Crear carpeta de configuración
RUN mkdir -p /var/log/supervisor
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN mkdir -p /var/www/html/queue \
    && chown -R www-data:www-data /var/www/html/queue \
    && chmod -R 775 /var/www/html/queue

# Copiar el código fuente (debes asegurarte que la carpeta "queue" exista en tu máquina local)
COPY . /var/www/html/

COPY rabbitmq/setup.php /var/www/html/rabbitmq/setup.php

# Crear carpetas necesarias y dar permisos correctos
RUN mkdir -p /var/www/html/queue /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

# Establecer directorio de trabajo
WORKDIR /var/www/html/

CMD ["/usr/bin/supervisord"]
