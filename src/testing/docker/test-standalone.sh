#!/usr/bin/env bash
# SPDX-FileCopyrightText: © Fossology contributors

# SPDX-License-Identifier: GPL-2.0-only

set -o errexit -o nounset -o xtrace

#### build image
docker compose build web

#### start container
readonly CONTAINER_ID="$(docker run --rm -p 127.0.0.1::80 -d fossology)"

#### is container running?
docker inspect -f "{{.State.Running}}" "${CONTAINER_ID}" | grep -q true

#### fossology needs up to 15 seconds to startup
sleep 15

readonly HOST="$(docker port "${CONTAINER_ID}" 80)"

#### is fossology reachable? --> check title
curl --silent --location "http://${HOST}/repo/" | grep -q "<title>Getting Started with FOSSology</title>"

#### test copyright is running
docker exec "${CONTAINER_ID}" /usr/local/share/fossology/copyright/agent/copyright -h

#### test whether the scheduler is running
docker exec "${CONTAINER_ID}" /usr/local/share/fossology/scheduler/agent/fo_cli -S

docker stop "${CONTAINER_ID}"
