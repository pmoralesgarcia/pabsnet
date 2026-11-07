# Static site, so plain nginx is all it needs. The base image's own CMD runs nginx.
FROM nginx:stable-alpine

COPY ./nginx/now.conf /etc/nginx/conf.d/default.conf
COPY ./www/now /var/www/now

EXPOSE 80
