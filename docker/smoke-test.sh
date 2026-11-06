#!/bin/sh
# Boots the production image against real MySQL with TLS required, then checks
# the things Playground can't: the image, wp-config.php, Apache, and MySQL.
set -eu
image="$1"
network=naturedex-smoke
password=smoke-test-only

cleanup() {
  status=$?
  if [ "$status" -ne 0 ]; then
    docker logs naturedex-web 2>&1 | tail -40 || true
  fi
  docker rm -f naturedex-web naturedex-db >/dev/null 2>&1 || true
  docker network rm "$network" >/dev/null 2>&1 || true
  exit "$status"
}
trap cleanup EXIT

docker network create "$network" >/dev/null
docker run -d --name naturedex-db --network "$network" \
  -e MYSQL_ROOT_PASSWORD="$password" -e MYSQL_DATABASE=wordpress \
  -e MYSQL_USER=naturedex -e MYSQL_PASSWORD="$password" \
  mysql:8.4 --require-secure-transport=ON >/dev/null

echo 'Waiting for MySQL'
for _ in $(seq 1 60); do
  # Connect over TCP as the app user: the image's first start also runs a
  # socket-only server, which would otherwise look ready too early.
  docker exec naturedex-db mysql -h127.0.0.1 -unaturedex -p"$password" -e 'SELECT 1' wordpress >/dev/null 2>&1 && break
  sleep 2
done

set -- --network "$network" \
  -e NATUREDEX_HOME_URL=https://naturedex.test \
  -e NATUREDEX_DB_NAME=wordpress -e NATUREDEX_DB_HOST=naturedex-db \
  -e NATUREDEX_DB_USER=naturedex -e NATUREDEX_DB_PASSWORD="$password" \
  -e NATUREDEX_AUTH_SECRET=smoke-test-only-secret \
  -e NATUREDEX_ADMIN_USER=naturedex-admin -e NATUREDEX_ADMIN_PASSWORD="$password" \
  -e NATUREDEX_ADMIN_EMAIL=admin@example.com

echo 'Running the bootstrap task'
docker run --rm "$@" "$image" naturedex-bootstrap
echo 'Running it again, which must change nothing'
docker run --rm "$@" "$image" naturedex-bootstrap | tee /tmp/naturedex-second-run
grep -q 'Imported 0 species, 0 field notes, and 0 pages' /tmp/naturedex-second-run

echo 'Running the scheduled task command'
docker run --rm "$@" "$image" wp --allow-root cron event run --due-now

docker run -d --name naturedex-web -p 8080:80 "$@" "$image" >/dev/null
for _ in $(seq 1 30); do
  curl -sf http://127.0.0.1:8080/healthz.html >/dev/null && break
  sleep 1
done

expect() {
  actual=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:8080$1")
  if [ "$actual" != "$2" ]; then
    echo "FAIL $1: expected $2, got $actual"
    exit 1
  fi
  echo "ok   $1 -> $actual"
}
contains() {
  if ! curl -s "http://127.0.0.1:8080$1" | grep -q "$2"; then
    echo "FAIL $1: missing \"$2\""
    exit 1
  fi
  echo "ok   $1 contains \"$2\""
}

expect /healthz.html 200
expect / 200
expect /map 200
expect /journal 200
expect /journal/read-the-water 200
expect /about 200
expect /species/red-fox 200
expect /no-such-trail 404
expect /editor 302
expect /wp-login.php 200
expect /xmlrpc.php 403
expect /wp-admin/install.php 403
expect /wp-admin/upgrade.php 403
expect /wp-cron.php 403
expect /wp-trackback.php 403
expect /wp-links-opml.php 403
expect /wp-signup.php 403
contains / 'Meet your wild neighbors'
contains /wp-json/naturedex/v1/regions 'Blue Ridge'
count=$(curl -s http://127.0.0.1:8080/wp-json/naturedex/v1/species | grep -o '"dexNumber"' | wc -l)
if [ "$count" -ne 24 ]; then
  echo "FAIL expected 24 species, got $count"
  exit 1
fi
echo 'ok   24 species'
if ! curl -sI http://127.0.0.1:8080/ | grep -qi 's-maxage=300'; then
  echo 'FAIL public pages must be cacheable'
  exit 1
fi
echo 'ok   public pages are cacheable'
server=$(curl -sI http://127.0.0.1:8080/ | tr -d '\r' | awk 'tolower($1)=="server:"{print $2}')
if [ "$server" != Apache ]; then
  echo "FAIL server header reveals a version: $server"
  exit 1
fi
echo 'ok   server header hides the version'
echo 'Production image passed.'
