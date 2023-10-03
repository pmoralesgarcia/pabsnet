server {

        server_name climateweeknyc.lifeofpablo.com;

        location / {
                proxy_pass http://127.0.0.1:8203/;
                # be sure to pass the original host header
                proxy_set_header Host $http_host;
                proxy_set_header X-Real-IP $remote_addr;
        }


    listen [::]:443 ssl ipv6only=on; # managed by Certbot
    listen 443 ssl; # managed by Certbot
    ssl_certificate /etc/letsencrypt/live/climateweeknyc.lifeofpablo.com/fullchain.pem; # managed by Certbot
    ssl_certificate_key /etc/letsencrypt/live/climateweeknyc.lifeofpablo.com/privkey.pem; # managed by Certbot
    include /etc/letsencrypt/options-ssl-nginx.conf; # managed by Certbot
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem; # managed by Certbot

}
server {
    if ($host = climateweeknyc.lifeofpablo.com) {
        return 301 https://$host$request_uri;
    } # managed by Certbot


        listen 80;

        server_name climateweeknyc.lifeofpablo.com;
    return 404; # managed by Certbot


}
