#!/bin/sh

# run this script under sudo!

apt-get install git
rm -rf /CORE-Support/
mkdir /CORE-Support/
cd /CORE-Support/
git clone https://github.com/GeorgeStreetCoop/CORE-Support.git /CORE-Support/
chown -Rf coop /CORE-Support
