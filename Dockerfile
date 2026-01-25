FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    default-mysql-client \
    && docker-php-ext-install pdo pdo_mysql mysqli zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copiar solo composer primero
COPY composer.json composer.lock* ./

RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copiar el resto del código (SIN vendor)
COPY . .

EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "."]