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

	read -p "Please enter your existing MySQL ROOT password: " -s MYSQL_ROOT_PW
	echo

	# set up mysql users
	read -p "Please enter the MySQL LANE password you'd like to use: " -s MYSQL_LANE_PW
	echo
	read -p "Please enter the MySQL OFFICE password you'd like external lane data updates to use: " -s MYSQL_OFFICE_PW
	echo
	mysql -u root -p"$MYSQL_ROOT_PW" --force -e "CREATE USER IF NOT EXISTS lane@localhost; ALTER USER lane@localhost IDENTIFIED WITH mysql_native_password BY '$MYSQL_LANE_PW';"
	mysql -u root -p"$MYSQL_ROOT_PW" --force -e "CREATE USER IF NOT EXISTS office@'192.168.1.%'; ALTER USER office@'192.168.1.%' IDENTIFIED WITH mysql_native_password BY '$MYSQL_OFFICE_PW';"

	# set up user permissions and basic data
 	mysql -u root -p"$MYSQL_ROOT_PW" --force < "$SUPPORT/setup_db.sql"

	# clean up passwords
	unset MYSQL_ROOT_PW MYSQL_LANE_PW MYSQL_OFFICE_PW

	# allow remote mysql access by setting 'bind-address = 0.0.0.0' in /etc/mysql/mysql.conf.d/mysqld.cnf
	HOST_IP=`hostname -I`
	cp /etc/mysql/mysql.conf.d/mysqld.cnf /etc/mysql/mysql.conf.d/mysqld.cnf~
	sed -i "s/^\s*#*\s*bind-address\(\s*=\s*\)127\.0\.0\.1\s*\$/bind-address\t= 0.0.0.0 # CORE-Support setup_mysql.sh\nsql-mode\t= NO_ENGINE_SUBSTITUTION # CORE-Support setup_mysql.sh/" /etc/mysql/mysql.conf.d/mysqld.cnf
fi


# restart mysql
systemctl restart mysql
