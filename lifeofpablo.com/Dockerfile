FROM ubuntu:23.10

RUN LC_ALL=C.UTF-8 add-apt-repository ppa:ondrej/php

RUN apt update -y && \
	apt install -y nginx php-fpm zip wget unzip curl

RUN curl -sSL https://packages.sury.org/php/README.txt | bash -x

RUN apt update -y && \
	apt install -y php-fpm php-zip php-curl php-gd php-mbstring 

#RUN mkdir -p /var/www/lifeofpablo.com  

VOLUME /var/www/lifeofpablo.com

WORKDIR  /var/www/lifeofpablo.com

#RUN curl -L https://github.com/datenstrom/yellow/archive/main.zip -o yellow-main.zip

#RUN unzip yellow-main 

#RUN mv ./yellow-main/* .

#COPY ./www/lifeofpablo.com /tmp/lifeofpablo.com

#COPY ./www/lifeofpablo.com /var/www/lifeofpablo.com/

RUN chmod -R a+rw /var/www/lifeofpablo.com

COPY ./lifeofpablo.com/nginx/lifeofpablo.com.conf /etc/nginx/sites-enabled/default

EXPOSE 80

COPY ./lifeofpablo.com/start.sh / 

CMD ["sh", "/start.sh"]




