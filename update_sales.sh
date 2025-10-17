#!/bin/sh
cd /CORE-Support && . ./update_office_env.sh && php update_office.php xfer_sales start_date=`date --date=-1\ day +\%Y-\%m-\%d`
