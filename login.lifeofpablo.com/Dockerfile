FROM debian:bookworm

RUN apt update -y && \
	apt install -y nginx php-fpm zip wget unzip curl

RUN curl -sSL https://packages.sury.org/php/README.txt | bash -x

RUN apt update -y && \
	apt install -y php-fpm php-zip php-curl php-gd php-mbstring 

#RUN mkdir -p /var/www/login.lifeofpablo.com  

COPY ./www/login.lifeofpablo.com /var/www/login.lifeofpablo.com/


VOLUME /var/www/login.lifeofpablo.com

WORKDIR  /var/www/login.lifeofpablo.com


#RUN curl -L https://github.com/datenstrom/yellow/archive/main.zip -o yellow-main.zip

#RUN unzip yellow-main 

#RUN mv ./yellow-main/* .

#COPY ./www/login.lifeofpablo.com /tmp/login.lifeofpablo.com

RUN chmod -R a+rw /var/www/html
#RUN chmod -R a+rw *

COPY ./login.lifeofpablo.com/nginx/login.lifeofpablo.com.conf /etc/nginx/sites-enabled/default

EXPOSE 80

COPY ./login.lifeofpablo.com/start.sh . 

CMD ["sh", "/start.sh"]




