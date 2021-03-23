#!/bin/bash
set -e

chmod 0644 /etc/cron.d/impero-cron
crontab /etc/cron.d/impero-cron

echo "running cron"
chmod 0644 /etc/cron.d/impero-cron
echo "chmoded"
crontab /etc/cron.d/impero-cron
echo "crontabbed"
cron -f
echo "ran cron, sleeping"
sleep 60
echo "slept"
