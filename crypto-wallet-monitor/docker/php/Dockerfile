FROM php:8.4-cli

# Dependências do sistema
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libgmp-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-install pdo pdo_pgsql bcmath gmp

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
