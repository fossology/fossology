#!/usr/bin/env bash
# SPDX-FileCopyrightText: © Fossology contributors

# SPDX-License-Identifier: GPL-2.0-only

set -o errexit -o nounset -o xtrace

docker compose build
docker compose up -d

#### fossology needs up to 15 seconds to startup
sleep 15

readonly HOST="$(docker compose port web 80)"

#### is fossology reachable? --> check title
curl --silent --location "http://${HOST}/repo/" | grep -q "<title>Getting Started with FOSSology</title>"

#### test copyright is running
docker compose exec -T web /usr/local/share/fossology/copyright/agent/copyright -h

#### test whether the scheduler is running
for attempt in {1..30}; do
  if docker compose exec -T scheduler /usr/local/share/fossology/scheduler/agent/fo_cli -S; then
    break
  fi

  if [[ "$attempt" -eq 30 ]]; then
    docker compose logs scheduler
    exit 1
  fi

  sleep 2
done

docker compose down
