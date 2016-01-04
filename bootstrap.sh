#!/bin/bash
# run this script under sudo!

# set up variables
PREVIOUSDIR=`pwd`
COREPOS=/CORE-POS
SUPPORT=/CORE-Support

# determine lane number and IP address
LANEID=`hostname`
LANENUMBER=`echo ${LANEID}|sed -n 's/^lane\([0-9]*\)$/\1/p'`
if [ -z "$LANENUMBER" ]; then
	echo "Host '${LANEID}' does not appear to be a POS lane. Aborting lane install." >&2
	exit 2
fi
LANEIP="192.168.1.$((${LANENUMBER}+50))"
echo "Setting up POS lane #${LANENUMBER} to use IP address ${LANEIP}..."

# get password for mysql 'lane' user
while [ -z "$LANEPASSWORD" ]; do
	read -s -p "What is the password for the mysql 'lane' user? " LANEPASSWORD
done


# install LAMP stack
tasksel install lamp-server


# bootstrap git
apt-get install git

# get latest CORE-Support directory; if user specified "rm" argument, deletes old one
if [ -n "$1" -a "$1" = "rm" ]; then
	rm -rf "$SUPPORT"
	mkdir -p "$SUPPORT" 2>/dev/null
	cd "$SUPPORT"
	git clone https://github.com/GeorgeStreetCoop/CORE-Support.git "$SUPPORT"
else
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
	git clone https://github.com/CORE-POS/IS4C.git --branch version-1.9 "$COREPOS"
else
	cd "$COREPOS"
	git reset --hard HEAD
	git pull
fi
chown -Rf cashier "$COREPOS"

# set up our ini files:
# ini.php is linked, so changes reflect back up to the shared codebase
ln -svf "$SUPPORT/ini.php" "$COREPOS/pos/is4c-nf/ini.php"
chown www-data "$SUPPORT/ini.php" "$COREPOS/pos/is4c-nf/ini.php"
# ini-local.php is linked to a copy, so local changes don't automatically share
sed "s/###LANENUMBER###/${LANENUMBER}/g;s/###LANEPASSWORD###/${LANEPASSWORD}/g" "$SUPPORT/template.ini-local.php" > "$SUPPORT/ini-local.php"
ln -svf "$SUPPORT/ini-local.php" "$COREPOS/pos/is4c-nf/ini-local.php"
chown www-data "$SUPPORT/ini-local.php" "$COREPOS/pos/is4c-nf/ini-local.php"
# ini.json is simply created empty; for the moment, this file doesn't sync
touch "$COREPOS/pos/is4c-nf/ini.json"
chown www-data "$COREPOS/pos/is4c-nf/ini.json"

# set up error logs
touch "$COREPOS/pos/is4c-nf/log/php-errors.log" "$COREPOS/pos/is4c-nf/log/queries.log"
chown www-data "$COREPOS/pos/is4c-nf/log/php-errors.log" "$COREPOS/pos/is4c-nf/log/queries.log"


# set up grub boot process
. "$SUPPORT/setup_grub.sh"


# set up network
. "$SUPPORT/setup_network.sh"


# set up lane URL
ln -svf "$COREPOS/pos/is4c-nf" "/var/www/lane"


# set up mysql users and basic data
echo 'When prompted below, please enter your mysql ROOT password...'
mysql -u root -p --force < "$SUPPORT/setup_db.sql"


# set up ssd, including boot process
. "$SUPPORT/setup_serial.sh"


# set up user "cashier" (runs as that user ID)
su -c "$SUPPORT/setup_user.sh" - cashier


# set background image to Co-op logo
ln -svf "$SUPPORT/GeorgeStreetCoopLogo_670x510.gif" "$COREPOS/pos/is4c-nf/graphics/is4c.gif"
chown www-data "$SUPPORT/GeorgeStreetCoopLogo_670x510.gif" "$COREPOS/pos/is4c-nf/graphics/is4c.gif"


# cleanup environment
unset LANEPASSWORD
cd "$PREVIOUSDIR"
