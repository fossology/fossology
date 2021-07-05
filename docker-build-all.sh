#!/bin/sh

targets="db scheduler web"
[ $# -gt 0 ] && targets="$*"
for t in $targets
do
    echo "========================================================"
    echo "============ TARGET=$t"
    docker build . --target fossology-$t -t fossology-${t}:latest
done

