# Dockerfile — PHP 7.4 + Apache pour Laravel 7.x
FROM php:7.4-apache

# Paquets système + extensions PHP utiles à Laravel 7
RUN apt-get update && apt-get install -y \
    git zip unzip curl libzip-dev libicu-dev libonig-dev tzdata \
 && docker-php-ext-configure intl \
 && docker-php-ext-install pdo pdo_mysql mbstring intl zip \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

# Timezone (optionnel, cohérent avec Europe/Paris)
ENV TZ=Europe/Paris

# Composer (v2)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Dossier de travail
WORKDIR /var/www/html

# Copie du code (ton Laravel est dans src/)
# Si ton app est à la racine, remplace "src/" par "."
COPY src/ ./

# Réglage du DocumentRoot sur public/
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
  /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf

# Dépendances + optimisations
RUN composer install --no-dev --prefer-dist --optimize-autoloader \
 && mkdir -p storage bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R ug+rwx storage bootstrap/cache

# EntryPoint: migrations + storage:link + Apache
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80
CMD ["/entrypoint.sh"]
