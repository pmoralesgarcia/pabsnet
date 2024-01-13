FROM php:8.2-fpm-alpine

RUN apt update -y && \
	apt install -y --no-install-recommends \ zip wget unzip curl

RUN apt update -y && \
	apt install -y --no-install-recommends \ php-zip php-curl php-gd php-mbstring 

COPY ./php/start.sh / 

CMD ["sh", "/start.sh"]
