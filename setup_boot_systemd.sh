#~/bin/sh
# run this script under sudo!

# exit if we're creating a backend-only setup
if [ "$LANENUMBER" == 0 ]; then
	echo "This is not a POS lane; skipping serial port setup."
	return
fi

# set up variables
if [ -z "$SUPPORT" ]; then
	SUPPORT=/CORE-Support
fi
if [ -z "$COREPOS" ]; then
	COREPOS=/CORE-POS
fi
if [ -z "$SSDDIR" ]; then
	SSDDIR="$COREPOS/pos/is4c-nf/scale-drivers/drivers/rs232"
fi


# tell systemd to run ssd on every boot
ln -svf "$SUPPORT/CORE-POS.service" /etc/systemd/system
systemctl daemon-reload
systemctl enable CORE-POS
systemctl restart CORE-POS
