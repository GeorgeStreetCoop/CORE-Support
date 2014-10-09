#!/bin/sh

# run this script under sudo!

apt-get install git

COREPOS=/CORE-POS
SUPPORT=/CORE-Support
LANEIDFILE=$SUPPORT/laneid.txt

rm -rf $SUPPORT
mkdir $SUPPORT
cd $SUPPORT
git clone https://github.com/GeorgeStreetCoop/CORE-Support.git $SUPPORT
chown -Rf coop $SUPPORT

. ./apt-updates.sh

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

rm -rf $COREPOS
mkdir $COREPOS
cd $COREPOS
git clone https://github.com/CORE-POS/IS4C.git $COREPOS
chown -Rf coop $COREPOS

touch "$COREPOS/pos/is4c-nf/log/php-errors.log"
chown www-data "$COREPOS/pos/is4c-nf/log/php-errors.log"

touch "$COREPOS/pos/is4c-nf/log/queries.log"
chown www-data "$COREPOS/pos/is4c-nf/log/queries.log"
