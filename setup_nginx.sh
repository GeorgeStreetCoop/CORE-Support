#~/bin/sh
# run this script under sudo!

# set up variables
if [ -z "$SUPPORT" ]; then
	SUPPORT=/CORE-Support
fi
if [ -z "$COREPOS" ]; then
	COREPOS=/CORE-POS
fi


# set up lane URL
ln -svf "$COREPOS/pos/is4c-nf/" "/var/www/html/lane"


apt-get install nginx


# enable PHP 7.0 FPM (FastCGI Process Manager)
cmp -s "$SUPPORT/template.sites-available_default" /etc/nginx/sites-available/default || cp /etc/nginx/sites-available/default /etc/nginx/sites-available/default~
cp "$SUPPORT/template.sites-available_default" /etc/nginx/sites-available/default


# enable $_SERVER['PHP_SELF']
cmp -s "$SUPPORT/template.fastcgi_params" /etc/nginx/fastcgi_params || cp /etc/nginx/fastcgi_params /etc/nginx/fastcgi_params~
cp "$SUPPORT/template.fastcgi_params" /etc/nginx/fastcgi_params


# test nginx configuration
echo 'Testing nginx configuration:'
nginx -t


# restart nginx and PHP 7.0 FPM
service nginx restart
service php7.0-fpm restart
