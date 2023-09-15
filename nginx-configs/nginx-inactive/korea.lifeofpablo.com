server
{
	index index.php index.html yellow.php;
	server_name korea.lifeofpablo.com; # Adjust to your domain setup
	root /var/www/korea/html; # Adjust to your setup


	location /content
	{
		rewrite ^(.*)$ /error break;
	}

	location /system
	{
		rewrite ^(.*)$ /error break;
	}

	location /
	{
		if (!-e $request_filename)
		{
			rewrite ^/(.*)$ /yellow.php last;
			break;
		}
		location ~ \.php$
		{
			fastcgi_split_path_info ^(.+\.php)(/.+)$;
			fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
			fastcgi_index yellow.php;
			include fastcgi.conf;
		}
		location /complaint-form
		{
			rewrite ^/complaint-form(.*)$ https://www.youtube.com/watch?v=dQw4w9WgXcQ/$1 redirect;
		}
	}
}

