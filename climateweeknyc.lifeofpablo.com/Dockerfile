# Static site, so plain nginx is all it needs. The base image's own CMD runs nginx.
FROM nginx:stable-alpine

COPY ./climateweeknyc.lifeofpablo.com/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY ./climateweeknyc.lifeofpablo.com/www /var/www/html

EXPOSE 80
