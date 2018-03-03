#!/bin/bash
# run this script under sudo!


# set up PHP php.ini
sed -i 's/^short_open_tag = Off/short_open_tag = On/' /etc/php*/*/php.ini /etc/php/*/*/php.ini 2&>/dev/null
