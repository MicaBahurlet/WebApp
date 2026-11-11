FROM php:8.2-apache

# Instalar extensiones necesarias de PDO MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Habilitar mod_rewrite por si usás URLs limpias
RUN a2enmod rewrite

# Copiar el código del proyecto al directorio web de Apache
COPY . /var/www/html/

# Configurar permisos para Apache
RUN chown -R www-data:www-data /var/www/html

# Exponer el puerto por defecto
EXPOSE 80