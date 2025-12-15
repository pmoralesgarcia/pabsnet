FROM debian:bookworm

RUN apt update -y && \
	apt install -y nginx zip wget unzip curl

RUN curl -sSL https://packages.sury.org/php/README.txt | bash -x

RUN apt update -y && \
	apt install -y php8.4-fpm php8.4-gd php8.4-cli php8.4-curl php8.4-bz2 php8.4-mbstring php8.4-intl php8.4-zip php8.4-mysql


COPY ./lifeofpablo.com/nginx/lifeofpablo.com.conf /etc/nginx/sites-enabled/default

VOLUME /var/www/html

WORKDIR  /var/www/html

RUN chmod -R a+rw /var/www/html

EXPOSE 80

COPY ./lifeofpablo.com/start.sh / 

CMD ["sh", "/start.sh"]




