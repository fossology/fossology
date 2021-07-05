#!/bin/sh

pod_prefix=$1
shift

pod=$(./oc-get-pod.sh $pod_prefix)
oc rsh --shell=/bin/bash pod/$pod "$@"

