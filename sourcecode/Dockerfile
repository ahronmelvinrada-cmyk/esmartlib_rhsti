
FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql \
    && a2enmod rewrite

WORKDIR /var/www/html

COPY sourcecode/ ./
COPY assets/ ./assets/
COPY uploads/ ./uploads/
RUN mkdir -p uploads/students uploads/library \
    && chown -R www-data:www-data uploads

EXPOSE 80
