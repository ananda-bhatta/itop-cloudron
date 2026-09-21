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
    if docker exec "$database" mysqladmin ping -h localhost -uroot -ptest-root-only --silent >/dev/null 2>&1; then
        break
    fi
    sleep 2
done

run_app() {
    docker run -d --name "$app" --network "$network" --read-only \
        --tmpfs /run --tmpfs /tmp --mount "source=$volume,target=/app/data" \
        -e CLOUDRON_MYSQL_HOST=mysql -e CLOUDRON_MYSQL_PORT=3306 \
        -e CLOUDRON_MYSQL_DATABASE=itop -e CLOUDRON_MYSQL_USERNAME=itop \
        -e CLOUDRON_MYSQL_PASSWORD=test-only -e CLOUDRON_APP_ORIGIN=https://itop.example.com \
        "$image" >/dev/null
    for attempt in {1..60}; do
        if docker exec "$app" curl -fsS http://localhost:8000/cloudron-health >/dev/null 2>&1; then
            return
        fi
        sleep 2
    done
    echo 'Application never became healthy.' >&2
    return 1
}
status() {
    docker exec "$app" curl -s -o /dev/null -w '%{http_code}' "http://localhost:8000$1"
}
run_app
[[ $(status /setup/) == 401 ]]
[[ $(status /conf/index.php) == 403 ]]
[[ $(status /data/) == 403 ]]
[[ $(status /initial-setup.txt) == 404 ]]
# Authenticate without printing or passing the setup password from the host.
docker exec "$app" bash -c 'curl -fsS -u "setup:$(cat /app/data/setup-password)" http://localhost:8000/setup/ > /tmp/setup.html; grep -qi itop /tmp/setup.html'
docker exec "$app" /app/code/cron.sh
docker exec --user www-data "$app" bash -c 'echo persisted > /app/data/public/data/test-marker; mkdir -p /app/data/public/env-production-build; mv /app/data/public/env-production-build /app/data/public/env-test'
docker rm -f "$app" >/dev/null
run_app
docker exec "$app" grep -qx persisted /app/data/public/data/test-marker
docker exec "$app" test -d /app/data/public/env-test
docker stop "$database" >/dev/null
[[ $(status /cloudron-health) == 503 ]]
echo 'Read-only container, setup protection, persistence and database health checks passed.'
