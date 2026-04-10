#!/usr/bin/env bash
# SPDX-FileCopyrightText: © Fossology contributors

# SPDX-License-Identifier: GPL-2.0-only

set -o errexit -o nounset -o xtrace

docker compose build
docker compose up -d

#### Wait for web and scheduler to be ready instead of fixed sleep
readonly MAX_WAIT_SECONDS=120
readonly POLL_SECONDS=3

waited=0
until curl --silent --location "http://$(docker compose port web 80)/repo/" | grep -q "<title>Getting Started with FOSSology</title>"; do
	if (( waited >= MAX_WAIT_SECONDS )); then
		docker compose ps
		docker compose logs --no-color --tail=100 web scheduler db
		echo "Timed out waiting for web UI to become ready"
		exit 1
	fi
	sleep "${POLL_SECONDS}"
	waited=$((waited + POLL_SECONDS))
done

readonly HOST="$(docker compose port web 80)"

#### is fossology reachable? --> check title
curl --silent --location "http://${HOST}/repo/" | grep -q "<title>Getting Started with FOSSology</title>"

#### test copyright is running
docker compose exec -T web /usr/local/share/fossology/copyright/agent/copyright -h

#### test whether the scheduler is running
waited=0
until docker compose exec -T scheduler /usr/local/share/fossology/scheduler/agent/fo_cli -S; do
	if (( waited >= MAX_WAIT_SECONDS )); then
		docker compose ps
		docker compose logs --no-color --tail=100 scheduler db
		echo "Timed out waiting for scheduler RPC endpoint on port 24693"
		exit 1
	fi
	sleep "${POLL_SECONDS}"
	waited=$((waited + POLL_SECONDS))
done

docker compose down
