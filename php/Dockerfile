FROM php:8.2-fpm-bookworm

RUN curl -sSL https://packages.sury.org/php/README.txt | bash -x
RUN apt update

# Update and Download Packages Debian
RUN apt-get update --yes

# Install Dependencies
RUN apt-get install --yes \
 zip \
 wget \
 unzip \
 curl

RUN curl -s https://packages.sury.org/php/apt.gpg | apt-key add -

# Install Extensions
RUN docker-php-ext-install gd
RUN docker-php-ext-install intl
RUN docker-php-ext-install mbstring
RUN docker-php-ext-install zip

# Memory Limit
RUN echo "memory_limit=-1" > $PHP_INI_DIR/conf.d/memory-limit.ini

# Time Zone
RUN echo "date.timezone=${PHP_TIMEZONE:-UTC}" > $PHP_INI_DIR/conf.d/date_timezone.ini



