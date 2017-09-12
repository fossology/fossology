#!/usr/bin/env bash
set -ex
cd install

docker-compose build --force-rm
docker-compose up -d

#### fossology needs up to 15 seconds to startup
sleep 15

#### is fossology reachable? --> check title
curl -L -s http://127.0.0.1:8081/repo/ | grep -q "<title>Getting Started with FOSSology</title>"

#### test whether the scheduler is running
docker exec -it install_fossology-scheduler_1 /usr/local/share/fossology/scheduler/agent/fo_cli -S
