---
Title: Configuring Vouch Proxy with Indieauth
Published: 2023-07-12 21:49:59
Author: Pablo Morales
Layout: blog
Tag: vouch proxy, vouch-proxy, authentication, indieauth, indielogin, indieweb, decentralized, sso, OAuth, OIDC, SSO, IdP, nginx, reverse proxy,technology, zero trust
Image: vp-iwc-2.png
---

  <article class="bg-white" markdown="1">
<img src="https://lifeofpablo.com/media/images/vp-iwc-2.png" class="db w-100"/>


Location:
<a class="h-geo" href="https://www.latlong.net/c/?lat=38.581573&long=-121.494400">
  <span class="p-latitude">38.581573</span>,
  <span class="p-longitude">-121.494400</span>
</a>


Table of Contents

[toc]


## Introduction
A few months ago, I wrote a [blog post](https://lifeofpablo.com/blog/setting-up-vouch-proxy-using-nginx) on Vouch Proxy using Google as an idP, or Identity provider. This time I will be writing on how to configure vouch proxy to use [indieAuth ](https://indieauth.spec.indieweb.org/)as the idP. 

This blog post will be very similar to the blog post mentioned above but we will focus on indieAuth.

Today, I'll demonstrate how to setup [Vouch Proxy](https://github.com/vouch/vouch-proxy) on an [nginx](https://www.nginx.com/) web server. In this example I will be using **[IndieAuth](https://indieauth.spec.indieweb.org/)** as our provider using a very minimal configuration.

**This tutorial assumes you have prior knowledge of using a linux server such as Debian. Message me at hello@lifeofpablo.com if you need some help. I'd be happy to do so!**


## Use Cases
I currently use it for:
* Signing into my website
    * Using it for my guestbook
* Sign into my RSS feeder. 
    * Using same cookie on my website. Similar to single sign on.

## What Vouch Proxy Does?
**[According to the Repository README.md, it states the following:](https://github.com/vouch/vouch-proxy/blob/master/README.md)**

Vouch Proxy (VP) forces visitors to login and authenticate with an IdP (such as one of the services listed above) before allowing them access to a website.

![Vouch Proxy protects websites](https://github.com/vouch/vouch-proxy/blob/master/examples/nginx-vouch-private_simple.png?raw=true)

VP can also be used as a Single Sign On (SSO) solution to protect all web applications in the same domain.

![Vouch Proxy is a Single Sign On solution](https://github.com/vouch/vouch-proxy/blob/master/examples/nginx-vouch-private_appA_appB_appC.png?raw=true)

After a visitor logs in Vouch Proxy allows access to the protected websites for several hours. Every request is checked by VP to ensure that it is valid.

VP can send the visitor's email, name and other information which the IdP provides (including access tokens) to the web application as HTTP headers. VP can be used to replace application user management entirely. In our case, we are passing an HTTP header with your domain to sign into sites that support indieauth protocol. 

An example of an HTTP header being passed is my domain, https://lifeofpablo.com.


## Things you'll need/prepare:
* A linux server with a public IP address with hosting and SSL
 * Debian will be used here but any of the common distros will work
 * Certbot is an easy solution to get SSL certifcate for *https://*
* [Go Language](https://go.dev/doc/install) (to compile vouch-proxy)
* [Vouch Proxy](https://github.com/vouch/vouch-proxy) 
* Make sure your website is [setup ](https://indieweb.org/IndieAuth)for use with the Indieauth protocol.
* [Nginx Web Server](https://www.nginx.com/)
* [Digital Ocean](https://www.digitalocean.com/community/tutorials/how-to-install-nginx-on-debian-11) has a good guide if you need to learn how to setup virtual blocks in nginx.

## Download/Install Vouch Proxy from Github
Make sure to have [Go Lang](https://go.dev/doc/install) installed. Follow the instructions for your operating system. In my case I am using debian.

Download [Vouch Proxy](https://github.com/vouch/vouch-proxy) from it's Github repository. 

``` bash
$ git clone https://github.com/vouch/vouch-proxy.git
$ cd vouch-proxy
$ ./do.sh goget
$ ./do.sh build
```
## Vouch Proxy Nginx Virtual Block

Let's go ahead and create a virtual block to proxy Vouch Proxy.

``` nginx
server {

        server_name vouch.domain.com; #decide what subdomain you will use.

       # Proxy to your Vouch instance
  location / {
    proxy_set_header  Host $http_host; #this variable will match your domain above
    proxy_set_header  X-Forwarded-Proto https;
    proxy_pass        http://127.0.0.1:9090;  #Default port is 9090 but you can change it if that port is already used. Remember this port for later.
  }
}

```

Let's go ahead and create a virtual block for a regular nginx website site or edit an existing virtual block. This is the website/service that you will protect with Vouch Proxy.

In this example I am using a php web app. If you a non php site site to work you can remove this location block and and edit it to your needs.


## Vouch Proxy config with Indieauth.com as a service provider.


### Modify your config.yml
This will get you going, I have added some of my personal settings such as public access. Public access allows people to access your "protected app" without needing to login until you need them to login. Here are some options in how you can allow people to use your app. It is important to align the config in the config.yml file. Even an extra space can cause Vouch Proxy to fail. More on that in the next section.

Options

* You can allow public access to the site but only allow everyone the option to sign into the website with a basic PHP script or use the corresponding http_header to use the website/web app to authenticate . 
    * Comment out or delete the line, "allowAllUsers: true" and include the line," publicAccess: true" .
* You can allow public access to the site but only allow certain people, (or in this case, domains) actually sign into the website. 
    * Comment out or delete the line, "allowAllUsers: true" and include the line," publicAccess: true" .
* You can force everyone to sign in before seeing the website but still allow everyone to access the website.
    * Include the "allowAllUsers: true" and comment out or delete the line,," publicAccess: true" .

``` yaml
# Vouch Proxy configuration
# bare minimum to get Vouch Proxy running with IndieAuth
# This setup current is running side by side with another vouch proxy instance

vouch:
  logLevel: debug
  listen: 0.0.0.0
  port: 9090
  allowAllUsers: true
  cookie:
    secure: false
    domain: lifeofpablo.com
  publicAccess: true
oauth:
  # IndieAuth
  # https://indielogin.com/api
  provider: indieauth
  client_id: https://lifeofpablo.com
  auth_url: https://indieauth.com/auth
  callback_url: https://auth.lifeofpablo.com/auth
```
## Run/test your Vouch Proxy configuration.

Run the following command

``` bash
nohup ./vouch-proxy -loglevel debug > vouch.log 2>&1 &
```
It should display a process ID (PID)

``` bash


[1] 53310

```


Hit enter. If no error or exit code displays, Vouch proxy is running! 

If there is an error, it will exit such as the example below.


``` bash


[1]+  Exit 126                nohup ./vouch-proxy -loglevel debug > vouch.log 2>&1

```

If there is an error, make sure your there is not weird spacing or errors in the configuration. If you are sure that you have the information correct, use the [examples provided by Vouch Proxy](https://github.com/vouch/vouch-proxy/blob/master/config/config.yml_example_indieauth) and copy and paste the example to get the formatting correct. Adjust the configuration as needed to match your needs.

## Nginx Virtual block protected by Vouch Proxy

``` nginx
server {
        listen 80;
        listen [::]:80;
        root /root/to/web/directory;
        index index.php index.html;
        server_name secretapp.example.com;

location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }
    client_max_body_size 100m;

 location ~* \.php$ {
try_files $uri =404;
    fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;# Adjust to your setup
    include fastcgi.conf;
    fastcgi_split_path_info ^(.+\.php)(/.+)$;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param   REMOTE_USER $auth_user;
    #fastcgi_param   HTTP_REMOTE_USER $auth_user;
     }

# Any request to this server will first be sent to this URL
auth_request /vouch-validate;


location = /vouch-validate {
  # This address is where Vouch will be listening on
  proxy_pass http://127.0.0.1:9090/validate;

  proxy_set_header Content-Length "";
  proxy_set_header Host $http_host;
  proxy_set_header Remote-User $auth_user;
  proxy_pass_request_body off; # no need to send the POST body
  proxy_set_header X-Real-IP $remote_addr;
  proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
  proxy_set_header X-Forwarded-Proto $scheme;

  # these return values are passed to the @error401 call
  auth_request_set $auth_resp_jwt $upstream_http_x_vouch_jwt;
  auth_request_set $auth_resp_err $upstream_http_x_vouch_err;
  auth_request_set $auth_resp_failcount $upstream_http_x_vouch_failcount;
}


auth_request_set $auth_user $upstream_http_x_vouch_user;

location = /logout {
        return 302 https://vouch.domain.com/logout?url=$scheme://$http_host;
}

error_page 401 = @error401;

# If the user is not logged in, redirect them to Vouch's login URL
location @error401 {
  return 302 https://vouch.domain.com/login?url=https://$http_host$request_uri&vouch-failcount=$auth_resp_failcount&X-Vouch-Token=$auth_resp_jwt&error=$auth_resp_err;
}

}

```

Eventually you will need to secure your site with SSL/TLS that makes your site use *https://*. More than ever, your traffic should be secure with using it as 0auth as the method used to sign in to your protected website(s).

Do this after you have the server blocks working in the following section.

Here is the link for Certbot for Debian. I have tested this on Debian 10 & 11.
[https://certbot.eff.org/instructions?ws=nginx&os=debianbuster](Link for Certbot)

Certbot can do this for you as long as you have the subdomain in your DNS pointing to your machine and have cert bot installed. It'll add these blocks in your server blocks automatically.

It'll look similar to this . Certbot will rearrange and add a few things.

``` nginx
server { 

   server_name vouch.example.com  # or the domain of protected site will be in place of *vouch.example.com* by certbot

                                .  .  .  .  .  .  .  .  .  .  .  .  .  .  .  .  .  .  .  .  .  .  .  .  . 

    listen [::]:443 ssl; # managed by Certbot
    listen 443 ssl; # managed by Certbot
    ssl_certificate /etc/letsencrypt/live/vouch.example.com/fullchain.pem; # managed by Certbot
    ssl_certificate_key /etc/letsencrypt/live/vouch.example.com/privkey.pem; # managed by Certbot
    include /etc/letsencrypt/options-ssl-nginx.conf; # managed by Certbot
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem; # managed by Certbot


}
server {
    if ($host = vouch.domain.com) {
        return 301 https://$host$request_uri;
    } # managed by Certbot


        listen 80;
        listen [::]:80;

        server_name secretapp.example.com;
    return 404; # managed by Certbot


}
```
Repeat for every server block you'd like protect.

Let's check for errors in nginx. Type the following command.

``` bash
sudo nginx -t

```

You should see something similar to this:

``` bash
nginx: the configuration file /etc/nginx/nginx.conf syntax is ok
nginx: configuration file /etc/nginx/nginx.conf test is successful
```
Then restart nginx to push changes.

``` bash
sudo systemctl restart nginx

```

## Let's open a browser tab or window!
Note: I'm using Firefox. (Preference). Any modern browser should work.

Depending on your setup, you'll see a login screen or a website without login in
<div class="mw9 center ph3-ns outline" markdown="1">

**Type in the protected app' URL in the address bar**

[image address-bar.png]
Sign in with your domain (indieauth).
<div class="flex flex-wrap">
  <div markdown="1" class=" fl w-50 pa2">
[image indieauth.png]
  </div>
  <div markdown="1" class=" fl w-50 pa2">
Trigger a login later, if needed.
  [image bludit1.png]
  </div>
</div>

OR

### Type in the protected app' URL in the address bar

**2 - Trigger Login Later**

[image address-bar.png]
Trigger vouch proxy (indieauth).
<div class="flex flex-wrap">
  <div markdown="1" class="fl w-50 pa2">
  [image bludit1.png]
  </div>
  <div markdown="1" class="fl w-50 pa2">
 [image indieauth.png]
  </div>
</div>
</div>

### Voila, the protected page.
Here is the home page of a Bludit CMS on subdomain acting as "secretapp.example.com"

I will write a blog post on using it on my website and my RSS feeder. I will also go in deeper about the cookie as well. 


That's it! You have setup indieauth to protect (or not your pages 

## Want to see who's logged in?

Whether you just want to see the user authenticated via the http_header or use http_header to help you authenticate into the website we can use a simple php script. We added already the other parts but here's an overview.

In your main server block, just below the line `auth_request /vouch-validate;` which enables the auth_request module, we added the following:

``` nginx
auth_request_set $auth_user $upstream_http_x_vouch_user;
```

This will take the HTTP header that Vouch sets, `X-Vouch-User`, and assign it to the nginx variable `$auth_user`. Then, depending on whether you use fastcgi or proxy_pass, include one of the two lines below in your server block:



``` nginx
fastcgi_param REMOTE_USER $auth_user;
proxy_set_header Remote-User $auth_user;
```

These will set an HTTP header with the value of `$auth_user` that your backend server can read in order to know who logged in. For example, in PHP you can access this data using:

``` php
<?php
echo 'Hello, ' . $_SERVER['REMOTE_USER'] . '!';
?>
```




  </article">
<style>

.gif {

display: flex;
flex-direction: row;
justify-content: space-around;
flex-wrap: wrap;



}

.gif img {
max-width: 70%;
width: 100%;
height: auto;

}



.item {
  flex: 4;
display: flex;
padding: width: 10%;


}

.pabs-banner img {
width: 100%;
height: auto;
}

@media only screen and (max-width: 600px) {
  .t-of-c {
    width: inherit;
  }
}


</style>


