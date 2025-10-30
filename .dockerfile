# Dockerfile (racine)
FROM php:8.2-apache

# Extensions
RUN docker-php-ext-install pdo pdo_mysql

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copie du code Laravel (ici dans src/)
COPY src/ ./

# Dep + optim
RUN composer install --no-dev --prefer-dist --optimize-autoloader \
 && chown -R www-data:www-data storage bootstrap/cache

# Apache -> public/
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
  /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf

# IMPORTANT : Apache écoute sur 80.
# Railway exposera le port où ton process écoute. Dans ce setup, pas besoin d'utiliser $PORT :
# Railway détecte l'écoute sur 80 dans le conteneur et mappe tout seul.

EXPOSE 80
CMD ["apache2-foreground"]
