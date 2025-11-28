#!/bin/bash
set -e

# Railway provides PORT environment variable, default to 8080 if not set
PORT=${PORT:-8080}

echo "Configuring Apache to listen on port $PORT..."

# Update Apache ports configuration
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
sed -i "s/:80/:$PORT/g" /etc/apache2/sites-available/000-default.conf

echo "Starting Apache on port $PORT..."
exec apache2-foreground
