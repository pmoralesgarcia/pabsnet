FROM debian:stable

RUN apt update -y && \
	apt install -y nginx zip wget unzip curl

COPY ./lifeofpablo-linkinbio/nginx/linkinbio.conf /etc/nginx/sites-enabled/default

VOLUME /var/www/html

WORKDIR  /var/www/html

RUN chmod -R a+rw /var/www/html

EXPOSE 80

COPY ./lifeofpablo-linkinbio/start.sh / 

CMD ["sh", "/start.sh"]




