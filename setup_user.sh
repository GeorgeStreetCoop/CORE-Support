#~/bin/sh
# run this script as regular user!

# exit if we're creating a backend-only setup
if [ "$LANENUMBER" == 0 ]; then
	echo "This is not a POS lane; skipping user setup."
	return
fi

# set up variables
if [ -z "$SUPPORT" ]; then
	SUPPORT=/CORE-Support
fi

# set up bash aliases
touch ~/.bashrc
sed -i '/alias firefox=/d;/alias geany=/d;/alias smartgit=/d;/alias r=/d;/alias k=/d;/alias kk=/d;/alias c=/d;/alias nv1=/d;/alias log_cl=/d;/alias log_pr=/d;/S ()/d;' ~/.bashrc
echo 'alias firefox="nohup firefox >/dev/null 2>&1 &"' >> ~/.bashrc
echo 'alias geany="nohup geany >/dev/null 2>&1 &"' >> ~/.bashrc
echo 'alias smartgit="nohup smartgithg >/dev/null 2>&1 &"' >> ~/.bashrc
echo "alias r=\"date +'%b %d %l:%M:%S'; $SUPPORT/reset_live.sh\"" >> ~/.bashrc
echo 'alias k="date +\"%b %d %l:%M:%S\"; echo -en \\\x1Bp0~~ >/dev/ttyS1"' >> ~/.bashrc
echo 'alias kk="date +\"%b %d %l:%M:%S\"; echo -en \\\x10\\\x14\\\x01\\\x00\\\x08 >/dev/ttyS1"' >> ~/.bashrc
echo 'alias c="echo -en \\\n\\\n\\\n\\\n\\\x1DV1 >/dev/ttyS1"' >> ~/.bashrc
echo 'alias nv1="echo -en \\\x1Cp\\\x010 >/dev/ttyS1"' >> ~/.bashrc
echo 'alias log_cl="clear; > /CORE-POS/pos/is4c-nf/log/queries.log; > /CORE-POS/pos/is4c-nf/log/php-errors.log"' >> ~/.bashrc
echo 'alias log_pr="tail -f /CORE-POS/pos/is4c-nf/log/queries.log & tail -f /CORE-POS/pos/is4c-nf/log/php-errors.log &"' >> ~/.bashrc
echo 'S () { echo "S$@" > /dev/ttyS0; }' >> ~/.bashrc

# set up openbox autolaunch
mkdir -p ~/.config/openbox
touch ~/.config/openbox/autostart
sed -i '/xterm/d;/firefox/d;/geany/d;/smartgit/d;/xrandr/d;/numlockx/d;' ~/.config/openbox/autostart
echo 'xterm >/dev/null 2>&1 &' >> ~/.config/openbox/autostart
echo 'firefox >/dev/null 2>&1 &' >> ~/.config/openbox/autostart
# echo 'geany >/dev/null 2>&1 &' >> ~/.config/openbox/autostart
# echo 'smartgithg >/dev/null 2>&1 &' >> ~/.config/openbox/autostart
echo 'xrandr --output VGA1 --mode 1024x768 --rate 75' >> ~/.config/openbox/autostart
echo '[ -x /usr/bin/numlockx ] && /usr/bin/numlockx on' >> ~/.config/openbox/autostart

# set up git credentials
cp "$SUPPORT/template.gitconfig" ~/.gitconfig

# set up browser
/usr/bin/firefox -setDefaultBrowser 2>/dev/null &
sleep 10
kill `pidof firefox` 2>/dev/null
sed -i '/"browser.startup.homepage"/d' ~/.mozilla/firefox/*/prefs.js
echo 'user_pref("browser.startup.homepage", "http://localhost/lane/install/index.php");' >> ~/.mozilla/firefox/*/prefs.js
