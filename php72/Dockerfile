FROM php:7.4-apache
# Paquets build + dépendances d'extensions (PHP 7.2 -> libonig pour mbstring)
# Installer les paquets système et extensions PHP nécessaires
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libonig-dev libxml2-dev libicu-dev \
    && docker-php-ext-install -j$(nproc) \
       pdo_mysql mbstring tokenizer xml ctype bcmath zip gd intl \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# DocumentRoot -> public (Laravel)
RUN sed -ri 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/public#' /etc/apache2/sites-available/000-default.conf \
 && sed -ri 's#<Directory /var/www/>#<Directory /var/www/html/public/>#' /etc/apache2/apache2.conf \
 && sed -ri 's#AllowOverride None#AllowOverride All#' /etc/apache2/apache2.conf


# Autoriser explicitement l'accès à /public (évite le 403 "client denied")
RUN printf '\n<Directory /var/www/html/public/>\n    AllowOverride All\n    Require all granted\n</Directory>\n' >> /etc/apache2/apache2.conf

# Opcache pour dev (revalidation à chaque requête)
RUN docker-php-ext-install opcache \
 && printf "opcache.enable=1\n\
opcache.memory_consumption=128\n\
opcache.max_accelerated_files=8000\n\
opcache.validate_timestamps=1\n\
opcache.revalidate_freq=0\n\
opcache.save_comments=1\n" > /usr/local/etc/php/conf.d/zz-opcache-dev.ini

# Installer Composer (2 par défaut ; on pourra downgrader en 1 si besoin)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
# 1) Copier le code dans l’image (assumant votre app dans ./src)


# ➕ (Option 2 "propre" avec cache build – facultatif)
COPY ./src/composer.json ./src/composer.lock /var/www/html/
RUN composer install --no-dev --prefer-dist --optimize-autoloader
COPY ./src/ /var/www/html/

# ➕ Assurer les dossiers runtime + droits
RUN mkdir -p storage/framework/{sessions,views,cache} bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

# 2) Installer les dépendances Composer
RUN composer install --no-dev --prefer-dist --optimize-autoloader \
 && mkdir -p storage/framework/{sessions,views,cache} bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

# (Optionnel) si vous voulez start.sh (voir plus bas) :
# COPY docker/start.sh /start.sh
# RUN chmod +x /start.sh
# CMD ["/start.sh"]

# Si vous ne voulez PAS de start.sh, laissez l'entrée par défaut de l'image :
# (ne mettez rien : l’image php:7.4-apache lance déjà apache2-foreground)
