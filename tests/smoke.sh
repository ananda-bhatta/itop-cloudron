#!/bin/bash
set -euo pipefail
image=${1:?Usage: tests/smoke.sh IMAGE}
prefix="itop-test-${RANDOM}-$$"
network="$prefix-net"
database="$prefix-db"
app="$prefix-app"
volume="$prefix-data"

cleanup() {
    docker logs "$app" 2>/dev/null || true
    docker rm -f "$app" "$database" >/dev/null 2>&1 || true
    docker volume rm "$volume" >/dev/null 2>&1 || true
    docker network rm "$network" >/dev/null 2>&1 || true
}
trap cleanup EXIT
docker network create "$network" >/dev/null
docker volume create "$volume" >/dev/null
docker run -d --name "$database" --network "$network" --network-alias mysql \
    -e MYSQL_ROOT_PASSWORD=test-root-only -e MYSQL_DATABASE=itop \
    -e MYSQL_USER=itop -e MYSQL_PASSWORD=test-only mysql:8.4 >/dev/null
for attempt in {1..90}; do
    if docker exec "$database" mysql --protocol=TCP -h 127.0.0.1 -uitop -ptest-only itop -e 'SELECT 1' >/dev/null 2>&1; then
        break
    fi
    sleep 2
done

run_app() {
    docker run -d --name "$app" --network "$network" --read-only \
        --tmpfs /run --tmpfs /tmp --mount "source=$volume,target=/app/data" \
        --mount "type=bind,source=$PWD/tests,target=/tests,readonly" \
        -e CLOUDRON_MYSQL_HOST=mysql -e CLOUDRON_MYSQL_PORT=3306 \
        -e CLOUDRON_MYSQL_DATABASE=itop -e CLOUDRON_MYSQL_USERNAME=itop \
        -e CLOUDRON_MYSQL_PASSWORD=test-only -e CLOUDRON_APP_ORIGIN=https://itop.example.com \
        "$image" >/dev/null
    for attempt in {1..240}; do
        if docker exec "$app" curl -fsS http://localhost:8000/cloudron-health >/dev/null 2>&1; then
            return
        fi
        sleep 2
        if [[ $(docker inspect --format '{{.State.Running}}' "$app") != true ]]; then
            docker cp "$app:/app/data/bootstrap.log" "/tmp/$prefix-bootstrap.log" 2>/dev/null || true
            if [[ -f "/tmp/$prefix-bootstrap.log" ]]; then
                grep -E 'Error|Fatal|Exception|failed|ErrorException' "/tmp/$prefix-bootstrap.log" | tail -n 12 || true
            fi
            return 1
        fi
    done
    echo 'Application never became healthy.' >&2
    return 1
}
status() {
    docker exec "$app" curl -s -o /dev/null -w '%{http_code}' "http://localhost:8000$1"
}
run_app
docker exec "$app" bash -c 'curl -fsS -D /tmp/login-headers http://localhost:8000/pages/UI.php > /tmp/login-page; grep -qi "auth_user" /tmp/login-page; grep -i "^set-cookie:.*itop-" /tmp/login-headers | grep -qi "; secure"'
[[ $(status /setup/) == 401 ]]
[[ $(status /conf/index.php) == 403 ]]
[[ $(status /data/) == 403 ]]
[[ $(status /initial-setup.txt) == 404 ]]
[[ $(status /initial-admin.txt) == 404 ]]
# Authenticate without printing or passing the setup password from the host.
docker exec "$app" bash -c 'curl -fsS -u "setup:$(cat /app/data/setup-password)" http://localhost:8000/setup/wizard.php > /tmp/setup.html; grep -qi itop /tmp/setup.html'
[[ $(docker exec "$app" bash -c 'curl -s -o /dev/null -w "%{http_code}" -u "setup:$(cat /app/data/setup-password)" http://localhost:8000/setup/permissions-test-folder/permissions-test-subfolder/permissions-test-file') == 403 ]]
docker exec "$app" test -f /app/data/public/conf/production/config-itop.php
docker exec "$app" grep -qx complete /app/data/.bootstrap-state
docker exec "$app" test ! -f /app/data/.bootstrap-credentials.json
docker exec "$app" test ! -f /run/itop-bootstrap/response.xml
docker exec "$app" gosu www-data:www-data php8.4 /app/data/public/webservices/cron.php --param_file=/app/data/cron.params --status_only=1
docker exec "$app" bash -c 'cat /app/data/initial-admin.txt | gosu www-data:www-data php8.4 /tests/bootstrap.php change'
docker exec --user www-data "$app" bash -c 'echo persisted > /app/data/public/data/test-marker; mkdir -p /app/data/public/env-production-build; mv /app/data/public/env-production-build /app/data/public/env-test'
docker rm -f "$app" >/dev/null
run_app
docker exec "$app" grep -qx persisted /app/data/public/data/test-marker
docker exec "$app" test -d /app/data/public/env-test
docker exec "$app" gosu www-data:www-data php8.4 /tests/bootstrap.php verify
docker stop "$database" >/dev/null
[[ $(status /cloudron-health) == 503 ]]
echo 'Automatic setup, admin login, cron authentication, directory protection and restart persistence checks passed.'
