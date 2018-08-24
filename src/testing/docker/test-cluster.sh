#!/usr/bin/env bash
set -o errexit -o nounset -o xtrace

docker-compose build
docker-compose up -d

#### fossology needs up to 15 seconds to startup
sleep 15

readonly HOST="$(docker-compose port web 80)"

#### is fossology reachable? --> check title
curl --silent --location "http://${HOST}/repo/" | grep -q "<title>Getting Started with FOSSology</title>"

#### test whether the scheduler is running
docker-compose exec scheduler /usr/local/share/fossology/scheduler/agent/fo_cli -S

docker-compose down
