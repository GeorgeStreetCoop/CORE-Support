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

# get password for mysql 'lane' user
while [ -z "$RECEIPT_PRINTER" ]; do
	read -p "Where is the receipt printer attached (usually '/dev/ttyS1' or '/dev/ttyUSB0'')? " RECEIPT_PRINTER
done

ln -svf "$RECEIPT_PRINTER" "$SUPPORT/receipt_printer"


# set up scanner and printer serial ports
chmod 666 /dev/ttyS0
chmod 666 "$SUPPORT/receipt_printer"

# set up "scanner" and "scale" output files
rm -f "$SUPPORT/ssddir"
ln -svf "$SSDDIR" "$SUPPORT/ssddir"
touch "$SSDDIR/scanner" "$SSDDIR/scale"
chmod 666 "$SSDDIR/scanner" "$SSDDIR/scale"

# overwrite "stock" ssd & config with links to George Street's versions
ln -svf "$SUPPORT/ssd" "$SSDDIR/ssd"
ln -svf "$SUPPORT/ssd.conf" "$SSDDIR/ssd.conf"
