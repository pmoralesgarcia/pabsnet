proxy_cache_path   /tmp/ levels=1:2 keys_zone=s3_cache:10m max_size=500m inactive=60m use_temp_path=off;

server {
  listen 80;
  listen 443 ssl;
  server_name  static.lifeofpablo.com;
  access_log  /var/log/nginx/static.lifeofpablo.access.log  combined;
  error_log   /var/log/nginx/static.lifeofpablo.error.log;
  set $bucket "lifeofpablo.s3.us-east-005.backblazeb2.com";
  sendfile on;

# This configuration uses a 60 minute cache for files requested:
  location ^~ /cached/ {
    rewrite           /cached(.*) $1 break;
    resolver 1.1.1.1;
    proxy_cache            s3_cache;
    proxy_http_version     1.1;
    proxy_redirect off;
    proxy_set_header       Connection "";
    proxy_set_header       Authorization '';
    proxy_set_header       Host $bucket;
    proxy_set_header       X-Real-IP $remote_addr;
    proxy_set_header       X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_hide_header      x-amz-id-2;
    proxy_hide_header      x-amz-request-id;
    proxy_hide_header      x-amz-meta-server-side-encryption;
    proxy_hide_header      x-amz-server-side-encryption;
    proxy_hide_header      Set-Cookie;
    proxy_ignore_headers   Set-Cookie;
    proxy_cache_revalidate on;
    proxy_intercept_errors on;
    proxy_cache_use_stale  error timeout updating http_500 http_502 http_503 http_504;
    proxy_cache_lock       on;
    proxy_cache_valid      200 304 60m;
    add_header             Cache-Control max-age=31536000;
    add_header             X-Cache-Status $upstream_cache_status;
    proxy_pass             https://$bucket; # without trailing slash
  }

# This configuration provides direct access to the Object Storage bucket:

location / {
    resolver 1.1.1.1;
    proxy_http_version     1.1;
    proxy_redirect off;
    proxy_set_header       Connection "";
    proxy_set_header       Authorization '';
    proxy_set_header       Host $bucket;
    proxy_set_header       X-Real-IP $remote_addr;
    proxy_set_header       X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_hide_header      x-amz-id-2;
    proxy_hide_header      x-amz-request-id;
    proxy_hide_header      x-amz-meta-server-side-encryption;
    proxy_hide_header      x-amz-server-side-encryption;
    proxy_hide_header      Set-Cookie;
    proxy_ignore_headers   Set-Cookie;
    proxy_intercept_errors on;
    add_header             Cache-Control max-age=31536000;
    proxy_pass             https://$bucket; # without trailing slash
  }
}
