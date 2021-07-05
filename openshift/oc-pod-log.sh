#!/bin/sh -x

pod_prefix=$1
shift

pod=$(./oc-get-pod.sh $pod_prefix)
oc logs -f $pod "$@"

