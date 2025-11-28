FROM php:8.2-apache

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy application files
COPY index.php /var/www/html/
COPY health.php /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# Configure Apache to listen on PORT environment variable
RUN echo 'Listen ${PORT}' > /etc/apache2/ports.conf && \
    echo '<VirtualHost *:${PORT}>\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
    </VirtualHost>' > /etc/apache2/sites-available/000-default.conf

ENV PORT=8080
EXPOSE 8080

CMD ["sh", "-c", "sed -i \"s/\\${PORT}/$PORT/g\" /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
