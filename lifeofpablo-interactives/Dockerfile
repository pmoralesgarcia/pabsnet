FROM debian:bookworm

RUN apt update -y && \
	apt install -y nginx zip wget unzip curl

RUN curl -sSL https://packages.sury.org/php/README.txt | bash -x

RUN apt update -y && \
	apt install -y php8.2-fpm php8.2-gd php8.2-cli php8.2-curl php8.2-bz2 php8.2-mbstring php8.2-intl php8.2-zip

COPY ./lifeofpablo-interactives/nginx/default.conf /etc/nginx/sites-enabled/default

VOLUME /var/www/html

WORKDIR  /var/www/html

RUN chmod -R a+rw /var/www/html

EXPOSE 80

COPY ./lifeofpablo-interactives/start.sh / 

CMD ["sh", "/start.sh"]




