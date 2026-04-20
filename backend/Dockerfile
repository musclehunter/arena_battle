# =============================================================================
# Arena Battle - Production Dockerfile (Railway 想定)
#
# - Railway Service の Root Directory を `backend/` に設定して使用する
# - $PORT (Railway が動的に決定) で HTTP を bind する
# - ビルド: Node で Vite アセット → PHP コンテナへ /public/build をコピー
# =============================================================================

# ---- 1) Node ステージ: Vite アセットビルド ----
FROM node:20-alpine AS assets
WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci

COPY . .
RUN npm run build


# ---- 2) Composer ステージ: 本番依存のインストール ----
FROM composer:2 AS vendor
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts \
    --prefer-dist


# ---- 3) ランタイム: PHP CLI + 必要な extension ----
FROM php:8.3-cli-alpine AS runtime

# OS / PHP extension 依存
RUN apk add --no-cache \
        libpng libjpeg-turbo freetype libzip icu-libs oniguruma-dev \
        mysql-client git curl bash tzdata \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev icu-dev oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        bcmath \
        intl \
        zip \
        gd \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del --purge .build-deps \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

ENV TZ=Asia/Tokyo \
    COMPOSER_ALLOW_SUPERUSER=1 \
    PORT=8080

WORKDIR /app

# アプリケーションコード + vendor + build 済みアセットを集約
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build

# autoload の最適化 & 書き込み権限
RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 8080

# Laravel は起動時に migrate / config:cache を実行してから built-in server で待ち受け
# (低トラフィック向け。負荷が増えたら FrankenPHP / RoadRunner に切替え)
CMD php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan migrate --force \
    && php artisan serve --host=0.0.0.0 --port="${PORT}"
