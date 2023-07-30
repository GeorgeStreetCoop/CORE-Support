#!/bin/bash
# run this script under sudo!


# set up PHP php.ini
sed -i 's/^short_open_tag = Off/short_open_tag = On/' /etc/php*/*/php.ini /etc/php/*/*/php.ini 2&>/dev/null


# back up PHP FPM configs, then increase pool size to prevent "server reached pm.max_children setting" error
find /etc/php/*/fpm -name www.conf -exec sh -c 'cp -n $0 $0~ ; sed -i "s/^\s*#*\s*pm.max_children\s*=\s*5\s*#*/pm.max_children\t= 25 ; CORE-Support setup_php.sh/" $0' {} \;


# restart nginx and PHP FPM
systemctl restart php*-fpm.service nginx.service
