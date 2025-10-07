FROM php:7.4-apache
# Paquets build + dépendances d'extensions (PHP 7.2 -> libonig pour mbstring)
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install -j$(nproc) \
       pdo_mysql mbstring tokenizer xml ctype bcmath zip gd \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# DocumentRoot -> public (Laravel)
RUN sed -ri 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/public#' /etc/apache2/sites-available/000-default.conf \
 && sed -ri 's#<Directory /var/www/>#<Directory /var/www/html/public/>#' /etc/apache2/apache2.conf \
 && sed -ri 's#AllowOverride None#AllowOverride All#' /etc/apache2/apache2.conf

# Installer Composer (2 par défaut ; on pourra downgrader en 1 si besoin)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
