FROM debian:bookworm

RUN apt update -y && \
	apt install -y nginx php-fpm zip wget unzip curl sendmail

RUN curl -sSL https://packages.sury.org/php/README.txt | bash -x

RUN apt update -y && \
	apt install -y php-fpm php-zip php-curl php-gd php-mbstring 


VOLUME /var/www/korea.lifeofpablo.com

WORKDIR  /var/www/korea.lifeofpablo.com

RUN chmod -R a+rw /var/www/korea.lifeofpablo.com

COPY ./nginx/korea.lifeofpablo.com.conf /etc/nginx/sites-enabled/default

EXPOSE 80

COPY ./start.sh / 

CMD ["sh", "/start.sh"]




