#!/bin/bash
# run this script under sudo!


# set up context
if [ -z "$WD_BEFORE_COREPOS_SETUP" ]; then
	WD_BEFORE_COREPOS_SETUP=`pwd`
fi
if [ -z "$SUPPORT" ]; then
	SUPPORT=/CORE-Support
fi
if [ -z "$COREPOS" ]; then
	COREPOS=/CORE-POS
fi


# check that we're a lane
LANEID=`hostname`
LANENUMBER=`echo ${LANEID}|sed -n 's/^lane\([0-9]*\)$/\1/p'`
if [ -z "$LANENUMBER" ]; then
	read -p "Host '${LANEID}' does not appear to be a POS lane. Install backend only? [y/N] " -n 1 -r
	echo
	if [[ ! $REPLY =~ ^[Yy]$ ]]; then
		echo "Aborting lane install." >&2
		return
	fi
	LANENUMBER=0
fi


# get password for mysql 'lane' user
while [ -z "$LANEPASSWORD" ]; do
	read -s -p "What is the password for the mysql 'lane' user? " LANEPASSWORD
done


# bootstrap git
apt-get -y install git


# get latest CORE-Support directory; if user specified "rm" argument, deletes old one
if [ -n "$1" -a "$1" = "rm" ]; then
	rm -rf "$SUPPORT"
	mkdir -p "$SUPPORT" 2>/dev/null
	cd "$SUPPORT"
	git clone https://github.com/GeorgeStreetCoop/CORE-Support.git "$SUPPORT"
else
	if [ ! -d "$SUPPORT" ]; then
		echo "Directory '$SUPPORT' doesn't exist. Aborting lane install. Try again with 'rm' override parameter?" >&2
		return
	fi
	cd "$SUPPORT"
	git reset --hard HEAD
	git pull
fi
chown -Rf cashier "$SUPPORT"


# hand off to main setup routine
. "$SUPPORT/setup.sh" "$1"


# cleanup environment
unset LANEPASSWORD
if [ -n "$WD_BEFORE_COREPOS_SETUP" ]; then
	cd "$WD_BEFORE_COREPOS_SETUP"
fi
