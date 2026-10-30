FROM php:8.4-apache

ARG USER_ID=1000
ARG GROUP_ID=1000

# 依存パッケージとPHP拡張
# OPcache は PHP 8.5 から本体に組み込まれ、docker-php-ext-install ではビルドできない（cp: cannot stat 'modules/*'）。
# 公式イメージで最初から読み込まれている版もあるため、読み込まれていないときだけビルドする
RUN apt-get update && apt-get install -y \
  wget curl vim net-tools iputils-ping unzip \
  libpng-dev libjpeg-dev libfreetype6-dev \
  libzip-dev libxml2-dev libicu-dev libcurl4-openssl-dev \
  libonig-dev libxslt1-dev libexif-dev \
  msmtp msmtp-mta \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install gd mysqli curl mbstring zip xml intl bcmath soap exif \
    $(php -r 'echo extension_loaded("Zend OPcache") ? "" : "opcache";') \
  && a2enmod rewrite headers \
  && rm -rf /var/lib/apt/lists/*

# 権限調整
RUN groupmod -g ${GROUP_ID} www-data && \
    usermod -u ${USER_ID} -g www-data www-data && \
    mkdir -p /var/www/html && chown -R www-data:www-data /var/www/html

# PHPからmsmtpを使うよう設定
RUN echo 'sendmail_path = "/usr/bin/msmtp -t -i"' > /usr/local/etc/php/conf.d/msmtp.ini && \
    ln -sf /usr/bin/msmtp /usr/sbin/sendmail

# Apache を 80 と 8080 で動かす
# - ホストからは 8080 へつなぐ（compose.yml などの ports）
# - 80 は、コンテナの中からサイトの URL（ポートなし）で自サイトを取りに行く処理のため（HTML エクスポートなど）
# - Docker 20.10 以降の bridge ネットワークでは、コンテナの中の net.ipv4.ip_unprivileged_port_start が 0 になるので、www-data のままで 80 を開ける
#   （host ネットワークなどではこの前提が成り立たない）
RUN sed -i 's/^Listen 80$/Listen 80\nListen 8080/' /etc/apache2/ports.conf && \
    sed -i 's/<VirtualHost \*:80>/<VirtualHost *:80 *:8080>/' /etc/apache2/sites-enabled/000-default.conf

# Apache起動
COPY manager/docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

USER www-data

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
