#!/bin/bash
# run this script under sudo!


# set up context
if [ -z "$SUPPORT" ]; then
	SUPPORT=/CORE-Support
fi


# create base tables
echo "Running base install page to create tables..."
wget -O /dev/null -q "http://localhost/lane/install/index.php"


# use office server sync function to populate tables; WARNING will repopulate ALL attached lanes!
while read tablename
do
    echo "Syncing $tablename..."
    wget -O /dev/null -q "http://rambutan/office/sync/TableSyncPage.php?tablename=&othertable=$tablename"
done < "$SUPPORT/opdata_tables.txt"
