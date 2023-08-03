#!/bin/bash
# run this script under sudo!

if [ -z "$SUPPORT" ]; then
	SUPPORT=/CORE-Support
fi
if [ -z "$COREPOS" ]; then
	COREPOS=/CORE-POS
fi

# check that we're a lane
if [ -z "$LANENUMBER" ]; then
	LANEID=`hostname`
	LANENUMBER=`echo ${LANEID}|sed -n 's/^lane\([0-9]*\)$/\1/p'`
fi


# create plugin link from CORE-POS plugins folder to actual plugin located in Support directory
if [ "$LANENUMBER" -gt 0 ]; then
	mkdir -m775 -p "$COREPOS/pos/is4c-nf/plugins/InventoryUpdateGSC"
	ln -svf "$SUPPORT/InventoryUpdateGSC.php" "$COREPOS/pos/is4c-nf/plugins/InventoryUpdateGSC/InventoryUpdateGSC.php"
fi
