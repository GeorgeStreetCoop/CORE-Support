#~/bin/sh
# run this script under sudo!

# set up variables
if [ -z "$SUPPORT" ]; then
	SUPPORT=/CORE-Support
fi
if [ -z "$COREPOS" ]; then
	COREPOS=/CORE-POS
fi


# set up static IP from hostname lane number and IP address
# 2016-01-02: commented out as seems to break networking entirely; reserved DHCP is sufficient.
## use hostname ("lane#") to determine IP address
## LANEID=`hostname`
## LANENUMBER=`echo ${LANEID}|sed -n 's/^lane\([0-9]*\)$/\1/p'`
## if [ -z "$LANENUMBER" ]; then
## 	echo "Host '${LANEID}' does not appear to be a POS lane. Aborting lane install." >&2
## 	exit 2
## fi
## LANEIP="192.168.1.$((${LANENUMBER}+50))"
## echo "Setting up POS lane #${LANENUMBER} to use IP address ${LANEIP}..."
## sed "s/###LANEIP###/${LANEIP}/g" "$SUPPORT/template.interfaces" > /etc/network/interfaces


# set up fannie and lane hosts
sed -i '/^192.168.1.50\b/d' /etc/hosts
sed -i '/^192.168.1.51\b/d' /etc/hosts
sed -i '/^192.168.1.52\b/d' /etc/hosts
sed -i '/^192.168.1.53\b/d' /etc/hosts
sed -i '/\bfannie\b/d' /etc/hosts
sed -i '/\blane1\b/d' /etc/hosts
sed -i '/\blane2\b/d' /etc/hosts
sed -i '/\blane3\b/d' /etc/hosts
sed -i '$a 192.168.1.50    fannie' /etc/hosts
sed -i '$a 192.168.1.51    lane1' /etc/hosts
sed -i '$a 192.168.1.52    lane2' /etc/hosts
sed -i '$a 192.168.1.53    lane3' /etc/hosts
