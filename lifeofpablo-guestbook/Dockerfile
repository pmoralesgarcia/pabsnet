FROM debian:bookworm

RUN apt update -y && \
	apt install -y nginx php-fpm zip wget unzip curl

RUN curl -sSL https://packages.sury.org/php/README.txt | bash -x

RUN apt update -y && \
	apt install -y php-fpm php-zip php-curl php-gd php-mbstring 

#RUN mkdir -p /var/www/guestbook  

VOLUME /var/www/guestbook

WORKDIR ./www/guestbook


COPY ./nginx/guestbook.conf /etc/nginx/sites-enabled/default

EXPOSE 80

COPY ./start.sh / 

CMD ["sh", "/start.sh"]




