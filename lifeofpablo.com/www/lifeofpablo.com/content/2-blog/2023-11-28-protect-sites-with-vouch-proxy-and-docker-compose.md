---
Title: How to Protect your sites Vouch Proxy, NGINX, Reverse Proxies with Docker Compose
TitleSlug: Protect Sites with Vouch Proxy and Docker Compose
Published: 2023-11-28 19:30:49
Author: Pablo Morales
Layout: blog
Tag: Vouch Proxy, Docker, Docker Compose, Reverse Proxy, nginx
Description: Protect your sites using Vouch Proxy and Docker Compose
Status: unlisted
---
I've written in the past how to install Vouch Proxy using Debian. I also wrote a post a while ago where I [dockerized my site and services](/blog/dockerizing-my-website-and-services). So let's state for the record, I like Vouch Proxy. It's met my needs. 

I'm going to share how I setup the following services using Docker Compose:

* [Vouch Proxy](https://github.com/vouch/vouch-proxy) [image vp1.png]
* NGINX Reverse Proxy
* Creating Reverse Proxies for your apps to be protected with Vouch Proxy
  * Provide Example Apps as use cases.
  * [Grafana](https://grafana.com/) and [Prometheus](https://prometheus.io/)

This guide, is recommended for those who have experience with [Docker](https://docker.com) and [Docker Compose](https://docs.docker.com/compose/). I will keep this simple for you to follow along if you don't have experience. When using Docker Compose and docker-compose.yml files, you are launching multiple containers at one time. When using a Dockerfile, one container is launched at a time. 

We'll using a lot of environmental variables to configure our applications. It seems like a lot of work at first but you'll be happy that you did. 

## Let's Setup Docker Compose
Here is the docker compose we are going to use. I will break it down piece by piece.

``` yaml
services:
  nginx:
    container_name: nginx
    image: nginxproxy/nginx-proxy
    restart: unless-stopped
    ports:
        - 80:80
        - 443:443
    volumes:
        - /var/run/docker.sock:/tmp/docker.sock:ro
        - /var/docker/nginx/html:/usr/share/nginx/html
        - /var/docker/nginx/certs:/etc/nginx/certs
        - /var/docker/nginx/vhost:/etc/nginx/vhost.d
        - /var/docker/nginx/conf:/etc/nginx/conf.d
    logging:
        options:
            max-size: "10m"
            max-file: "3"
  letsencrypt-companion:
    container_name: letsencrypt-companion
    image: jrcs/letsencrypt-nginx-proxy-companion
    restart: unless-stopped
    volumes_from:
        - nginx
    volumes:
        - /var/run/docker.sock:/var/run/docker.sock
        - /var/docker/nginx/acme:/etc/acme.sh
    environment:
        DEFAULT_EMAIL: your-email@domain.com
  mariadb:
    container_name: mariadb
    image: mariadb:latest
    command: --default-authentication-plugin=mysql_native_password
    environment:
      MYSQL_ROOT_PASSWORD: changeme
      MYSQL_DATABASE: kanboard
      MYSQL_USER: kanboard
      MYSQL_PASSWORD: changeme
    volumes:
      - mariadb:/var/lib/mysql:z
  vouch-proxy-auth:
    container_name: vp-proxy
    image: quay.io/vouch/vouch-proxy:alpine-latest
    ports:
      - 9090:9090
    volumes:
      - ./vouch-proxy-config:/config
    restart: always
    environment:
      VIRTUAL_HOST: your-domain.com
      LETSENCRYPT_HOST: your-domain.com
  grafana:
    container_name: grafana
    image: grafana/grafana:latest
    volumes:
      - ../plugins/:/etc/grafana/plugins/ # For locally developed plugins
      - ./grafana/provisioning/:/etc/grafana/provisioning/ # Automatically configure datasources
      - grafana_vol:/var/lib/grafana # Volume to persist configuration between restarts
    environment:
      - "GF_SECURITY_ADMIN_PASSWORD=pwd"
      - GF_USERS_ALLOW_SIGN_UP=FALSE
      - GF_USERS_AUTO_ASSIGN_ORG=TRUE
      - GF_USERS_AUTO_ASSIGN_ORG_ROLE=EDITOR
      - GF_AUTH_PROXY_ENABLED=true                  # Enable authentication via a proxy
      - GF_AUTH_PROXY_HEADER_NAME=X-Vouch-User   # Header that grafana will expect (do not change)
      - GF_AUTH_PROXY_HEADER_PROPERTY=email         # Either email or username depending on what will be in the token
      - GF_AUTH_PROXY_AUTO_SIGN_UP=false
      - GF_INSTALL_PLUGINS=grafana-azure-data-explorer-datasource # Auto install plugins from grafana.com
      - GF_SERVER_HTTP_PORT=3001
      - GF_SERVER_PROTOCOL=http
      - GF_SERVER_DOMAIN=grafana.domain.com
      - GF_SERVER_ROOT_URL=grafana.domain.com
      - GF_SERVER_SERVE_FROM_SUB_PATH=false
      - GF_SMTP_ENABLED=TRUE
      - "GF_SMTP_HOST=smtp.domain.com"
      - "GF_SMTP_USER=smtp-user"
      - GF_SMTP_PASSWORD=changeme
      - "GF_SMTP_FROM_ADDRESS=grafana@domain.com"
      - "GF_SMTP_FROM_NAME=Name of Grafana Instance"
      - "GF_SMTP_STARTTLS_POLICY=MANDATORYSTARTTLS" #may or may not need on needs
    expose:
      - 3001
  vp-proxy-graf:
    image: nginx:latest
    container_name: vp-proxy-graf
    environment:
      VIRTUAL_HOST: grafana.domain.com
      LETSENCRYPT_HOST: grafana.domain.com
    volumes:
      - ./prometheus-grafana/nginx/graf:/etc/nginx/conf.d
    ports:
      - 8081:80
  prometheus:
    image: prom/prometheus:latest
    container_name: prometheus
    restart: unless-stopped
    volumes:
      - ./prometheus-grafana/prometheus/prometheus.yml:/etc/prometheus/prometheus.yml
      - prometheus_data:/prometheus
    command:
      - '--config.file=/etc/prometheus/prometheus.yml'
      - '--storage.tsdb.path=/prometheus'
      - '--web.console.libraries=/etc/prometheus/console_libraries'
      - '--web.console.templates=/etc/prometheus/consoles'
      - '--web.enable-lifecycle'
    expose:
      - 9091
  vp-proxy-prom:
    image: nginx:latest
    container_name: vp-proxy-prom
    environment:
      VIRTUAL_HOST: prometheus.domain.com
      LETSENCRYPT_HOST: prometheus.domain.com
    volumes:
      - ./prometheus-grafana/nginx/prom:/etc/nginx/conf.d
    ports:
      - 8082:80
  node-exporter:
    image: prom/node-exporter:latest
    container_name: node-exporter
    restart: unless-stopped
    volumes:
      - /proc:/host/proc:ro
      - /sys:/host/sys:ro
      - /:/rootfs:ro
    command:
      - '--path.procfs=/host/proc'
      - '--path.rootfs=/rootfs'
      - '--path.sysfs=/host/sys'
      - '--collector.filesystem.mount-points-exclude=^/(sys|proc|dev|host|etc)($$|/)'
    expose:
      - 9100
volumes:
  prometheus_data: {}
  grafana_vol:
  mariadb:
  prom_data:
```

## Setup Vouch Proxy Config

``` yaml
# Vouch Proxy configuration
# bare minimum to get Vouch Proxy running with google

vouch:
  logLevel: debug
  listen: 0.0.0.0

  domains:
    - your-base-domain.com
      #  vouch.cookie.domain: your-base-domain.com

  cookie:
    secure: true
    domain: your-base-domain.com


oauth:
  provider: google
  # get credentials from...
  # https://console.developers.google.com/apis/credentials
  client_id: your-client-id
  client_secret: your-client-secret
  # Google may require callback_urls (redirect URIs) to be 'https'
  callback_urls:
  - https://vouch.domain.com/auth
  preferredDomain: your-base-domain.com # be careful with this option, it may conflict with chrome on Android
  # endpoints are set from https://godoc.org/golang.org/x/oauth2/google
```

## NGINX Reverse Proxy

## Setup MariaDB

## Setup Grafana

## Setup a separate NGINX Reverse Proxy with VP