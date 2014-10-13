#!/bin/sh
# run this script under sudo!

# set up variables
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

# get latest POS directory
rm -rf "$COREPOS"
mkdir "$COREPOS"
cd "$COREPOS"
git clone https://github.com/CORE-POS/IS4C.git "$COREPOS"
chown -Rf coop "$COREPOS"

# set up our ini files:
# ini.php is linked, so changes reflect back up to the shared codebase
ln -svf "$SUPPORT/ini.php" "$COREPOS/pos/is4c-nf/ini.php"
chown www-data "$SUPPORT/ini.php" "$COREPOS/pos/is4c-nf/ini.php"
# ini-local.php is linked to a copy, so local changes don't automatically share
cp "$SUPPORT/ini-local.blank.php" "$SUPPORT/ini-local.php"
ln -svf "$SUPPORT/ini-local.php" "$COREPOS/pos/is4c-nf/ini-local.php"
chown www-data "$SUPPORT/ini-local.php" "$COREPOS/pos/is4c-nf/ini-local.php"

# set up error logs
touch "$COREPOS/pos/is4c-nf/log/php-errors.log" "$COREPOS/pos/is4c-nf/log/queries.log"
chown www-data "$COREPOS/pos/is4c-nf/log/php-errors.log" "$COREPOS/pos/is4c-nf/log/queries.log"


# set up grub boot process
sed -i '/^GRUB_CMDLINE_LINUX=.*quiet\|splash.*/s/^GRUB_CMDLINE_LINUX=\(.*\)\(quiet\|splash\)\(.*\)\(quiet\|splash\)\(.*\)$/# was &\nGRUB_CMDLINE_LINUX=\1\3\5/' /etc/default/grub
sed -i 's/.*GRUB_INIT_TUNE=.*/GRUB_INIT_TUNE="480 440 1 660 1 880 1 660 1 440 3"/' /etc/default/grub
update-grub


# set up static IP
sed "s/###LANEIP###/${LANEIP}/g" "$SUPPORT/template.interfaces" > /etc/network/interfaces

# set up fannie and lane hosts
sed -i '/^192.168.1.50\b/d' /etc/hosts
sed -i '/^192.168.1.51\b/d' /etc/hosts
sed -i '/^192.168.1.52\b/d' /etc/hosts
sed -i '/^192.168.1.53\b/d' /etc/hosts
sed -i '/\bfannie\b/d' /etc/hosts
sed -i '/\blane1\b/d' /etc/hosts
sed -i '/\blane2\b/d' /etc/hosts
sed -i '/\blane3\b/d' /etc/hosts
sed -i '$a 192.168.1.50    fannie' /etc/hosts
sed -i '$a 192.168.1.51    lane1' /etc/hosts
sed -i '$a 192.168.1.52    lane2' /etc/hosts
sed -i '$a 192.168.1.53    lane3' /etc/hosts


# set up webserver
rm -f "/var/www/html/POS"
ln -svf "$COREPOS/pos/is4c-nf" "/var/www/html/POS"


# set up mysql for network use
sed -i "/bind-address/s/\(= *\).*\$/\1${LANEIP}/" /etc/mysql/my.cnf
sed -i '/skip-networking/s/^\( *skip-networking\)/# \1/' /etc/mysql/my.cnf


# set up browser (runs as user "coop")
su coop
xdg-settings set default-web-browser firefox.desktop
sed -i '/"browser.startup.homepage"/d' ~coop/.mozilla/firefox/*/prefs.js
sed -i '$a user_pref("browser.startup.homepage", "http://localhost/POS/install/index.php");' ~coop/.mozilla/firefox/*/prefs.js

# set up bash aliases
touch ~coop/.bashrc
sed -i '/alias firefox=/d;/alias geany=/d;/alias smartgit=/d' ~coop/.bashrc
sed -i '$a alias firefox="nohup firefox >/dev/null 2>&1 &"' ~coop/.bashrc
sed -i '$a alias geany="nohup geany >/dev/null 2>&1 &"' ~coop/.bashrc
sed -i '$a alias smartgit="nohup smartgithg >/dev/null 2>&1 &"' ~coop/.bashrc

# set up openbox autolaunch
mkdir -p ~coop/.config/openbox
touch ~coop/.config/openbox/autostart
sed -i '/xterm/d;/firefox/d;/geany/d;/smartgit/d' ~coop/.config/openbox/autostart
sed -i '$a xterm >/dev/null 2>&1 &' ~coop/.config/openbox/autostart
sed -i '$a firefox >/dev/null 2>&1 &' ~coop/.config/openbox/autostart
sed -i '$a geany >/dev/null 2>&1 &' ~coop/.config/openbox/autostart
sed -i '$a smartgithg >/dev/null 2>&1 &' ~coop/.config/openbox/autostart
