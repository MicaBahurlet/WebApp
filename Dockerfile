FROM php:8.2-apache

# Instalar extensiones necesarias de PDO MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Habilitar mod_rewrite de Apache para URLs limpias si las usás
RUN a2enmod rewrite

# Copiar el código del proyecto al directorio web de Apache
COPY . /var/www/html/

# Ajustar permisos para que Apache pueda leer los archivos
RUN chown -R www-data:www-data /var/www/html

# Exponer el puerto 80 del contenedor
EXPOSE 80