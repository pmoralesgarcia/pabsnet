server {
	server_name reader.lifeofpablo.com;

	location / {
		proxy_pass http://127.0.0.1:8085;
		# be sure to pass the original host header
		proxy_set_header Host $http_host;
	}


    listen 443 ssl; # managed by Certbot


    ssl_certificate /etc/letsencrypt/live/reader.lifeofpablo.com/fullchain.pem; # managed by Certbot
    ssl_certificate_key /etc/letsencrypt/live/reader.lifeofpablo.com/privkey.pem; # managed by Certbot
}
server {
    if ($host = reader.lifeofpablo.com) {
        return 301 https://$host$request_uri;
    } # managed by Certbot


	listen 80;
	server_name reader.lifeofpablo.com;
    return 404; # managed by Certbot


}
