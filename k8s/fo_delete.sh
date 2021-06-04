#!/usr/bin/sh
# FOSSology fo_conf script
# SPDX-FileCopyrightText: 2021 Omar AbdelSamea <omarmohamed168@gmail.com>
# SPDX-License-Identifier: GPL-2.0

# used to delete agent from kubernetes cluster

if [ $# -eq 0 ]
then
        echo "Missing options!"
        echo "(run $0 -h for help)"
        echo ""
        exit 0
fi

ECHO="false"
IP="192.168.49.2" # minikube default 
IP=$(kubectl get nodes -o jsonpath="{.items[*].status.addresses[?(@.type=='InternalIP')].address}")
while getopts "ha:" OPTION; do
        case $OPTION in

            h)
                echo "Usage:"
                echo "$0 -h "
                echo ""
                echo "   -h     help (this output)"
                echo "   -a     delete agent deployment"
                exit 0
                ;;
            a)
                AGENT="${OPTARG}"
                ;;
        esac
done

if [ -z $AGENT ]
then
    echo "Please add agent name after -a option";
else
kubectl delete deploy $AGENT
curl http://$IP:30079/v2/keys/agents/$AGENT?recursive=true -XDELETE    
kubectl exec deploy/scheduler -- bash -c \
  "/usr/share/fossology/scheduler/agent/fo_cli --host=scheduler \
  --port=24693 --reload" || echo "Scheduler is initlaizing or not running"
fi