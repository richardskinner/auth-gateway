FROM php:7.1-apache

# Install PHP extensions
#RUN curl -sL https://deb.nodesource.com/setup_6.x | bash - \
RUN apt-get update && apt-get install -y \
      apt-utils \
      libicu-dev \
      libpq-dev \
      zlib1g-dev \
      libmcrypt-dev

RUN docker-php-ext-install zip mbstring mcrypt opcache

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer

# Install Xdebug
RUN curl -fsSL 'https://xdebug.org/files/xdebug-2.6.0.tgz' -o xdebug.tar.gz \
    && mkdir -p xdebug \
    && tar -xf xdebug.tar.gz -C xdebug --strip-components=1 \
    && rm xdebug.tar.gz \
    && ( \
    cd xdebug \
    && phpize \
    && ./configure --enable-xdebug \
    && make -j$(nproc) \
    && make install \
    && make test \
    ) \
    && rm -r xdebug \
    && docker-php-ext-enable xdebug

WORKDIR /var/www/html

COPY ./ /var/www/html

RUN composer install

RUN ./vendor/bin/phpunit

RUN ./vendor/bin/phpunit --coverage-html coverage