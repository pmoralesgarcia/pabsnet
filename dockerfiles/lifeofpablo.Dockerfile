FROM debian:bookworm

RUN apt update -y && \
	apt install -y nginx php-fpm zip wget unzip curl sendmail

RUN curl -sSL https://packages.sury.org/php/README.txt | bash -x

RUN apt update -y && \
	apt install -y php-fpm php-zip php-curl php-gd php-mbstring 

#RUN mkdir -p /var/www/lifeofpablo.com  

#VOLUME /var/www/lifeofpablo.com

#WORKDIR  /var/www/lifeofpablo.com


RUN chmod -R a+rw /var/www/lifeofpablo.com

COPY ./lifeofpablo.com/nginx/lifeofpablo.com.conf /etc/nginx/sites-enabled/default

EXPOSE 80

COPY ./start.sh / 

CMD ["sh", "/start.sh"]




