FROM php:8.4-apache

ARG USER_ID=1000
ARG GROUP_ID=1000

# 依存パッケージとPHP拡張
RUN apt-get update && apt-get install -y \
  wget curl vim net-tools iputils-ping unzip \
  libpng-dev libjpeg-dev libfreetype6-dev \
  libzip-dev libxml2-dev libicu-dev libcurl4-openssl-dev \
  libonig-dev libxslt1-dev libexif-dev \
  msmtp msmtp-mta \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install gd mysqli curl mbstring zip xml opcache intl bcmath soap exif \
  && a2enmod rewrite \
  && rm -rf /var/lib/apt/lists/*

# 権限調整
RUN groupmod -g ${GROUP_ID} www-data && \
    usermod -u ${USER_ID} -g www-data www-data && \
    mkdir -p /var/www/html && chown -R www-data:www-data /var/www/html

# PHPからmsmtpを使うよう設定
RUN echo 'sendmail_path = "/usr/bin/msmtp -t -i"' > /usr/local/etc/php/conf.d/msmtp.ini && \
    ln -sf /usr/bin/msmtp /usr/sbin/sendmail

# Apache を 8080 で動かす（www-data はポート 80 にバインドできないため）
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf && \
    sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-enabled/000-default.conf

# Apache起動
COPY manager/docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

USER www-data

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
