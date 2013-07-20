#!/bin/sh

touch "/CORE-Support/ini.php"
ln -s "/CORE-Support/ini.php" "/CORE-POS/pos/is4c-nf/ini.php"

touch "/CORE-Support/ini-local.php"
ln -s "/CORE-Support/ini-local.php" "/CORE-POS/pos/is4c-nf/ini-local.php"

touch "/CORE-POS/pos/is4c-nf/log/php-errors.log"
chown www-data "/CORE-POS/pos/is4c-nf/log/php-errors.log"

touch "/CORE-POS/pos/is4c-nf/log/queries.log"
chown www-data "/CORE-POS/pos/is4c-nf/log/queries.log"

echo Done
