#!/bin/bash
docker build -t fossology/test . && docker run -d fossology/test && docker ps | grep -q fossology/test
exit 0
