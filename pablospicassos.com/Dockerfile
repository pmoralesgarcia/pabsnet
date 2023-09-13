FROM alpine:3.18.3

RUN apt update -y && \
	apt install -y nginx php-fpm zip wget unzip curl sendmail

RUN curl -sSL https://packages.sury.org/php/README.txt | bash -x

RUN apt update -y && \
	apt install -y php-fpm php-zip php-curl php-gd php-mbstring php-dom php-json 


VOLUME /var/www/pablospicassos.com

WORKDIR  /var/www/pablospicassos.com


RUN chmod -R a+rw /var/www/pablospicassos.com

COPY ./nginx/pablospicassos.com.conf /etc/nginx/sites-enabled/default

EXPOSE 80

COPY ./start.sh / 

CMD ["sh", "/start.sh"]




