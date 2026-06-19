#!/bin/bash
# Phronix AI - Laravel Backend Setup Script
# Run this once to initialize the backend

set -e

echo "=== Phronix AI Backend Setup ==="

# 1. Install PHP dependencies
if [ ! -d "vendor" ]; then
    echo "Installing PHP dependencies..."
    composer install --no-dev --optimize-autoloader
fi

# 2. Copy env if not exists
if [ ! -f ".env" ]; then
    echo "Creating .env from .env.example..."
    cp .env.example .env
    php artisan key:generate
    echo ""
    echo "IMPORTANT: Edit .env and set your GEMINI_API_KEY before continuing!"
    exit 1
fi

# 3. Create SQLite database if not exists
mkdir -p database
if [ ! -f "database/database.sqlite" ]; then
    touch database/database.sqlite
fi

# 4. Run migrations
echo "Running database migrations..."
php artisan migrate --force

# 5. Create storage link
php artisan storage:link 2>/dev/null || true

# 6. Clear and cache config
php artisan config:cache
php artisan route:cache

echo ""
echo "=== Setup complete! ==="
echo "Start the server: php artisan serve --host=0.0.0.0 --port=8000"
