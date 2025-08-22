# ShopSwift Plain PHP Backend (web + app API)

## Run
composer install
cp .env.example .env
php -S localhost:8000 -t public

## Test
GET  /            -> health
GET  /api/v1/products
POST /api/v1/auth/login
GET  /api/v1/shipper/orders (Authorization: Bearer demo)
