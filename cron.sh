#!/bin/bash
set -euo pipefail
if [[ ! -f /app/data/public/conf/production/config-itop.php ]]; then
    exit 0
fi
if [[ -f /app/data/.bootstrap-state ]] && [[ $(cat /app/data/.bootstrap-state) != complete ]]; then
    exit 0
fi
if [[ ! -f /app/data/cron.params ]]; then
    echo 'iTop background tasks need /app/data/cron.params; see POSTINSTALL.md.'
    exit 0
fi
cd /app/data/public
exec /usr/local/bin/gosu www-data:www-data php8.4 webservices/cron.php --param_file=/app/data/cron.params
