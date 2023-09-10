server {
index index.php index.html yellow.php;
  server_name korea.lifeofpablo.com; # Adjust to your domain setup
  root /var/www/korea/html; # Adjust to your setup


    location /content {
        rewrite ^(.*)$ /error break;
    }

    location /system {
        rewrite ^(.*)$ /error break;
    }

    location / {
        if (!-e $request_filename) {
            rewrite ^/(.*)$ /yellow.php last;
            break;
        }
    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index yellow.php;
        include fastcgi.conf;
    }
	
   }




    listen 443 ssl; # managed by Certbot
    ssl_certificate /etc/letsencrypt/live/korea.lifeofpablo.com/fullchain.pem; # managed by Certbot
    ssl_certificate_key /etc/letsencrypt/live/korea.lifeofpablo.com/privkey.pem; # managed by Certbot
    include /etc/letsencrypt/options-ssl-nginx.conf; # managed by Certbot
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem; # managed by Certbot

}
server {
    if ($host = korea.lifeofpablo.com) {
        return 301 https://$host$request_uri;
    } # managed by Certbot


  server_name korea.lifeofpablo.com;
    listen 80;
    return 404; # managed by Certbot


}
