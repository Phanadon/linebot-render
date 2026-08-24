FROM php:8.2-apache

# ติดตั้ง mysqli สำหรับเชื่อมต่อ MySQL/MariaDB
RUN docker-php-ext-install mysqli

# เปิด Apache rewrite
RUN a2enmod rewrite

# คัดลอกไฟล์โปรเจกต์เข้า Apache
COPY . /var/www/html/

# ตั้งสิทธิ์ให้ Apache สามารถเขียน log ได้
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80