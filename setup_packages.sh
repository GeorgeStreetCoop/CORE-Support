#!/bin/sh

# first install add-apt-repository
sudo apt-get -y install software-properties-common python-software-properties

# add repositories
for REPO in `cat repositories.txt`
do
	sudo add-apt-repository -y $REPO
done

# add packages
sudo apt-get -qq update
sudo apt-get -y install `cat packages.txt`

# add packages
sudo apt-get -y remove `cat packages_remove.txt`
sudo apt-get -y autoremove

# update to latest versions
sudo apt-get -y upgrade
