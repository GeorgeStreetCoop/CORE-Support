#!/bin/bash
# run this script under sudo!
# Ref https://www.digitalocean.com/community/tutorials/how-to-install-and-secure-phpmyadmin-with-nginx-on-an-ubuntu-14-04-server


apt-get install phpmyadmin


# set up phpmyadmin URL
ln -svf /usr/share/phpmyadmin "/var/www/html/phpmyadmin"


# enable mcrypt
phpenmod mcrypt


# restart nginx and PHP FPM
systemctl restart php*-fpm.service nginx.service
