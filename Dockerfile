FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

COPY index.php /var/www/html/
COPY health.php /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
