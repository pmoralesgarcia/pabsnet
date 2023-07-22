server {


        server_name copy.lifeofpablo.com;

  location / {
    proxy_set_header  Host $http_host;
    proxy_set_header  X-Forwarded-Proto https;
    proxy_pass        http://127.0.0.1:8080;
  }

    location /validate {
      proxy_pass http://indieauth.lifeofpablo.com; # must not! have a slash at the end
  proxy_set_header Content-Length "";
  proxy_set_header Host $http_host;
  proxy_pass_request_body off; # no need to send the POST body
  proxy_set_header X-Real-IP $remote_addr;
  proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
  proxy_set_header X-Forwarded-Proto $scheme;

      # these return values are used by the @error401 call
      auth_request_set $auth_resp_jwt $upstream_http_x_vouch_jwt;
      auth_request_set $auth_resp_err $upstream_http_x_vouch_err;
      auth_request_set $auth_resp_failcount $upstream_http_x_vouch_failcount;
    }

    # if /validate returns `401 not authorized` then forward the request to the error401block
    error_page 401 = @error401;

    location @error401 {
        # redirect to Vouch Proxy for login
        return 302 https://indieauth.lifeofpablo.com/login?url=$scheme://$http_host$request_uri&vouch-failcount=$auth_resp_failcount&X-Vouch-Token=$auth_resp_jwt&error=$auth_resp_err;
    }



}


