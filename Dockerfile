# Gunakan image resmi PHP 8.3 FPM + Nginx berbasis Alpine
FROM php:8.3-fpm-alpine

# Install ekstensi dan utilitas yang dibutuhkan
RUN apk add --no-cache \
    nginx \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip

# Install ekstensi PHP
RUN docker-php-ext-install bcmath opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Direktori kerja
WORKDIR /var/www/html

# Salin seluruh isi project
COPY . /var/www/html

# Install dependensi PHP tanpa dev packages
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Konfigurasi perizinan folder storage dan cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Konfigurasi Nginx
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# Salin script startup
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Port default yang biasa digunakan Render (80 atau custom PORT)
EXPOSE 80

CMD ["/usr/local/bin/start.sh"]
