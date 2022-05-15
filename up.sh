docker-compose up -d --build
docker-compose exec fpm cp .env.example .env
docker-compose exec fpm php artisan key:generate
docker-compose exec fpm php artisan storage:link
docker-compose exec fpm php artisan migrate
docker-compose exec fpm php artisan db:seed
docker-compose exec fpm chown -R www-data:www-data storage
