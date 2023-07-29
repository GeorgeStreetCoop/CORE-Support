#~/bin/sh
# run this script under sudo!

# set up variables
if [ -z "$SUPPORT" ]; then
	SUPPORT=/CORE-Support
fi
if [ -z "$COREPOS" ]; then
	COREPOS=/CORE-POS
fi


apt-get install nginx


# enable PHP FPM (FastCGI Process Manager)
cmp -s "$SUPPORT/template.sites-available_default" /etc/nginx/sites-available/default || cp /etc/nginx/sites-available/default /etc/nginx/sites-available/default~
cp "$SUPPORT/template.sites-available_default" /etc/nginx/sites-available/default


# enable $_SERVER['PHP_SELF']
cmp -s "$SUPPORT/template.fastcgi_params" /etc/nginx/fastcgi_params || cp /etc/nginx/fastcgi_params /etc/nginx/fastcgi_params~
cp "$SUPPORT/template.fastcgi_params" /etc/nginx/fastcgi_params


# test nginx configuration
echo 'Testing nginx configuration:'
nginx -t


# let nginx through the firewall
ufw allow 'Nginx HTTP'


# restart nginx and PHP FPM
systemctl restart php*-fpm.service nginx.service


# set up lane URLs
ln -svf "$COREPOS/pos/is4c-nf/" "/var/www/lane" # Apache2, Ubuntu before 13.10
ln -svf "$COREPOS/pos/is4c-nf/" "/var/www/html/lane" # Apache2, Ubuntu 13.10 and later
ln -svf "$COREPOS/pos/is4c-nf/" "/usr/share/nginx/www/lane" # nginx, earlier
ln -svf "$COREPOS/pos/is4c-nf/" "/usr/share/nginx/html/lane" # nginx, later
