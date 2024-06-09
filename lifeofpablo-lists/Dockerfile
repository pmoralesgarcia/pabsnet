FROM debian:bookworm

RUN apt update -y && \
	apt install -y nginx zip wget unzip curl

RUN curl -sSL https://packages.sury.org/php/README.txt | bash -x

RUN apt update -y && \
	apt install -y php8.3-fpm php8.3-gd php8.3-cli php8.3-curl php8.3-bz2 php8.3-mbstring php8.3-intl php8.3-zip php8.3-mysql

COPY ./lifeofpablo-lists/www /var/www/html


VOLUME /var/www/html

WORKDIR  /var/www/html

COPY ./lifeofpablo-lists/nginx/default.conf /etc/nginx/sites-enabled/default

RUN chmod -R a+rw /var/www/html

EXPOSE 80

COPY ./lifeofpablo-lists/start.sh / 

CMD ["sh", "/start.sh"]




