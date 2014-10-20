#~/bin/sh
# run this script under sudo!

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

# set up scanner serial port
chmod 666 /dev/ttyS0

# set up "scanner" and "scale" output files
touch "$SSDDIR/scanner" "$SSDDIR/scale"
chmod 666 "$SSDDIR/scanner" "$SSDDIR/scale"

# overwrite "stock" ssd & config with links to George Street's versions
ln -svf "$SUPPORT/ssd" "$SSDDIR/ssd"
ln -svf "$SUPPORT/ssd.conf" "$SSDDIR/ssd.conf"

# set ssd to run on boot
rm -f "$SUPPORT/ssddir"
ln -svf "$SSDDIR" "$SUPPORT/ssddir"
ln -svf "$SUPPORT/ssd-boot.conf" /etc/init/
