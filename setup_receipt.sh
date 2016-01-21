#~/bin/sh
# run this script under sudo!

# set up variables
if [ -z "$SUPPORT" ]; then
	SUPPORT=/CORE-Support
fi
if [ -z "$COREPOS" ]; then
	COREPOS=/CORE-POS
fi
if [ -z "$RECEIPTS" ]; then
	RECEIPTS="$COREPOS/pos/is4c-nf/lib/ReceiptBuilding"
fi


# commented out while we've got our own CORE-POS fork
## add George Street receipt savings plugin to "stock" versions
## ln -svf "$SUPPORT/SimpleReceiptSavings.php" "$RECEIPTS/ReceiptSavings/"


# create alias to rewrite TM-T88III NVRAM slot 1 with Co-op logo;
# we hold back from just rewriting it, because NVRAM can wear out from repeated rewrites!
alias nv1cp="cp '$SUPPORT/GeorgeStreetCoopLogo_TMT88III.tlg' /dev/ttyS2"
