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


# give numeric links to named QuickMenus in Support, then link to whole Support directory from CORE-POS
if [ "$LANENUMBER" -gt 0 ]; then
	ln -svf "$SUPPORT/QuickMenus/GrabGo.php" "$SUPPORT/QuickMenus/1.php"
	ln -svf "$SUPPORT/QuickMenus/Soups.php" "$SUPPORT/QuickMenus/2.php"
	ln -svf "$SUPPORT/QuickMenus/Waters.php" "$SUPPORT/QuickMenus/3.php"
	ln -svf "$SUPPORT/QuickMenus/OpenRing.php" "$SUPPORT/QuickMenus/4.php"
	chown -R www-data "$SUPPORT/QuickMenus"

	mv "$COREPOS/pos/is4c-nf/plugins/QuickMenus/quickmenus" "$COREPOS/pos/is4c-nf/plugins/QuickMenus/quickmenus~"
	ln -svf "$SUPPORT/QuickMenus" "$COREPOS/pos/is4c-nf/plugins/QuickMenus/quickmenus"
fi
