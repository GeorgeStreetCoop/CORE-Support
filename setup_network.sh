#~/bin/sh
# run this script under sudo!

# set up variables
if [ -z "$SUPPORT" ]; then
	SUPPORT=/CORE-Support
fi
if [ -z "$COREPOS" ]; then
	COREPOS=/CORE-POS
fi


# set up static IP
# 2016-01-02: commented out as seems to break networking entirely; reserved DHCP is sufficient.
# sed "s/###LANEIP###/${LANEIP}/g" "$SUPPORT/template.interfaces" > /etc/network/interfaces

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


# set up mysql for network use
sed -i "/bind-address/s/\(= *\).*\$/\1${LANEIP}/" /etc/mysql/my.cnf
sed -i '/skip-networking/s/^\( *skip-networking\)/# \1/' /etc/mysql/my.cnf
