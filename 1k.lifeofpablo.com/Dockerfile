FROM debian:bookworm

RUN apt update -y && \
	apt install -y nginx php-fpm zip wget unzip curl sendmail

RUN curl -sSL https://packages.sury.org/php/README.txt | bash -x

RUN apt update -y && \
	apt install -y php-fpm php-zip php-curl php-gd php-mbstring 

COPY ./www /var/www/html

WORKDIR /var/www/html

COPY ./nginx/default.conf /etc/nginx/sites-enabled/default

EXPOSE 80

COPY ./1k.lifeofpablo.com/start.sh / 

CMD ["sh", "/start.sh"]




