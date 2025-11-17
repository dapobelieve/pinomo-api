
FROM php:8.4-apache

COPY /apache/default.conf /etc/apache2/sites-enabled/000-default.conf

RUN apt-get update && apt-get install -y \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zlib1g-dev \
    libzip-dev \
    nano \
    gcc \
    git \
    make \
    autoconf \
    libprotobuf-dev \
    protobuf-compiler \
    zip \
    unzip \
    supervisor

# Add and Enable PHP-PDO Extenstions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd sockets
RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN docker-php-ext-install zip
RUN docker-php-ext-install bcmath
RUN docker-php-ext-enable pdo_mysql

# Install Datadog PHP APM extension
RUN curl -LO https://github.com/DataDog/dd-trace-php/releases/latest/download/datadog-setup.php \
    && php datadog-setup.php --php-bin=all --enable-appsec \
    && rm datadog-setup.php

WORKDIR /var/www/html

RUN chown -R www-data:www-data /var/www
RUN chmod 755 /var/www

RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

RUN a2enmod rewrite

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY / ./

# Ensure storage directories exist and set proper permissions
RUN mkdir -p /var/www/html/storage/logs \
    && mkdir -p /var/www/html/storage/framework/cache \
    && mkdir -p /var/www/html/storage/framework/sessions \
    && mkdir -p /var/www/html/storage/framework/views \
    && mkdir -p /var/www/html/storage/app \
    && mkdir -p /var/www/html/bootstrap/cache

# Set proper ownership and permissions for Laravel directories
RUN chown -R www-data:www-data /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Ensure the Laravel log file can be created and written to
RUN touch /var/www/html/storage/logs/laravel.log \
    && chown www-data:www-data /var/www/html/storage/logs/laravel.log \
    && chmod 664 /var/www/html/storage/logs/laravel.log


# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Composer dependencies
# Handle GitLab authentication for private repositories
ARG GITLAB_TOKEN
RUN if [ -n "$GITLAB_TOKEN" ]; then \
        echo "{\"gitlab-token\": {\"gitlab.com\": \"$GITLAB_TOKEN\"}}" > auth.json; \
    fi
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
# Clean up auth file for security
RUN rm -f auth.json

RUN service apache2 stop

RUN mkdir -p "/etc/supervisor/logs"

COPY /supervisor/supervisord.conf /etc/supervisor/supervisord.conf

COPY start.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/start.sh

EXPOSE 80

ENTRYPOINT ["start.sh"]

CMD ["/usr/bin/supervisord", "-n", "-c",  "/etc/supervisor/supervisord.conf"]
