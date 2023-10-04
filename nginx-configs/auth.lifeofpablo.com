server
{
	server_name auth.lifeofpablo.com;

	location /
	{
		proxy_pass http://127.0.0.1:9092;
		# be sure to pass the original host header
		proxy_set_header Host $http_host;
	}

}
