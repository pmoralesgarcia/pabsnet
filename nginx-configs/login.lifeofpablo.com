server
{
	server_name login.lifeofpablo.com;

	location /
	{
		proxy_pass http://127.0.0.1:8081;
		# be sure to pass the original host header
		proxy_set_header Host $http_host;
	}

}
