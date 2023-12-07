FROM serversideup/php:8.1-fpm-nginx as builder

# Node.js
RUN curl -sL https://deb.nodesource.com/setup_16.x -o nodesource_setup.sh
RUN bash nodesource_setup.sh
RUN apt-get install nodejs -y
RUN npm install npm -g
RUN command -v node
RUN command -v npm
RUN npm install --global pnpm

WORKDIR /app
COPY . .

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --optimize-autoloader --no-dev --no-interaction --no-progress --ansi

RUN pnpm install
RUN npm run build
RUN rm -rf node_modules

FROM serversideup/php:8.1-fpm-nginx

RUN apt-get update \
    && apt-get install -y --no-install-recommends php8.1-pgsql  \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

COPY --from=ocittwo/php-pdf:latest /app/libphp_pdf.so /usr/lib/php/20210902/libphp_pdf.so
RUN echo "extension=libphp_pdf.so" > /etc/php/8.1/cli/conf.d/php-pdf.ini
COPY  --from=builder --chown=$PUID:$PGID /app .

# COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
# RUN composer install --optimize-autoloader --no-dev --no-interaction --no-progress --ansi

# artisan commands
RUN php ./artisan key:generate && \
    php ./artisan view:cache && \
    php ./artisan route:cache && \
    php ./artisan ziggy:generate && \
    php ./artisan storage:link

USER root:root
