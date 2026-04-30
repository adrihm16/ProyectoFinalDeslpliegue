# Etapa 1: Construcción (Builder)
FROM php:8.2-fpm-alpine AS builder

# Instalamos dependencias de construcción
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-install pdo pdo_mysql

# Etapa 2: Producción (Final)
FROM php:8.2-fpm-alpine

# Instalamos curl para el healthcheck y fcgi para diagnóstico opcional
RUN apk add --no-cache curl fcgi

# Copiamos las extensiones desde la etapa de construcción
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Establecemos el directorio de trabajo
WORKDIR /var/www/html

# Copiamos el código de la aplicación
COPY --chown=www-data:www-data ./public ./public
COPY --chown=www-data:www-data ./config ./config
COPY ./php-fpm.d/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf

# Aseguramos que PHP-FPM no se ejecute en segundo plano
RUN sed -i 's/^;daemonize = yes/daemonize = no/' /usr/local/etc/php-fpm.conf

# Configuramos las variables de entorno
ENV DB_HOST=db \
    DB_NAME=blogdb \
    DB_USER=bloguser \
    DB_PASS=blogpass \
    REDIS_HOST=redis

# Exponemos el puerto de PHP-FPM
EXPOSE 9000

# Cambiamos al usuario no root
USER www-data

# Comando por defecto para mantener el contenedor en primer plano
CMD ["php-fpm", "-F"]