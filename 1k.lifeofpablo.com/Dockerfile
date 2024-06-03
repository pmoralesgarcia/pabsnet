FROM debian:bookworm

RUN apt update -y && \
	apt install -y nginx php-fpm zip wget unzip curl sendmail

RUN curl -sSL https://packages.sury.org/php/README.txt | bash -x

RUN apt update -y && \
apt install -y php8.3-fpm php8.3-gd php8.3-cli php8.3-curl php8.3-bz2 php8.3-mbstring php8.3-intl php8.3-zip php8.3-mysql

COPY ./1k.lifeofpablo.com /var/www/html

WORKDIR /var/www/html

COPY ./1k.lifeofpablo.com/nginx/default.conf /etc/nginx/sites-enabled/default

EXPOSE 80

COPY ./1k.lifeofpablo.com/start.sh / 

CMD ["sh", "/start.sh"]




