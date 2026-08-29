# ---------------------------------------------------------------------------
# SmartTracer -- image backend Laravel (PHP-FPM).
#
# Image ini dipakai oleh TIGA service di docker-compose: app (php-fpm),
# worker (queue:work), dan scheduler (schedule:run). Yang membedakan hanya
# perintah yang dijalankan, isinya identik.
#
# Tidak ada tahap Node di sini. routes/web.php kosong dan satu-satunya blade
# yang memanggil @vite (welcome.blade.php) tidak punya route, jadi backend
# murni REST API dan tidak butuh hasil build Vite.
# ---------------------------------------------------------------------------

# --- Tahap 1: resolve dependency Composer ---------------------------------
FROM php:8.3-cli-alpine AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY --from=mlocati/php-extension-installer:2 \
    /usr/bin/install-php-extensions \
    /usr/local/bin/

RUN install-php-extensions \
        gd \
        pdo_pgsql \
        pgsql \
        zip \
        intl \
        bcmath \
        pcntl

WORKDIR /app

# Copy dependency manifest dulu agar layer Composer bisa di-cache
COPY composer.json composer.lock ./

# Install dependency tanpa menjalankan script Artisan,
# karena source Laravel belum dicopy
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --prefer-dist \
        --optimize-autoloader

# Baru copy seluruh source Laravel
COPY . .

# Optimasi autoloader setelah source tersedia
RUN composer dump-autoload \
        --no-dev \
        --optimize \
        --classmap-authoritative

# --- Tahap 2: runtime -----------------------------------------------------
FROM php:8.3-fpm-alpine AS runtime

# install-php-extensions menangani dependency sistem tiap ekstensi sendiri,
# jauh lebih ringkas daripada rangkaian apk add + docker-php-ext-install.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

# pdo_pgsql  : dua koneksi OLTP/OLAP
# zip, gd    : maatwebsite/excel (baca .xlsx, sisipkan gambar)
# intl,bcmath: format angka & tanggal lokal
# pcntl      : sinyal shutdown yang bersih untuk queue:work
# opcache    : wajib untuk performa di VPS kecil
RUN install-php-extensions \
        pdo_pgsql \
        pgsql \
        zip \
        gd \
        intl \
        bcmath \
        pcntl \
        opcache \
    && apk add --no-cache postgresql-client

WORKDIR /var/www/html

COPY docker/php.ini      /usr/local/etc/php/conf.d/zz-smarttracer.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-smarttracer.conf

COPY --from=vendor --chown=www-data:www-data /app /var/www/html
COPY --chown=root:root docker/entrypoint.sh /usr/local/bin/entrypoint

# public/ disimpan sebagai cadangan di luar volume. Entrypoint menyalinnya ke
# volume bersama supaya container nginx bisa menyajikan file statis Laravel
# (storage link, favicon) tanpa perlu ikut mem-build image backend.
RUN chmod +x /usr/local/bin/entrypoint \
    && cp -a /var/www/html/public /var/www/public-src \
    && mkdir -p storage/framework/{cache,sessions,testing,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
