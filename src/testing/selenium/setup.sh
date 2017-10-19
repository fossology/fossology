#!/bin/bash
# Copyright Siemens AG, 2017
# SPDX-License-Identifier:   GPL-2.0

set -e

#### get dependencies
sudo apt install curl

#### download images from dockerhub
#docker pull fossology/fossology:latest
docker pull selenium/standalone-chrome

#### remove old container, if any
fossology_running=`docker ps --all | grep fossology-test | wc -l`
if [ $fossology_running -gt 0 ]
then
    echo "Removed fossology-test"
    docker rm -f fossology-test
fi

selenium_running=`docker ps --all | grep selenium-test | wc -l` > /dev/null
if [ $selenium_running -gt 0 ]
then
    echo "Removed selenium-test"
    docker rm -f selenium-test
fi

testnet_running=`docker network ls | grep fossology-testnet | wc -l` > /dev/null
if [ $testnet_running -gt 0 ]
then
    echo "Removed network testnet"
    docker network remove fossology-testnet
fi

#### create network
docker network create --subnet=172.18.0.0/16 fossology-testnet

### build fossology/fossology from local changes
docker build -t fossology/fossology ../../../

#### create new container
docker create -p 8081:8081 --name fossology-test --net fossology-testnet --ip 172.18.0.22 fossology/fossology
# DBUS_SESSION_BUS_ADDRESS is needed, because https://github.com/SeleniumHQ/docker-selenium/issues/87
docker create -v /dev/shm:/dev/shm -e DBUS_SESSION_BUS_ADDRESS=/dev/null -p 4444:4444 --name selenium-test --net fossology-testnet --ip 172.18.0.23 selenium/standalone-chrome

#### run container
docker start fossology-test

echo "Waiting for fossology to start (can take up to 15 seconds)..."

#### wait for fossology to start
while [ `curl -s -L -I $FOSSOLOGY_ENV | grep "200 OK" | wc -l` -eq 0 ]
do
sleep 2
done

docker start selenium-test

#### Copy dummy data to docker container
docker cp ../dataFiles/TestData/ selenium-test:/home/TestData/

