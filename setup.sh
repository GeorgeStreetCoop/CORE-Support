#!/bin/bash
# run this script under sudo!


# set up context
if [ -z "$WD_BEFORE_COREPOS_SETUP" ]; then
	WD_BEFORE_COREPOS_SETUP=`pwd`
fi
if [ -z "$SUPPORT" ]; then
	SUPPORT=/CORE-Support
fi
if [ -z "$COREPOS" ]; then
	COREPOS=/CORE-POS
fi


# check that we're a lane
LANEID=`hostname`
if [ -z "$LANENUMBER" ]; then
	LANENUMBER=`echo ${LANEID}|sed -n 's/^lane\([0-9]*\)$/\1/p'`
fi
if [ -z "$LANENUMBER" ]; then
	read -p "Host '${LANEID}' does not appear to be a POS lane. Install backend only? [y/N] " -n 1 -r
	echo
	if [[ ! $REPLY =~ ^[Yy]$ ]]; then
		echo "Aborting lane install." >&2
		return
	fi
	LANENUMBER=0
fi


# get password for mysql 'lane' user
while [ -z "$LANEPASSWORD" ]; do
	read -s -p "What is the password for the mysql 'lane' user? " LANEPASSWORD
done


# install LAMP stack
tasksel install lamp-server


# bootstrap git
apt-get -y install git

# get latest CORE-Support directory; if user specified "rm" argument, deletes old one
if [ -n "$1" -a "$1" = "rm" ]; then
	rm -rf "$SUPPORT"
	mkdir -p "$SUPPORT" 2>/dev/null
	cd "$SUPPORT"
	git clone https://github.com/GeorgeStreetCoop/CORE-Support.git "$SUPPORT"
else
	if [ ! -d "$SUPPORT" ]; then
		echo "Directory '$SUPPORT' doesn't exist. Aborting lane install. Try again with 'rm' override parameter?" >&2
		return
	fi
	cd "$SUPPORT"
	git reset --hard HEAD
	git pull
fi
chown -Rf cashier "$SUPPORT"

# install needed packages
. "$SUPPORT/setup_packages.sh"


# get latest CORE-POS directory; if user specified "rm" argument, deletes old one
if [ -n "$1" -a "$1" = "rm" ]; then
	rm -rf "$COREPOS"
	mkdir -p "$COREPOS" 2>/dev/null
	cd "$COREPOS"
	git clone https://github.com/GeorgeStreetCoop/CORE-POS.git "$COREPOS"
else
	if [ ! -d "$COREPOS" ]; then
		echo "Directory '$COREPOS' doesn't exist. Aborting lane install. Try again with 'rm' override parameter?" >&2
		return
	fi
	cd "$COREPOS"
	git reset --hard HEAD
	git pull
fi
chown -Rf cashier "$COREPOS"

# set up our ini files:
# ini.php is linked to a copy, so local changes don't automatically share
if [ "$LANENUMBER" -gt 0 ]; then
	sed "s/###LANENUMBER###/${LANENUMBER}/g;s/###LANEPASSWORD###/${LANEPASSWORD}/g" "$SUPPORT/template.ini.php" > "$SUPPORT/ini.php"
	ln -svf "$SUPPORT/ini.php" "$COREPOS/pos/is4c-nf/ini.php"
	chown www-data "$SUPPORT/ini.php" "$COREPOS/pos/is4c-nf/ini.php"
	# ini.json is simply created empty; for the moment, this file doesn't sync
	touch "$COREPOS/pos/is4c-nf/ini.json"
	chown www-data "$COREPOS/pos/is4c-nf/ini.json"
fi

# set up error logs
touch "$COREPOS/pos/is4c-nf/log/php-errors.log" "$COREPOS/pos/is4c-nf/log/queries.log"
chown www-data "$COREPOS/pos/is4c-nf/log/php-errors.log" "$COREPOS/pos/is4c-nf/log/queries.log"
ln -svf "$COREPOS/pos/is4c-nf/log/php-errors.log" "$COREPOS/pos/is4c-nf/log/queries.log" "$SUPPORT"
ln -svf /var/log/apache2/access.log "$SUPPORT/apache_access.log"
ln -svf /var/log/apache2/error.log "$SUPPORT/apache_error.log"


# set up grub boot process
. "$SUPPORT/setup_grub.sh"


# set up network
. "$SUPPORT/setup_network.sh"

# set up PHP
. "$SUPPORT/setup_php.sh"


# set up lane URL
ln -svf "$COREPOS/pos/is4c-nf/" "/var/www/lane"
# prevent recursive link
find "$COREPOS/pos/is4c-nf/" -maxdepth 1 -name is4c-nf -type l -delete


# set up mysql users and basic data
if [ "$LANENUMBER" -gt 0 ]; then
	echo 'When prompted below, please enter your mysql ROOT password...'
	mysql -u root -p --force < "$SUPPORT/setup_db.sql"
fi


# set up ssd, including boot process
. "$SUPPORT/setup_serial.sh"


# set up xwindows, including boot process
. "$SUPPORT/setup_xwindows.sh"


# set up user "cashier" (runs as that user ID)
su -c "$SUPPORT/setup_user.sh" - cashier


# set up George Street receipt formatting
. "$SUPPORT/setup_receipt.sh"


# set background image to Co-op logo
if [ "$LANENUMBER" -gt 0 ]; then
	ln -svf "$SUPPORT/GeorgeStreetCoopLogo_670x510.gif" "$COREPOS/pos/is4c-nf/graphics/is4c.gif"
	chown www-data "$SUPPORT/GeorgeStreetCoopLogo_670x510.gif" "$COREPOS/pos/is4c-nf/graphics/is4c.gif"
fi


# cleanup environment
unset LANEPASSWORD
if [ -n "$WD_BEFORE_COREPOS_SETUP" ]; then
	cd "$WD_BEFORE_COREPOS_SETUP"
fi
