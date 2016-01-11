#!/bin/bash
# run this script under sudo!

# set up grub boot process
sed -i '/^GRUB_CMDLINE_LINUX_DEFAULT=.*quiet\|splash.*/s/^GRUB_CMDLINE_LINUX_DEFAULT=\(.*\)\(quiet\|splash\)\(.*\)\(quiet\|splash\)\(.*\)$/# was &\nGRUB_CMDLINE_LINUX_DEFAULT=\1\3\5/' /etc/default/grub
sed -i '/^GRUB_CMDLINE_LINUX=.*quiet\|splash.*/s/^GRUB_CMDLINE_LINUX=\(.*\)\(quiet\|splash\)\(.*\)\(quiet\|splash\)\(.*\)$/# was &\nGRUB_CMDLINE_LINUX=\1\3\5/' /etc/default/grub
sed -i '/^GRUB_HIDDEN_TIMEOUT=.*/s/^GRUB_HIDDEN_TIMEOUT=\(.*\)$/GRUB_TIMEOUT_STYLE=hidden # deprecated GRUB_HIDDEN_TIMEOUT=\1/' /etc/default/grub
sed -i 's/.*GRUB_INIT_TUNE=.*/GRUB_INIT_TUNE="480 440 1 660 1 880 1 660 1 440 3"/' /etc/default/grub
update-grub
