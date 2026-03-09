# Dockerfile
FROM php:8.2-cli

# Instalar extensiones
RUN docker-php-ext-install pdo pdo_mysql mysqli zip

# Instalar dependencias del sistema para GD
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Instalar dependencias
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copiar aplicación
COPY . .

# Puerto
EXPOSE 8080

# Comando de inicio explícito
CMD ["php", "-S", "0.0.0.0:8080", "-t", "."]