FROM php:8.3-cli

RUN docker-php-ext-install pdo_sqlite

WORKDIR /var/www/html

COPY . .

RUN chmod -R 755 /var/www/html

EXPOSE 10000

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000} -t public"]
