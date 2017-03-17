#!/bin/bash
set -ex
cd install
docker-compose up -d

#### fossology needs up to 15 seconds to startup
sleep 15

curl -L -s http://127.0.0.1:8081/repo/ | grep -q "<title>Getting Started with FOSSology</title>"
