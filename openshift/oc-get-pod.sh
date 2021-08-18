#!/bin/sh

pod=$1
shift

get_pod() {
    pod=$1
    oc get pod -o "custom-columns=POD:.metadata.name,STATUS:status.phase" | \
        sed -n "/^$pod/s/  *.*$//p" | grep -v 'deploy$'
}

pod_name=$(get_pod $pod)
echo "Waiting for  '$pod_name'" >&2
oc wait  pod/$pod_name --for=condition=ready --timeout=120s >&2

echo $pod_name
