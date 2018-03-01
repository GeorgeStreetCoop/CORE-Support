#!/bin/bash
# run this script under sudo!

# set up PHP php.ini
sed -i 's/^short_open_tag = Off/short_open_tag = On/' /etc/php5/cli/php.ini
