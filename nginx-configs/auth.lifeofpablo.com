server
{
        server_name auth.lifeofpablo.com;

        location /
        {
                proxy_pass http://127.0.0.1:9092;
                # be sure to pass the original host header
                proxy_set_header Host $http_host;
        }



    listen 443 ssl; # managed by Certbot
    ssl_certificate /etc/letsencrypt/live/auth.lifeofpablo.com/fullchain.pem; # managed by Certbot
    ssl_certificate_key /etc/letsencrypt/live/auth.lifeofpablo.com/privkey.pem; # managed by Certbot
    include /etc/letsencrypt/options-ssl-nginx.conf; # managed by Certbot
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem; # managed by Certbot

}
server
{
    if ($host = auth.lifeofpablo.com) {
        return 301 https://$host$request_uri;
    } # managed by Certbot


        server_name auth.lifeofpablo.com;
    listen 80;
    return 404; # managed by Certbot


}
