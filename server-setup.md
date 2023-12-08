## Installing in your own VPS server

PDFPintar is built using the following tech stack:

-   Laravel
-   PostgresDB
-   pgvector (for embedding)
-   poppler-utils
-   OpenAI (for generate vector and chatting)
-   Redis (Optional)

## Requirement

Before running this project ensure you have the following software installed on your system.

1. PHP >= 8.1
2. PostgresDB >= 15.3
3. [pgvector](https://github.com/pgvector/pgvector)
4. NodeJS >= 18
5. Redis (optional)

## Docker (optional and fastest way)

If you prefer not to set up everything manually, the easiest way it to use Docker. Install Docker with the following commands (if not installed yet):

```bash
sudo apt update
sudo apt install docker-ce
sudo systemctl start docker
sudo systemctl enable docker
sudo docker --version # Docker version 24.0.4
```

Once Docker is installed, start PDFPIntar using `docker compose`:

Clone project:

```bash
git clone https://github.com/ahmadrosid/pdfpintar.git

cd pdfpintar
```

Build container using docker compose:

```bash
docker compose up -d --build
```

Then run database migration:

```bash
docker compose exec server php artisan migrate
```

## Install nginx

First install nginx.

```bash
sudo apt install apt install -y nginx
```

Then clone project into `/var/www/html` using git:

```bash
git clone https://github.com/ahmadrosid/pdfpintar.git
```

## Installing PHP

The minimum requirement for php version is 8.2, here's how you can install it in ubuntu >= 20.

```bash
sudo apt install software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install php8.2 php8.2-fpm php8.2-pgsql -y
# Here again, you might need to remove the version number. For example, `sudo apt-get install php php-fpm php-pgsql`
```

## Installing poppler-utils

Install required dependencies:

```bash
sudo apt install apt install -y poppler-utils
```

## Installing Postgres with pgvector extension

PDFPintar uses Postgres instead of MySQL due to its support for vector data. Below are the steps to configure Postgres with the pgvector extension on a Linux Debian environment.

```bash
sudo apt-get install postgresql-server-dev-14 postgresql-contrib libpq-dev gcc make -y
# Depending on your operating system, you might need another version of postgresql-server-dev

sudo apt-get install php8.2-pgsql php8.2-dom php8.2-curl php8.2-zip php8.2-redis
# If the above line fails, try removing the version number. For example, use 'sudo apt-get install php-pgsql php-dom php-curl' and so on. If you get PHP 8.2 or higher, then you're good to go.

cd /tmp
git clone --branch v0.4.2 https://github.com/pgvector/pgvector.git
cd pgvector
make clean && PG_CFLAGS=-DIVFFLAT_BENCH make && make install
```

## Enabling pgvector

Once pgvector ins installed, enable the extension. You can learn more about pgvector [here](https://github.com/pgvector/pgvector).

Login to postgre cli:

```bash
sudo -u postgres psql
```

Create new user and database.

```sql
CREATE USER pdfpintar WITH PASSWORD 'password';
CREATE DATABASE pdfpintar OWNER pdfpintar;
ALTER USER pdfpintar WITH SUPERUSER;
ALTER USER pdfpintar WITH PASSWORD 'your_password_here';
```

## Building the UI

Now let's get the laravel project up and running, we need to build the UI ready for production.

Ensure you have nodejs installed, here's how:

```bash
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs
curl -fsSL https://get.pnpm.io/install.sh | sh -
```

Now install javascript dependencies and build the ui.

```bash
pnpm install
pnpm build

# or using just npm
npm install
npm build
```

## Setting Up Laravel

Add the following environment variables to your `.env` file:

```bash
APP_NAME=pdfpintar
APP_KEY=base64:wx+...
APP_DEBUG=false
APP_URL=http://localhost:8000

OPENAI_API_KEY="sx..."

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pdfpintar
DB_USERNAME=pdfpintar
DB_PASSWORD=your_password_here
```

Run database migration :

```bash
php artisan migrate:fresh
```

Enable storage link :

```bash
php artisan storage:link
```

## Nginx Configuration

In this example I configure it to work on my domain.

```bash
server {
    listen 80;

    server_name yourdomain.com;
    root /var/www/html/pdfpintar/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ^~ /document/chat/streaming$ {
        proxy_http_version 1.1;
        add_header Connection '';

        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

you might need to open port to accept from public connection

```bash
sudo ufw allow 80
sudo ufw allow 443
```

Adjust permission for the project directory:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx
```

## Queue Worker

A queue worker is used for indexing PDF documents in the background. Ensure Redis is available:

```bash
sudo apt install redis-server
```

Install supervisor

```bash
bash apt install supervisor
```

Add new worker config.

```bash
cd /etc/supervisor/conf.d
vim queue-worker.conf
```

Configure the worker to point to the destination folder:

```yaml
[program:queue-worker]
process_name = %(program_name)s_%(process_num)02d
command=php /var/www/pdfpintar/artisan queue:listen
autostart=true
autorestart=true
user=root
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/pdfpintar/storage/logs/worker.log
```

Update supervisorctl config

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart all
```

## Setup SSL

Install certbot.

```bash
sudo apt update
sudo apt install certbot python3-certbot-nginx
```

Generate ssl certificate:

```bash
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

Still have a problem? Contact me at [https://t.me/ahmadrosid](https://t.me/ahmadrosid).