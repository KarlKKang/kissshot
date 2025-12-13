#!/bin/bash

plugin_name=$1
op=$2

if [ -z "$plugin_name" ]; then
    echo "Usage: $0 <plugin_name>"
    exit 1
fi

# Remove cron job scripts
rm -f "/etc/cron.daily/${plugin_name}.daily.sh"
rm -f "/etc/cron.hourly/${plugin_name}.hourly.sh"

# Stop and remove DDNS service
if [ -x /etc/rc.d/rc.kissshot-ddns ]; then
    /etc/rc.d/rc.kissshot-ddns stop
    sleep 1
fi
rm -f /etc/rc.d/rc6.d/K10kissshot-ddns
rm -f /etc/rc.d/rc.kissshot-ddns
rm -f /usr/local/sbin/kissshot-ddns

# Stop and remove xrayd
if [ -x /etc/rc.d/rc.xrayd ]; then
    if [ "$op" = "reload" ]; then
        /etc/rc.d/rc.xrayd reload
    else
        /etc/rc.d/rc.xrayd stop
        sleep 1
    fi
fi
rm -f /etc/rc.d/rc6.d/K10xrayd
rm -f /etc/rc.d/rc.xrayd
rm -f /usr/local/sbin/xrayd

# Remove emhttp files so we can re-install.
rm -f -r "/usr/local/emhttp/plugins/${plugin_name}"