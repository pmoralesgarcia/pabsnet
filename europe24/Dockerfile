FROM debian:bookworm

RUN apt update -y && \
	apt install -y nginx zip wget unzip curl

RUN curl -sSL https://packages.sury.org/php/README.txt | bash -x

RUN apt update -y && \
	apt install -y php8.3-fpm php8.3-gd php8.3-cli php8.3-curl php8.3-bz2 php8.3-mbstring php8.3-intl php8.3-zip

COPY ./europe24/nginx/europe24.conf /etc/nginx/sites-enabled/default

VOLUME /var/www/html

WORKDIR  /var/www/html

RUN chmod -R a+rw /var/www/html

EXPOSE 80

COPY ./europe24/start.sh / 

CMD ["sh", "/start.sh"]




