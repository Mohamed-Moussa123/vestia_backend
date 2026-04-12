FROM php:8.2-apache
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
COPY vestia_backend-main/vestia_backend-main/vestia_backend/vestia/api/ /var/www/html/
COPY vestia_backend-main/vestia_backend-main/vestia_backend/vestia/admin/ /var/www/html/admin/
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html
RUN a2enmod rewrite
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/sites-available/000-default.conf
EXPOSE 80
