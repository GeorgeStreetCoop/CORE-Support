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

# set up scanner and printer serial ports
chmod 666 /dev/ttyS0
chmod 666 /dev/ttyS1

# set up "scanner" and "scale" output files
touch "$SSDDIR/scanner" "$SSDDIR/scale"
chmod 666 "$SSDDIR/scanner" "$SSDDIR/scale"

# overwrite "stock" ssd & config with links to George Street's versions
ln -svf "$SUPPORT/ssd" "$SSDDIR/ssd"
ln -svf "$SUPPORT/ssd.conf" "$SSDDIR/ssd.conf"

# set ssd to run on boot
rm -f "$SUPPORT/ssddir"
ln -svf "$SSDDIR" "$SUPPORT/ssddir"
ln -svf "$SUPPORT/CORE-POS.conf" /etc/init/
