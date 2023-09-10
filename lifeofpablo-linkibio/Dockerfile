FROM debian:bookworm

RUN apt update -y && \
	apt install -y nginx php-fpm zip wget unzip curl



#RUN mkdir -p /var/www/littlelink.conf

VOLUME /var/www/linkinbio

WORKDIR /var/www/linkinbio


COPY ./nginx/littlelink.conf /etc/nginx/sites-enabled/default

EXPOSE 80

COPY ./start.sh / 

CMD ["sh", "/start.sh"]




