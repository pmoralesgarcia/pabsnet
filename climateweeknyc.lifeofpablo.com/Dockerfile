FROM debian:bookworm

RUN apt update -y && \
	apt install -y nginx zip wget unzip curl

COPY ./www/climateweeknyc.lifeofpablo.com /var/www/climateweeknyc.lifeofpablo.com

WORKDIR /var/www/climateweeknyc.lifeofpablo.com


COPY ./nginx/climateweeknyc.lifeofpablo.com.conf /etc/nginx/sites-enabled/default

EXPOSE 80

COPY ./start.sh / 

CMD ["sh", "/start.sh"]




