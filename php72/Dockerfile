# PHP 7.4 + Apache (Laravel 7)
FROM php:7.4-apache

# Paquets + extensions
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libonig-dev libxml2-dev libicu-dev \
 && docker-php-ext-install -j"$(nproc)" \
    pdo_mysql mbstring tokenizer xml ctype bcmath zip gd intl \
 && a2enmod rewrite headers env \
 && rm -rf /var/lib/apt/lists/*

# Apache → DocumentRoot /public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's#DocumentRoot /var/www/html#DocumentRoot ${APACHE_DOCUMENT_ROOT}#' /etc/apache2/sites-available/000-default.conf \
 && sed -ri 's#<Directory /var/www/>#<Directory ${APACHE_DOCUMENT_ROOT}/>#' /etc/apache2/apache2.conf \
 && sed -ri 's#AllowOverride None#AllowOverride All#' /etc/apache2/apache2.conf \
 && printf "ServerName localhost\n" > /etc/apache2/conf-available/servername.conf \
 && a2enconf servername

# Opcache (optionnel)
RUN docker-php-ext-install opcache \
 && printf "opcache.enable=1\nopcache.memory_consumption=128\nopcache.max_accelerated_files=8000\nopcache.validate_timestamps=1\nopcache.revalidate_freq=0\nopcache.save_comments=1\n" > /usr/local/etc/php/conf.d/zz-opcache-dev.ini

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copier d’abord les fichiers composer pour mieux cacher les layers
COPY ./src/composer.json ./src/composer.lock ./

# ⚠️ Créer les dossiers AVANT composer install (fixe votre erreur)
RUN mkdir -p storage/framework/{sessions,views,cache} bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && composer install --no-dev --prefer-dist --optimize-autoloader --no-scripts --no-ansi --no-interaction --no-progress

# Puis le reste du code
COPY ./src/ /var/www/html/

# Droits d’accès runtime
RUN chown -R www-data:www-data storage bootstrap/cache

# Démarrage: faire écouter Apache sur le PORT Railway (0.0.0.0:$PORT)
CMD ["/bin/sh","-lc","echo \"Listen ${PORT:-8080}\" > /etc/apache2/ports.conf && exec apache2-foreground"]
