FROM php:8.1-cli
WORKDIR /app
COPY . /app
RUN apt-get update && apt-get install -y unzip git && rm -rf /var/lib/apt/lists/*
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && php composer-setup.php --install-dir=/usr/local/bin --filename=composer && php -r "unlink('composer-setup.php');"
RUN composer install --no-dev --prefer-dist --no-interaction || true
EXPOSE 8000
CMD ["php","-S","0.0.0.0:8000","-t","public"] 
