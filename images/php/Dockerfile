FROM php:7.1-apache

RUN apt-get update && apt-get install -y mysql-client \
    && docker-php-ext-install pdo_mysql mysqli

RUN pecl install -o -f redis \
    &&  rm -rf /tmp/pear \
    &&  docker-php-ext-enable redis

RUN a2enmod rewrite && service apache2 restart

WORKDIR /etc/apache2/sites-available/

EXPOSE 80