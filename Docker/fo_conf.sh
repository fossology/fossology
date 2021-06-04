#!/bin/bash
# FOSSology fo_conf script
# SPDX-FileCopyrightText: 2021 Omar AbdelSamea <omarmohamed168@gmail.com>
# SPDX-License-Identifier: GPL-2.0

# used to add confing to FOSSology etcd pod inside kubernetes cluster
db_host="${FOSSOLOGY_DB_HOST:-db}"
db_name="${FOSSOLOGY_DB_NAME:-fossology}"
db_user="${FOSSOLOGY_DB_USER:-fossy}"
db_password="${FOSSOLOGY_DB_PASSWORD:-fossy}"

if [[ "$1" == "scheduler" ]]; then
    while read line; do 
        if [[ $line =~ ^"["(.+)"]"$ ]]; then 
            arrname=${BASH_REMATCH[1]}
        elif [[ $line =~ ^([_[:alpha:]][_[:alnum:]]*)" = "(.*) ]]; then 
            declare ${BASH_REMATCH[1]}="${BASH_REMATCH[2]}"
            if [[ $arrname != "HOSTS" ]]; then 
                eval "curl http://192.168.49.2:30079/v2/keys/fossology/${arrname,,}/${BASH_REMATCH[1],,} -XPUT -d value=${BASH_REMATCH[2]}"
            fi
        fi
    done < /etc/fossology/fossology.conf        
elif [[ "$1" == "db" ]]; then
    curl http://192.168.49.2:30079/v2/keys/db/ -XPUT -d value="dbname=${db_name} host=${db_host} user=${db_user} password=${db_password} client_encoding=utf8"
elif [[ "$1" == "agent" ]]; then
    curl -s http://etcd:2379/v2/keys/fossology/hosts/$2 -XPUT -d value="$2.agent-svc /etc/fossology 10 $2"
    while read line; do 
        if [[ $line =~ ^"["(.+)"]"$ ]]; then 
            arrname=${BASH_REMATCH[1]}
        elif [[ $line =~ ^([_[:alpha:]][_[:alnum:]]*)" = "(.*) ]]; then 
            curl -s http://etcd:2379/v2/keys/agents/$2/${BASH_REMATCH[1]} -XPUT --data-urlencode value="${BASH_REMATCH[2]}"
            echo "${BASH_REMATCH[1]}=${BASH_REMATCH[2]} conf for $2 is Added"
        elif [[ $line =~ ^([_[:alpha:]][_[:alnum:]]*)"[]"" = "(.*) ]]; then 
            IFS=,
            special=(${BASH_REMATCH[2]})
            for (( i=0; i<${#special[@]}; i++ )) do
                curl -s http://etcd:2379/v2/keys/agents/$2/${BASH_REMATCH[1]}/$i -XPUT -d value="${special[$i]}"
                echo "${BASH_REMATCH[1]}=${special[$i]} conf for $2 is Added"
            done
        fi
    done < /usr/share/fossology/$2/$2.conf            
fi