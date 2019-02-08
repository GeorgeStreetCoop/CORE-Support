#~/bin/sh
# run this script under sudo!


apt-get install ufw

ufw allow ssh
ufw allow from 192.168.1.71 to any port 80
ufw allow from 192.168.1.50 to any port 3306

service ufw restart

ufw --force enable
