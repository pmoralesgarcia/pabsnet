FROM debian:bookworm

RUN apt update -y && \
	apt install -y nginx zip wget unzip curl

RUN curl -sSL https://packages.sury.org/php/README.txt | bash -x 

COPY ./www/1000.lifeofpablo.com /var/www/1000.lifeofpablo.com

WORKDIR /var/www/1000.lifeofpablo.com


COPY ./nginx/1000.lifeofpablo.com.conf /etc/nginx/sites-enabled/default

EXPOSE 80

COPY ./start.sh / 

CMD ["sh", "/start.sh"]




