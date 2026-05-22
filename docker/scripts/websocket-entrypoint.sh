#!/bin/sh
set -e

cd /var/www

if [ ! -f vendor/autoload.php ]; then
    echo "vendor/ not found, running composer install..."
    composer install --no-dev --prefer-dist --optimize-autoloader
fi

exec php websocket_server.php
