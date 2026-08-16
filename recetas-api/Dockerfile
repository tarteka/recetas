FROM php:8.4-cli

# Instala dependencias mínimas, SQLite y librerías necesarias para GD.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libsqlite3-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd \
        --with-jpeg \
        --with-webp \
        --with-freetype \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_sqlite \
        gd \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install --no-dev

RUN echo '#!/bin/sh' > /usr/local/bin/recetas-entrypoint && \
    echo 'set -e' >> /usr/local/bin/recetas-entrypoint && \
    echo 'if [ -f "vendor/autoload.php" ] && [ -f "database/schema.sql" ]; then' >> /usr/local/bin/recetas-entrypoint && \
    echo '  php -r "require \"/app/vendor/autoload.php\"; \$pdo = \App\Database::conectar(); \$sql = file_get_contents(\"/app/database/schema.sql\"); \$pdo->exec(\$sql);"' >> /usr/local/bin/recetas-entrypoint && \
    echo '  echo "Base de datos inicializada correctamente."' >> /usr/local/bin/recetas-entrypoint && \
    echo 'fi' >> /usr/local/bin/recetas-entrypoint && \
    echo 'exec "$@"' >> /usr/local/bin/recetas-entrypoint && \
    chmod +x /usr/local/bin/recetas-entrypoint

ENTRYPOINT ["recetas-entrypoint"]

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]