---
Title: Setting up NGINX and PHP-FPM Dockerfile
Published: 2024-01-15 22:08:07
Author: Pablo Morales
Layout: blog
Tag: 2024
Description: 
Image: https://i.ytimg.com/vi/1P54UoBjbDs/maxresdefault.jpg
ImageAlt: nginx and php-fpm logos. Image from Eightyfy
Status: unlisted
---
This weekend I finally and successfully moved my website, [lifeofpablo.com](https://lifeofpablo.com/) and other web infrastructure to a new [Digital Ocean Droplet](https://www.digitalocean.com/products/droplets). Moving was simple since I used [Docker](https://www.docker.com/), I made use of a ```docker-compose.yml``` file and used Github Actions to point to the new machine. 

I would like to share how I create a Dockerfile to create an image/container that includes both NGINX and PHP-FPM

I use [Ondřej Surý's](https://deb.sury.org/) PHP repositories. 

Folder Structure
```
pablos-infra/
├── php-app/
│   ├── Dockerfile
│   ├── nginx/
│   │   └── default.conf
│   ├── www/
│   │   └── files to be served go here
│   └── start.sh
├── other-php-app (optional)
├── other-services (optional)
└── docker-compose.yml
```
Dockerfile
``` dockerfile {.with-line-number}

FROM debian:bookworm

RUN apt update -y && \
	apt install -y nginx zip wget unzip curl

RUN curl -sSL https://packages.sury.org/php/README.txt | bash -x

RUN apt update -y && \
	apt install -y php8.2-fpm php8.2-gd php8.2-cli php8.2-curl php8.2-bz2 php8.2-mbstring php8.2-intl php8.2-zip

COPY ./php-app/nginx/nginx.conf /etc/nginx/sites-enabled/default

VOLUME /var/www/html

WORKDIR  /var/www/html

RUN chmod -R a+rw /var/www/html

EXPOSE 80

COPY ./php-app/start.sh / 

CMD ["sh", "/start.sh"]

```

docker-compose.yml 
``` yaml {.with-line-number}
services:
  php-app:
    container_name: php-app
    restart: unless-stopped 
    build:
      dockerfile: ./php-app/Dockerfile  
    volumes:
      #Project root
      - ./php-app/www:/var/www/html
    ports:
      - "8080:80"
```

``` nginx {.with-line-number}
server {
    listen 80;
    server_name localhost;
    root /var/www/html;
    index index.php;

  location / {
    try_files $uri $uri/ /index.php?$query_string;
  }

  location ~ \.php$ {
    fastcgi_split_path_info ^(.+\.php)(/.+)$;
    fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    fastcgi_index index.php;
    include fastcgi.conf;
  }
}
```
start.sh
``` bash {.with-line-number}
#!/bin/sh
php-fpm8.2
nginx -g 'daemon off;'
```
