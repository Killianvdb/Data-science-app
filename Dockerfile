FROM php:8.2-fpm

RUN echo "upload_max_filesize = 100M" > /usr/local/etc/php/conf.d/uploads.ini && \
    echo "post_max_size = 100M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "max_execution_time = 600" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/uploads.ini

# 1. Install system dependencies + Node.js
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    libonig-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libicu-dev \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# 2. Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
       pdo pdo_pgsql zip opcache bcmath mbstring gd intl

RUN apt-get update && apt-get install -y docker.io && rm -rf /var/lib/apt/lists/*


# 3. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Set working directory
WORKDIR /app

# 5. Copy composer files
COPY composer.json composer.lock ./

# 6. Install PHP dependencies
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist



# 7. Copy package.json for Node dependencies
COPY package*.json ./

# 8. Install Node dependencies
RUN npm install

# 9. Copy application code
COPY . .

# 10. Build assets
RUN npm run build

# 11. Finalize composer
RUN composer dump-autoload --optimize

# 12. Fix permissions
RUN chmod -R 777 storage bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
