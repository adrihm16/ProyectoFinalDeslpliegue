# Etapa 1: Construcción
FROM php:8.2-cli-alpine AS builder

RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install pdo pdo_mysql

# Etapa 2: Producción
FROM php:8.2-cli-alpine

# Instalamos curl para el healthcheck
RUN apk add --no-cache curl

# Copiamos extensiones
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

WORKDIR /var/www/html

# Copiamos el código y asignamos permisos al usuario www-data
COPY --chown=www-data:www-data ./public ./public
COPY --chown=www-data:www-data ./config ./config

ENV DB_HOST=db \
    DB_NAME=blogdb \
    DB_USER=bloguser \
    DB_PASSWORD=blogpass \
    REDIS_HOST=redis \
    REDIS_PORT=6379

EXPOSE 8000

# Cambiamos al usuario no root
USER www-data

CMD ["php", "-S", "0.0.0.0:8000", "-t", "/var/www/html/public", "/var/www/html/public/index.php"]
