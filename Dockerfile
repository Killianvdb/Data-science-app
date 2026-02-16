FROM php:8.2-fpm

# 1. Install system dependencies
# Added nodejs and npm to this list
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    libpq-dev \
    libzip-dev \
    libonig-dev \
    libpng-dev \
    libssl-dev \
    zlib1g-dev \
    libicu-dev \
    python3 \
    python3-pip \
    python3-venv \
    nodejs \
    npm \
    && rm -rf /var/lib/apt/lists/*

# 2. Install PHP extensions
RUN pecl install redis && docker-php-ext-enable redis
RUN docker-php-ext-install pdo pdo_pgsql zip opcache bcmath mbstring gd intl

# 3. Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 4. Set up the working directory
WORKDIR /app

# 5. Handle Python dependencies
COPY requirements.txt .
RUN pip3 install --no-cache-dir -r requirements.txt --break-system-packages || true

# 6. Create non-root user
RUN groupadd -g 1000 appuser && \
    useradd -r -u 1000 -g appuser appuser

# 7. Copy application code with ownership
COPY --chown=appuser:appuser . .

# 8. Install Laravel dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader || true

# 9. Permission Fixes
RUN chmod -R 775 storage bootstrap/cache && \
    chown -R appuser:appuser /app/storage /app/bootstrap/cache

USER root
RUN mkdir -p /home/appuser && chown -R appuser:appuser /home/appuser
# ... then switch back ...
USER appuser
# 10. Switch to the non-root user
USER appuser

EXPOSE 9000

CMD ["php-fpm"]