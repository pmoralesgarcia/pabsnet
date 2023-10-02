server {
        listen 80 http2;
        listen [::]:80 http2;

server_name korea.lifeofpablo.com;

location / {
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_pass http://127.0.0.1:8096/;
}
