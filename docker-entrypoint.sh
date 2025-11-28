#!/bin/sh
set -e

# Railway provides PORT environment variable
PORT=${PORT:-8080}

echo "Configuring Apache to listen on port $PORT..."

# Update ports.conf
echo "Listen $PORT" > /etc/apache2/ports.conf

# Update default site
cat > /etc/apache2/sites-available/000-default.conf <<EOF
<VirtualHost *:$PORT>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html

    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

echo "Apache configured. Starting on port $PORT..."

# Start Apache in foreground
exec apache2-foreground
