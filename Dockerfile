FROM php:8.4-cli

# ============================================================
# Dépendances système nécessaires à Laravel + PostgreSQL
# ============================================================
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
    && rm -rf /var/lib/apt/lists/*

# ============================================================
# Installer Composer
# ============================================================
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ============================================================
# Dossier de travail Laravel
# ============================================================
WORKDIR /var/www/html

# ============================================================
# Copier le projet Laravel dans le conteneur
# ============================================================
COPY . .

# ============================================================
# Installer les dépendances PHP de production
# ============================================================
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# ============================================================
# Préparer les dossiers Laravel nécessaires
# ============================================================
RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache

# ============================================================
# Donner les permissions à Laravel
# ============================================================
RUN chown -R www-data:www-data \
    storage \
    bootstrap/cache

# ============================================================
# Port utilisé par Render
# ============================================================
EXPOSE 10000

# ============================================================
# Démarrage du backend Laravel
# ============================================================
CMD ["sh", "-c", "php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"]
