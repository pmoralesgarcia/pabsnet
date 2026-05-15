FROM debian:bookworm

RUN apt update -y && \
    apt install -y nginx zip wget unzip curl

RUN curl -sSL https://packages.sury.org/php/README.txt | bash -x

RUN apt update -y && \
    apt install -y php8.2-fpm php8.2-gd php8.2-cli php8.2-curl php8.2-bz2 php8.2-mbstring php8.2-intl php8.2-zip

COPY ./login.lifeofpablo.com/nginx/login.lifeofpablo.com.conf /etc/nginx/sites-enabled/default

# Remove default index files
RUN rm -rf /var/www/html/*

WORKDIR /var/www/html

# Copy the start script to the root
COPY ./login.lifeofpablo.com/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80

# Run the start script
CMD ["/bin/bash", "/start.sh"]