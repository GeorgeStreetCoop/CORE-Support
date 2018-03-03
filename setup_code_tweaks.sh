#!/bin/bash
# run this script under sudo!

if [ -z "$COREPOS" ]; then
	COREPOS=/CORE-POS
fi


# Expand QuickMenus selection to fit contents (max 24)
# see https://github.com/CORE-POS/IS4C/pull/973/commits/1dcb8bdffe67eb535c48db11d5b950601fd3736d
replace 'size="10"' 'size="'\''.min(count($my_menu), 24).'\''"' -- "$COREPOS/pos/is4c-nf/plugins/QuickMenus/QMDisplay.php"
