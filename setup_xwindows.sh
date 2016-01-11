#!/bin/bash
# run this script under sudo!

# set up variables
if [ -z "$SUPPORT" ]; then
	SUPPORT=/CORE-Support
fi


# set up X11
cmp -s "$SUPPORT/template.xorg.conf" /etc/X11/xorg.conf || cp /etc/X11/xorg.conf /etc/X11/xorg.conf~
cp "$SUPPORT/template.xorg.conf" /etc/X11/xorg.conf


# set up slim login manager
cmp -s "$SUPPORT/template.slim.conf" /etc/slim.conf || cp /etc/slim.conf /etc/slim.conf~
cp "$SUPPORT/template.slim.conf" /etc/slim.conf
