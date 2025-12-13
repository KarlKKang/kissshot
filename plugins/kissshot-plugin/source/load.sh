#!/bin/bash

plugin_name=$1

if [ -z "$plugin_name" ]; then
    echo "Usage: $0 <plugin_name>"
    exit 1
fi

mkdir -p /etc/rc.d/rc6.d

# Install and start DDNS service
ln -s "/usr/local/emhttp/plugins/${plugin_name}/system/ddns" /usr/local/sbin/kissshot-ddns
mv "/usr/local/emhttp/plugins/${plugin_name}/rc.d/rc.kissshot-ddns" /etc/rc.d/rc.kissshot-ddns
mv "/usr/local/emhttp/plugins/${plugin_name}/rc.d/rc6.d/K10kissshot-ddns" /etc/rc.d/rc6.d/K10kissshot-ddns
/etc/rc.d/rc.kissshot-ddns start

# Install and start xrayd
ln -s "/usr/local/emhttp/plugins/${plugin_name}/system/xrayd" /usr/local/sbin/xrayd
mv "/usr/local/emhttp/plugins/${plugin_name}/rc.d/rc.xrayd" /etc/rc.d/rc.xrayd
mv "/usr/local/emhttp/plugins/${plugin_name}/rc.d/rc6.d/K10xrayd" /etc/rc.d/rc6.d/K10xrayd
/etc/rc.d/rc.xrayd start

# Install cron job scripts
mv "/usr/local/emhttp/plugins/${plugin_name}/cron/${plugin_name}.daily.sh" "/etc/cron.daily/${plugin_name}.daily.sh"
mv "/usr/local/emhttp/plugins/${plugin_name}/cron/${plugin_name}.hourly.sh" "/etc/cron.hourly/${plugin_name}.hourly.sh"