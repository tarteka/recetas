FROM php:8.4-cli

# Instala dependencias mínimas y soporte SQLite.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY docker/entrypoint.sh /usr/local/bin/recetas-entrypoint

# Permite ejecutar el entrypoint al iniciar el contenedor.
RUN chmod +x /usr/local/bin/recetas-entrypoint

ENTRYPOINT ["recetas-entrypoint"]

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]