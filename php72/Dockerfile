FROM php:7.4-apache

# Paquets + extensions
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libonig-dev libxml2-dev libicu-dev \
 && docker-php-ext-install -j"$(nproc)" \
    pdo_mysql mbstring tokenizer xml ctype bcmath zip gd intl \
 && a2enmod rewrite \
 && rm -rf /var/lib/apt/lists/*

# Apache → DocumentRoot /public + droits d'accès
RUN sed -ri 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/public#' /etc/apache2/sites-available/000-default.conf \
 && sed -ri 's#<Directory /var/www/>#<Directory /var/www/html/public/>#' /etc/apache2/apache2.conf \
 && sed -ri 's#AllowOverride None#AllowOverride All#' /etc/apache2/apache2.conf \
 && printf '\n<Directory /var/www/html/public/>\n    AllowOverride All\n    Require all granted\n</Directory>\n' >> /etc/apache2/apache2.conf \
 && printf "ServerName localhost\n" > /etc/apache2/conf-available/servername.conf \
 && a2enconf servername

# Opcache dev
RUN docker-php-ext-install opcache \
 && printf "opcache.enable=1\nopcache.memory_consumption=128\nopcache.max_accelerated_files=8000\nopcache.validate_timestamps=1\nopcache.revalidate_freq=0\nopcache.save_comments=1\n" > /usr/local/etc/php/conf.d/zz-opcache-dev.ini

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ⚠️ Copier le code AVANT composer install
COPY ./src/ /var/www/html/

# Dépendances
RUN composer install --no-dev --prefer-dist --optimize-autoloader \
 && mkdir -p storage/framework/{sessions,views,cache} bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

# Laisse l'entrypoint par défaut (Apache)
CMD ["apache2-foreground"]
