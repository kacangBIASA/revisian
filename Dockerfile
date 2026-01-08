FROM php:8.2-apache

RUN a2enmod rewrite

RUN apt-get update \
  && apt-get install -y --no-install-recommends ca-certificates \
  && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /var/www/html
COPY . /var/www/html

# Copy CA certificate untuk Azure MySQL
COPY certs/azure-mysql-ca.pem /etc/ssl/certs/azure-mysql-ca.pem

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
