#!/bin/sh

# use PHP 7.x if at all possible
if [ -x /usr/bin/php7.2 ]; then
	alias php=/usr/bin/php7.2
elif [ -x /usr/bin/php7.1 ]; then
	alias php=//usr/bin/php7.1
elif [ -x /usr/bin/php7.0 ]; then
	alias php=/usr/bin/php7.0
else
	echo "PHP 7.x couldn't be found in /usr/bin! This may not work..."
fi
alias php
php -v | head -1
echo

cd /CORE-Support && . ./update_office_env.sh && php update_office.php xfer_sales start_date=`date --date=-1\ day +\%Y-\%m-\%d`
