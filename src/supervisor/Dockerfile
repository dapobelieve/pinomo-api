FROM php:8.2-fpm-alpine

# Only absolutely essential extensions
RUN docker-php-ext-install pdo pdo_mysql pcntl

# Install supervisor
RUN apk add --no-cache supervisor

# Copy config
COPY ./supervisor/supervisord.conf /etc/supervisor/supervisord.conf

# Create log directory
RUN mkdir -p /etc/supervisor/logs

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]