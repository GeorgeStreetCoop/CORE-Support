#!/bin/sh

# run this as root

COREPOS=/CORE-POS
SUPPORT=/CORE-Support
LANEIDFILE=$SUPPORT/laneid.txt

if [ -f "$LANEIDFILE" ]; then
	LANEID=`cat $LANEIDFILE`
fi
if [ -z "$LANEID" ]; then
	while [ -z "$LANEID" ]; do
		read -p "What is the name of this POS lane? (lane1, lane2, etc) " LANEID
	done
	echo -n $LANEID > $LANEIDFILE
fi

echo "Lane ID is $LANEID, stored at $LANEIDFILE"
ls -l $LANEIDFILE

touch "$COREPOS/pos/is4c-nf/log/php-errors.log"
chown www-data "$COREPOS/pos/is4c-nf/log/php-errors.log"

touch "$COREPOS/pos/is4c-nf/log/queries.log"
chown www-data "$COREPOS/pos/is4c-nf/log/queries.log"
