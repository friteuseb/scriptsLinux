#!/bin/bash

VPN_NAME="talanamiens-cyril_wolfangel 1"

if nmcli connection show --active | grep -q "$VPN_NAME"; then
    echo "Disconnecting from $VPN_NAME..."
    nmcli connection down id "$VPN_NAME"
else
    echo "Connecting to $VPN_NAME..."
    nmcli connection up id "$VPN_NAME"
fi



