#!/bin/sh
host=$(/sbin/ip route|awk '/default/ { print $3 }')
replace=$(echo "host = $host")
awk -v new="$replace" '{ if (NR == 2) print new; else if (NR == 9) print new; else print $0}' config.template > config.ini
python worker.py