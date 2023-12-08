FROM serversideup/php:8.2-fpm-nginx as builder

# Node.js
RUN curl -sL https://deb.nodesource.com/setup_16.x -o nodesource_setup.sh
RUN bash nodesource_setup.sh
RUN apt-get install nodejs -y
RUN npm install npm@6 -g
RUN command -v node
RUN command -v npm
RUN npm install -g typescript
RUN npm install -g pnpm

WORKDIR /app
COPY . .

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --optimize-autoloader --no-dev --no-interaction --no-progress --ansi

RUN pnpm install
RUN npm run build
RUN rm -rf node_modules

FROM serversideup/php:8.2-fpm-nginx

RUN apt-get update \
    && apt-get install -y --no-install-recommends php8.2-pgsql poppler-utils \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

COPY  --from=builder --chown=$PUID:$PGID /app .

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --optimize-autoloader --no-dev --no-interaction --no-progress --ansi

# artisan commands
RUN php ./artisan key:generate && \
    php ./artisan view:cache && \
    php ./artisan route:cache && \
    php ./artisan ziggy:generate && \
    php ./artisan storage:link

USER root:root
