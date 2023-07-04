FROM phpswoole/swoole:4.8.12-php8.0-alpine

WORKDIR /app

COPY . .

RUN composer install --no-interaction --ignore-platform-reqs --optimize-autoloader --prefer-dist

ENV DB_PASS=root

CMD [ "php", "index.php" ]

EXPOSE 8000
