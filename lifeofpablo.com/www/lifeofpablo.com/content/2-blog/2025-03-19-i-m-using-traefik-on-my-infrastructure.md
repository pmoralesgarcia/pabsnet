---
Title: I'm using traefik on my infrastructure.
Published: 2025-03-19 21:49:27
Author: Pablo Morales
Layout: blog
Tag: 2025, infrastructure, traefik, proxy, nginx, replacement
Description: 
Image: https://static.lifeofpablo.com/media/blog/traefik/traefik-proxy-logo.png
ImageAlt: Traefik Logo
---
### Some Background
For a few weeks, I've been thinking about using another reverse-proxy service just to try something new.

Since I launched my docker mono-repository that hosts my entire infrastructure, I've used nginx as my primary reverse-proxy. I was using [Jason WIlder](http://jasonwilder.com/blog/2014/03/25/automated-nginx-reverse-proxy-for-docker/)'s [nginx proxy](https://hub.docker.com/r/jwilder/nginx-proxy/tags) to expose my applications to the web from the virtual machine. 

Now I have transitioned to using [traefik](https://traefik.io/traefik/). It was very easy to add labels specific to traefik to my services in my docker compose file. 

In my docker compose setup, I used Let's Encrypt for SSL certificates in both nginx and traefik.

Just like with any major change, I was worried about downtime but I took the risk and I tested out *lifeofpablo.com * and some other subdomains. Once I labeled the items with traefik specific items, and redeployed, it worked great! Once I looked around for anything out of place, I went back and added traefik labels to the rest of my containers. 

### nginx

Within the docker file, nginx-proxy would use environmental variables to route outside of the machine to the domain or subdomain that the DNS will point to. 

``` docker
   environment:
      VIRTUAL_HOST: service.lifeofpablo.com
      LETSENCRYPT_HOST: service.lifeofpablo.com
```

### traefik

Instead of using environmental variables explicitly, traefik uses labels to route. In this example, I'm using my the service *korea.lifeofpablo.com* (korea) as the service being proxied.

I simply matched the service name, *korea* as this is the name of the container in Docker and for the host, I used *korea.lifeofpablo.com*.

```
labels:
      - "traefik.http.routers.korea.rule=Host(`korea.lifeofpablo.com`)"
      - "traefik.http.routers.korea.entrypoints=websecure"
      - "traefik.http.routers.korea.tls.certresolver=myresolver"
```

### Future

What I have in mind for the future of my infrastructure with traefik is to control authentication. I want to use [Zitadel](https://zitadel.com/) to add authentication to services I have to simply experiment. This experimentation will be key as I continue planning the rebuild of my website. I want my future version of my website to be the center of my internet presence and be able to connect easily with my services. 
