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


# in /etc/php/7.0/fpm/php.ini:

# cgi.fix_pathinfo=0
sed -i 's/^[[:space:]]*\;\?[[:space:]]*cgi\.fix_pathinfo=[01]/cgi\.fix_pathinfo=0/' /etc/php/7.0/fpm/php.ini


# in /etc/nginx/sites-available/default:

# change root to /var/www/html;
sed -i 's/^\([[:space:]]*root[[:space:]]\+\).\+\;/\1\/var\/www\/html\;/' /etc/nginx/sites-available/default

# allow read of index.php
sed -i 's/^\([[:space:]]*index[[:space:]]\+\)index.html index.htm/\1index.php index.html index.htm/' /etc/nginx/sites-available/default

# give us a name
sed -i 's/^\([[:space:]]*server_name[[:space:]]\+\)_\;/\1localhost\;/' /etc/nginx/sites-available/default

# add disable_symlinks off;

# uncomment location ~ \.php$ section
sed -i 's/^\([[:space:]]*\)#\([[:space:]]*\)location ~ \\\.php\$/\1\2location ~ \\\.php\$/' /etc/nginx/sites-available/default
sed -i 's/^\([[:space:]]*\)#\([[:space:]]*\)include snippets\/fastcgi-php.conf\;/\1\2include snippets\/fastcgi-php.conf\;/' /etc/nginx/sites-available/default
sed -i 's/^\([[:space:]]*\)#\([[:space:]]*\)fastcgi_pass unix\:\/run\/php\/php7\.0-fpm\.sock\;/\1\2fastcgi_pass unix\:\/run\/php\/php7\.0-fpm\.sock\;/' /etc/nginx/sites-available/default
#
echo 'TODO: uncomment closing bracket on "location ~ \.php$" section of /etc/nginx/sites-available/default'

# deny access to .htaccess files
echo 'TODO: uncomment "location ~ /\.ht" section of /etc/nginx/sites-available/default'

echo 'RUN THIS: sudo nano /etc/nginx/sites-available/default'
echo

# test nginx configuration
echo 'Testing nginx configuration:'
nginx -t
