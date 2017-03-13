#!/bin/bash
docker build -t fossology/test . 
container_id=`docker run -d fossology/test`
ip=`docker inspect --format '{{ .NetworkSettings.IPAddress }}' $container_id | awk "NR==1{print;exit}"`
docker ps | grep -q fossology/test
curl -L -I http://$ip/repo | grep -q "200 OK"