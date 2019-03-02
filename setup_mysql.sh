#!/bin/bash
# run this script under sudo!

if [ -z "$SUPPORT" ]; then
	SUPPORT=/CORE-Support
fi

# check that we're a lane
if [ -z "$LANENUMBER" ]; then
	LANEID=`hostname`
	LANENUMBER=`echo ${LANEID}|sed -n 's/^lane\([0-9]*\)$/\1/p'`
fi


apt-get install mysql-server


# set up mysql users and basic data, allow remote mysql access
if [ "$LANENUMBER" -gt 0 ]; then
	echo 'When prompted below, please enter your mysql ROOT password...'
	mysql -u root -p --force < "$SUPPORT/setup_db.sql"

	# set 'bind-address = 0.0.0.0' in /etc/mysql/mysql.conf.d/mysqld.cnf
	HOST_IP=`hostname -I`
	cp /etc/mysql/mysql.conf.d/mysqld.cnf /etc/mysql/mysql.conf.d/mysqld.cnf~
	sed -i "s/^\s*#*\s*bind-address\(\s*=\s*\)127\.0\.0\.1\s*\$/bind-address\t= 0.0.0.0 # CORE-Support setup_mysql.sh\nsql-mode\t= NO_ENGINE_SUBSTITUTION # CORE-Support setup_mysql.sh/" /etc/mysql/mysql.conf.d/mysqld.cnf
fi


# restart mysql
service mysql restart
