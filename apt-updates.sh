#!/bin/sh
for REPO in `cat repositories.txt`
do
	sudo add-apt-repository -y $REPO
done
sudo apt-get -qq update
sudo apt-get install `cat packages.txt`

