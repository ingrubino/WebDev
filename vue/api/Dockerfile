# API PHP: build context = cartella vue/ (serve anche config/device-rules.json)
FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite \
 && sed -ri 's#/var/www/html#/var/www/html/public#g' /etc/apache2/sites-available/000-default.conf \
 && printf '<Directory /var/www/html/public>\n  AllowOverride All\n</Directory>\n' > /etc/apache2/conf-enabled/api.conf

COPY api/public /var/www/html/public
COPY api/src /var/www/html/src
COPY config /var/www/html/config
