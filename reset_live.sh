#!/bin/sh
[ "$USER" != root ] && exec sudo $0 "$@"

numlockx
killall ssd
# nohup /sbin/start CORE-POS >/dev/null 2>&1 &
/usr/sbin/service CORE-POS start
