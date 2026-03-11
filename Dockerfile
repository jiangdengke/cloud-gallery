# ---- build frontend ----
FROM node:20-alpine AS nodebuild
WORKDIR /app/web
COPY web/package*.json ./
RUN npm ci
COPY web/ .
RUN npm run build

# ---- install php deps ----
FROM php:8.4-cli AS vendor
WORKDIR /app
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Composer 在 Docker 构建环境中偶发网络超时（尤其是 GitHub dist/source 下载）。
# 这里适当放宽超时时间，避免依赖下载慢导致镜像构建失败。
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_PROCESS_TIMEOUT=1200 \
    COMPOSER_HTTP_TIMEOUT=1200

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS \
        git unzip \
        libzip-dev libicu-dev libonig-dev \
    && docker-php-ext-install zip intl mbstring \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-progress
COPY . .
RUN composer dump-autoload --optimize

# ---- app runtime (Apache, API only) ----
FROM php:8.4-apache AS api
WORKDIR /var/www

ENV APACHE_DOCUMENT_ROOT=/var/www/public

RUN { \
        echo 'upload_max_filesize=100M'; \
        echo 'post_max_size=100M'; \
        echo 'memory_limit=256M'; \
        echo 'max_execution_time=300'; \
        echo 'max_input_time=300'; \
    } > /usr/local/etc/php/conf.d/cloud-gallery.ini

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS \
        libpng-dev libjpeg-dev libfreetype6-dev \
        libzip-dev libonig-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    # php:8.4-apache 已内置 sqlite3/pdo_sqlite，无需再次编译（避免 config0.m4 兼容问题）
    && docker-php-ext-install pdo_mysql mbstring zip gd bcmath intl \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && a2enmod rewrite \
    && sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/000-default.conf \
    && { \
        echo '<Directory /var/www/public>'; \
        echo '  AllowOverride All'; \
        echo '  Require all granted'; \
        echo '</Directory>'; \
      } > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel \
    && rm -rf /var/lib/apt/lists/*

COPY --from=vendor /app /var/www

RUN mkdir -p \
        /var/www/storage/app/public \
        /var/www/storage/app/tmp \
        /var/www/storage/framework/cache \
        /var/www/storage/framework/sessions \
        /var/www/storage/framework/views \
        /var/www/storage/logs \
        /var/www/bootstrap/cache \
    && if [ ! -e /var/www/public/storage ]; then ln -s /var/www/storage/app/public /var/www/public/storage; fi \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# 启动前：自动生成 APP_KEY、准备 SQLite、执行迁移（见 docker/entrypoint.sh）
COPY docker/entrypoint.sh /usr/local/bin/cloud-gallery-entrypoint
RUN chmod +x /usr/local/bin/cloud-gallery-entrypoint

ENTRYPOINT ["/usr/local/bin/cloud-gallery-entrypoint"]
CMD ["apache2-foreground"]

# ---- app runtime (Apache, with built frontend) ----
FROM api AS app
COPY --from=nodebuild /app/web/dist /var/www/public
