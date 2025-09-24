# Use PHP 8.2 CLI to match composer.json requirements
FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    zip \
    unzip \
    libzip-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions (including PostgreSQL and your specified extensions)
RUN docker-php-ext-install pdo_pgsql pgsql pdo mbstring exif pcntl bcmath gd zip

# Configure PHP for memory optimization
RUN echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/memory.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/memory.ini \
    && echo "max_input_vars = 3000" >> /usr/local/etc/php/conf.d/memory.ini

# Get latest Composer and set memory limit
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
ENV COMPOSER_MEMORY_LIMIT=1

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Make scripts executable
RUN chmod +x build.sh start.sh start-simple.sh wait-for-db.sh health-check.sh debug-composer.sh fix-composer-lock.sh debug-deployment.sh

# Run the build script which handles composer install with fallbacks
RUN ./build.sh

# Create storage directories and set permissions
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
RUN chmod -R 775 storage bootstrap/cache

# Expose port (Render will set PORT environment variable)
EXPOSE $PORT

# Add health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD ./health-check.sh || exit 1

# Start Laravel application
CMD ["./start-simple.sh"]
