---
Title: How to Protect your sites Vouch Proxy, NGINX, Reverse Proxies with Docker Compose
TitleSlug: Protect Sites with Vouch Proxy and Docker Compose
Published: 2023-11-28 19:30:49
Author: Pablo Morales
Layout: blog
Tag: Vouch Proxy, Docker, Docker Compose, Reverse Proxy, nginx
Description: Protect your sites using Vouch Proxy and Docker Compose
Status: draft
---
I've written in the past how to install Vouch Proxy using Debian. I also wrote a post a while ago where I [dockerized my site and services](/blog/dockerizing-my-website-and-services). So let's state for the record, I like Vouch Proxy. It's met my needs. 

I'm going to share how I setup the following services using Docker Compose:

* [Vouch Proxy](https://github.com/vouch/vouch-proxy)
* NGINX Reverse Proxy
* Creating Reverse Proxies for your apps to be protected with Vouch Proxy
  * Provide Example Apps as use cases.
  * [Grafana](https://grafana.com/) and [Prometheus](https://prometheus.io/)

This guide, is recommended for those who have experience with [Docker](https://docker.com) and [Docker Compose](https://docs.docker.com/compose/). I will keep this simple for you to follow along if you don't have experience. When using Docker Compose and docker-compose.yml files, you are launching multiple containers at one time. When using a Dockerfile, one container is launched at a time. 

We'll using a lot of environmental variables to configure our applications. It seems like a lot of work at first but you'll be happy that you did. 

## Let's Setup Docker Compoose

## Vouch Proxy Config

## NGINX Reverse Proxy

## Setup MariaDB

## Setup Grafana

## Setup a separate NGINX Reverse Proxy with VP