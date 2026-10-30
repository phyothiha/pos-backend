# Inventory & Invoice Management — Backend

Laravel 13 API for authentication, products, inventory movements, invoices, payments, and deliveries. Authentication uses JWT bearer tokens.

## Requirements

- Docker
- PHP 8.5 and Composer 2 to install dependencies before starting Sail.

## Demo Login

- Username: `admin`
- Password: `password`

## Setup

Install dependencies and create the local environment file:

```bash
composer install
cp .env.example .env
```

Set the following values in `.env`, replacing the existing database settings and uncommenting them where needed:

```env
APP_URL=http://localhost:8080

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

APP_PORT=8080
VITE_PORT=5174
FORWARD_DB_PORT=3307
```

Start the containers, generate application secrets, and initialize the database:

```bash
vendor/bin/sail up -d
vendor/bin/sail artisan key:generate
vendor/bin/sail artisan jwt:secret
vendor/bin/sail artisan migrate --seed
```
