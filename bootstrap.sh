#!/bin/sh
# run this script under sudo!

# set up variables
COREPOS=/CORE-POS
SUPPORT=/CORE-Support
LANEIDFILE=$SUPPORT/laneid.txt

# bootstrap git
apt-get install git

# get latest Support directory
rm -rf "$SUPPORT"
mkdir "$SUPPORT"
cd "$SUPPORT"
git clone https://github.com/GeorgeStreetCoop/CORE-Support.git "$SUPPORT"
chown -Rf coop "$SUPPORT"

# install needed packages
. ./apt-updates.sh

# get/set lane ID
if [ -f "$LANEIDFILE" ]; then
	LANEID=`cat "$LANEIDFILE"`
fi
if [ -z "$LANEID" ]; then
	while [ -z "$LANEID" ]; do
		read -p "What is the name of this POS lane? (lane1, lane2, etc) " LANEID
	done
	echo -n "$LANEID" > "$LANEIDFILE"
fi
echo "Lane ID is $LANEID, stored at $LANEIDFILE"
ls -l "$LANEIDFILE"

# get latest POS directory
rm -rf "$COREPOS"
mkdir "$COREPOS"
cd "$COREPOS"
git clone https://github.com/CORE-POS/IS4C.git "$COREPOS"
chown -Rf coop "$COREPOS"

# set up our ini files
ln -svf "$SUPPORT/ini.php" "$COREPOS/pos/is4c-nf/ini.php"
ln -svf "$SUPPORT/ini-local.php" "$COREPOS/pos/is4c-nf/ini-local.php"
chown www-data "$SUPPORT/ini.php" "$SUPPORT/ini-local.php"

# set up error logs
touch "$COREPOS/pos/is4c-nf/log/php-errors.log" "$COREPOS/pos/is4c-nf/log/queries.log"
chown www-data "$COREPOS/pos/is4c-nf/log/php-errors.log" "$COREPOS/pos/is4c-nf/log/queries.log"


# set up webserver
ln -svf "$COREPOS/pos/is4c-nf/" "/var/www/html/POS"


# set up browser (runs as user "coop")
su coop
xdg-settings set default-web-browser firefox.desktop
sed -i 's/"browser.startup.homepage"//' ~coop/.mozilla/firefox/*/prefs.js
sed -i '$a user_pref("browser.startup.homepage", "http://localhost/POS/install/index.php");' ~coop/.mozilla/firefox/*/prefs.js
