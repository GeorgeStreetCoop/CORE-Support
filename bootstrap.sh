#!/bin/sh

# run this script under sudo!

apt-get install wget unzip
wget https://github.com/GeorgeStreetCoop/CORE-Support/archive/master.zip -O ~/CORE-Support.zip
unzip -o ~/CORE-Support.zip -d ~/ && rm -rf /CORE-Support &&  mv ~/CORE-Support-master /CORE-Support
chown -Rf coop /CORE-Support
rm ~/CORE-Support.zip
