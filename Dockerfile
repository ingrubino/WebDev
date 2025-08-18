FROM php:8.2-apache

# Installa estensioni necessarie
RUN docker-php-ext-install pdo pdo_mysql

