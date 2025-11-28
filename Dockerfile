FROM php:8.2-apache

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy application files
COPY index.php /var/www/html/
COPY health.php /var/www/html/

# Create startup script
RUN echo '#!/bin/bash\n\
    set -e\n\
    PORT=${PORT:-80}\n\
    sed -i "s/80/$PORT/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf\n\
    exec apache2-foreground' > /start.sh && chmod +x /start.sh

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port
EXPOSE 80

CMD ["/start.sh"]
