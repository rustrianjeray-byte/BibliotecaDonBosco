Dockerfile
FROM php:8.2-apache

# Habilitar extensiones para conexión a base de datos (PDO y MySQL)
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Copiar el código al directorio raíz de Apache
COPY . /var/www/html/

# Exponer el puerto estándar
EXPOSE 80
