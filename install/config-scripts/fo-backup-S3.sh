#!/bin/sh
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# version 2 as published by the Free Software Foundation.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
#
# SPDX-FileCopyrightText: 2021 Orange
# SPDX-License-Identifier: GPL-2.0-only
#
# Author: Nicolas Toussaint <nicolas1.toussaint@orange.com>

. $(dirname $0)/fo-config-common.sh

backup_db_prefix="fossology_db_"
backup_db_suffix=".pg"
backup_fs_prefix="fossology_fs_"
backup_fs_suffix=".tgz"
repository_location="/srv/fossology" # FIXME: variabalize directory
repository_dirname="repository"
repository_path="$repository_location/$repository_dirname"
now_tag=$(date +%Y%m%d_%H%M%S)
error_count=0
s3_cnx_tested=false
aws_cmd='/usr/local/bin/aws'

# It looks like copying from S3 and untarring to the filesystem in the same pipe
# causes trouble and breaks the pipe.
# [un]comment below to [not] use a temporary folder.
USE_TEMP_DIRECTORY=/tmp

f_aws_cmd() {
    $aws_cmd --endpoint-url $BUCKET_HOST s3 "$@"
}

# Example command to list Bucket contents
# aws --endpoint-url $BUCKET_HOST s3 ls s3://$BUCKET_NAME/

# alias awss3='aws --endpoint-url $BUCKET_HOST s3'
# alias awslsb='aws --endpoint-url $BUCKET_HOST s3 ls s3://$BUCKET_NAME'
# alias awss3api='aws --endpoint-url $BUCKET_HOST s3api'

f_help() {
  cat <<EOF
Usage: fo-backup-s3 [options]
  -i or --install    : Install required tools (requires internet access and root privileges)
  -a or --alt-s3     : Use alternative environment variable names for S3 credentials
                       (prefixed with 'ALT_', where available)
  -l or --list       : list files in bucket
  -b or --backup-all :
  -d or --backup-db  :
  -f or --backup-fs  :
  -r or --restore-latest :
  -e or --restore-db <backup filename> :
  -g or --restore-fs <backup filename> :
  -h or --help : this help

Expects S3 connection and credentials details in following environment variables:
  AWS_SECRET_ACCESS_KEY, AWS_ACCESS_KEY_ID, BUCKET_HOST, BUCKET_NAME

EOF
}

# Finds most recent backup from S3 storage
# Arg1. Backup file prefix
# Arg2. Backup file suffix
f_find_most_recent_backup() {
    f_aws_cmd ls s3://$BUCKET_NAME | awk "/ $1.*$2$/{print \$4}" | sort | tail -n 1
}

# Returns true if file was found in Bucket
# Arg1. File name
f_check_file_exists() {
    f_aws_cmd ls s3://$BUCKET_NAME  | awk "{print \$4}" | grep -q "$1"
}

f_test_s3_connection() {
    $s3_cnx_tested && return 0
    echo "Testing connection to S3 storage"
    for v in "$AWS_SECRET_ACCESS_KEY" "$AWS_ACCESS_KEY_ID" "$BUCKET_HOST" "$BUCKET_NAME"
    do
        [ -n "$v" ] || f_fatal "one or more environment variable is missing"
    done
    f_aws_cmd ls s3://$BUCKET_NAME --human-readable --summarize | tail -n 2 | sed 's/ *//' \
        || f_fatal "S3 storage is unreachable"
    s3_cnx_tested=true
}

## Options parsing and setup
# parse options
OPTS=$(getopt -o abde:fg:hilrt --long 'alt-s3,backup-all,backup-db,backup-fs,restore-latest,restore-db:,retore-fs:,test,install,list,help' -n "$(basename $0)" -- "$@")
[ $? -ne 0 ] && OPTS="--help"
[ $# -eq 0 ] && OPTS="--help"

ACTION_USE_ALT_ENV_VARS=false
ACTION_INSTALL=false
ACTION_LIST=false
ACTION_BACKUP_DB=false
ACTION_BACKUP_FS=false
ACTION_RESTORE_DB=false
ACTION_RESTORE_DB_FILE=""
ACTION_RESTORE_FS=false
ACTION_RESTORE_FS_FILE=""
ACTION_TEST_S3=false

eval set -- "$OPTS"
while true; do
   case "$1" in
      -a|--alt-s3)
        ACTION_USE_ALT_ENV_VARS=true
        [ -n "$ALT_AWS_SECRET_ACCESS_KEY" ] && AWS_SECRET_ACCESS_KEY="$ALT_AWS_SECRET_ACCESS_KEY"
        [ -n "$ALT_AWS_ACCESS_KEY_ID" ]     && AWS_ACCESS_KEY_ID="$ALT_AWS_ACCESS_KEY_ID"
        [ -n "$ALT_BUCKET_HOST" ]           && BUCKET_HOST="$ALT_BUCKET_HOST"
        [ -n "$ALT_BUCKET_NAME" ]           && BUCKET_NAME="$ALT_BUCKET_NAME"
        shift;;
      -b|--backup-all)     ACTION_BACKUP_DB=true; ACTION_BACKUP_FS=true; shift;;
      -d|--backup-db)      ACTION_BACKUP_DB=true; shift;;
      -f|--backup-fs)      ACTION_BACKUP_FS=true; shift;;
      -r|--restore-latest) ACTION_RESTORE_DB=true; ACTION_RESTORE_FS=true; shift;;
      -e|--restore-db)     ACTION_RESTORE_DB=true; ACTION_RESTORE_DB_FILE=$2 ; shift 2;;
      -g|--restore-fs)     ACTION_RESTORE_FS=true; ACTION_RESTORE_FS_FILE=$2 ; shift 2;;
      -h|--help)           f_help; exit;;
      -i|--install)        ACTION_INSTALL=true; shift;;
      -l|--list)           ACTION_LIST=true; shift;;
      -t|--test)           ACTION_TEST_S3=true; shift;;
      --)                  shift; break;;
      *)                   echo "Error: option $1 not recognised, try --help"; exit 1;;
   esac
done

if $ACTION_INSTALL
then
    temp_dir=$(mktemp -d) || f_fatal "Could not create temp directory"
    awscli_url="https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip"
    awscli_file="awscliv2.zip"

    f_log -s "Installing AWS Client on system"

    cd $temp_dir || f_fatal
    curl "$awscli_url" -o "$awscli_file" || f_fatal "Failed downloading from '$awscli_url'"
    unzip "$awscli_file" || f_fatal

    [ $(id -u) -eq 0 ] || f_fatal "awscli Installation requires Root priviledges"
    [ -e "/usr/local/aws-cli/v2/current" ] & update_option="--update"
    ./aws/install $update_option || f_fatal "Failed installing awscli"
    $aws_cmd --version || f_fatal "Error testing command: $aws_cmd"
    f_log "Tool 'awscli' was succesfuly installed"
fi

export PGUSER=$FOSSOLOGY_DB_USER
export PGPASSWORD=$FOSSOLOGY_DB_PASSWORD
export PGHOST=$FOSSOLOGY_DB_HOST
export PGDATABASE=$FOSSOLOGY_DB_NAME
AWS_SECRET_ACCESS_KEY_EMPTY="<empty>"
[ -n "$AWS_SECRET_ACCESS_KEY" ] && AWS_SECRET_ACCESS_KEY_EMPTY='<not empty>'
PGPASSWORD_EMPTY="<empty>"
[ -n "$PGPASSWORD" ] && PGPASSWORD_EMPTY='<not empty>'

cat <<EOS

S3 Backup configuration
USE_ALT_ENV_VARS      : $ACTION_USE_ALT_ENV_VARS
AWS_ACCESS_KEY_ID     : $AWS_ACCESS_KEY_ID
AWS_SECRET_ACCESS_KEY : $AWS_SECRET_ACCESS_KEY_EMPTY
BUCKET_HOST           : $BUCKET_HOST
BUCKET_NAME           : $BUCKET_NAME

Database
PGUSER     : $FOSSOLOGY_DB_USER
PGPASSWORD : $PGPASSWORD_EMPTY
PGHOST     : $FOSSOLOGY_DB_HOST
PGDATABASE : $FOSSOLOGY_DB_NAME

EOS


if $ACTION_TEST_S3
then
    f_test_s3_connection
fi

if $ACTION_LIST
then
    f_aws_cmd ls s3://$BUCKET_NAME --human-readable --summarize
fi

# # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # #
# BACKUP DB
if $ACTION_BACKUP_DB
then
    f_test_s3_connection
    f_log -s "Back up database, tag=$now_tag"
    s3_file="fossology_db_${now_tag}${backup_db_suffix}"
    s3_dest="s3://$BUCKET_NAME/$s3_file"

    f_log -l "Dest file: $s3_file"

    pg_dump -Fc -d $FOSSOLOGY_DB_NAME | f_aws_cmd cp - $s3_dest
    # FIXME: should check file size too
    if f_check_file_exists "$s3_file"
    then
        f_log "SUCCESS: Database backed up to '$s3_dest'"
    else
        f_log "ERROR: Database backup failed"
        error_count=$((error_count+1))
    fi
fi

# # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # #
# BACKUP REPOSITORY
if $ACTION_BACKUP_FS
then
    f_test_s3_connection
    f_log -s "Back up repository filesystem, tag=$now_tag"
    s3_file="fossology_fs_${now_tag}${backup_fs_suffix}"
    s3_dest="s3://$BUCKET_NAME/$s3_file"

    f_log -l "Dest file: $s3_file"

    tar cz -C $repository_location $repository_dirname | f_aws_cmd cp - $s3_dest
    # FIXME: should check file size too
    if f_check_file_exists "$s3_file"
    then
        f_log "SUCCESS: repository filesystem backed up to '$s3_dest'"
    else
        f_log "ERROR: repository filesystem backup failed"
        error_count=$((error_count+1))
    fi
fi

# # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # #
# RESTORE DB
if $ACTION_RESTORE_DB
then
    f_test_s3_connection
    f_log -s "Restore database: '$ACTION_RESTORE_DB_FILE'"
    if [ -n "$ACTION_RESTORE_DB_FILE" ]
    then
        if ! f_check_file_exists $ACTION_RESTORE_DB_FILE
        then
            f_log "ERROR: Failed to find backup file '$ACTION_RESTORE_DB_FILE'"
            ACTION_RESTORE_DB_FILE=""
        fi
    else
        f_log "Selecting most recent DB backup"
        ACTION_RESTORE_DB_FILE=$(f_find_most_recent_backup $backup_db_prefix $backup_db_suffix)
        if [ -z "$ACTION_RESTORE_DB_FILE" ]
        then
            f_log "ERROR: Failed to find most recent backup file."
        fi
    fi

    if [ -n "$ACTION_RESTORE_DB_FILE" ]
    then
        s3_source="s3://$BUCKET_NAME/$ACTION_RESTORE_DB_FILE"
        if [ -n "$USE_TEMP_DIRECTORY" ]
        then
            [ -d "$USE_TEMP_DIRECTORY" ] || f_fatal "Could not find directory '$USE_TEMP_DIRECTORY'"
            f_log "Download backup to '$USE_TEMP_DIRECTORY'"
            if f_aws_cmd cp $s3_source $USE_TEMP_DIRECTORY/$ACTION_RESTORE_DB_FILE
            then
                f_log "Restore database"
                if cat $USE_TEMP_DIRECTORY/$ACTION_RESTORE_DB_FILE | pg_restore -Fc -c -C -d $FOSSOLOGY_DB_NAME
                then
                    rm -v $USE_TEMP_DIRECTORY/$ACTION_RESTORE_DB_FILE
                    f_log "Successfuly restored database from local copy"
                else
                    error_count=$((error_count+1))
                    f_log "ERROR: Database restore failed"
                fi
            else
                error_count=$((error_count+1))
                f_log "ERROR: Database restore failed"
            fi
        else
            # TODO: test export AWS_CLIENT_TIMEOUT=900000 (120000ms  is the default)
            f_log "Download + Restore database"
            if f_aws_cmd cp $s3_source - | pg_restore -Fc -d $FOSSOLOGY_DB_NAME
            then
                f_log "SUCCESS: Database restored successfuly"
            else
                error_count=$((error_count+1))
                f_log "ERROR: Database restore failed"
            fi
        fi
    else
        error_count=$((error_count+1))
    fi
fi

# # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # #
#RESTORE REPOSITORY
if $ACTION_RESTORE_FS
then
    f_test_s3_connection
    f_log -s "Restore repository fileystem: '$ACTION_RESTORE_FS_FILE'"
    if [ -n "$ACTION_RESTORE_FS_FILE" ]
    then
        if ! f_check_file_exists $ACTION_RESTORE_FS_FILE
        then
            f_log "ERROR: Failed to find backup file '$ACTION_RESTORE_FS_FILE'"
            ACTION_RESTORE_FS_FILE=""
        fi
    else
        f_log "Selecting most recent FS backup"
        ACTION_RESTORE_FS_FILE=$(f_find_most_recent_backup $backup_fs_prefix $backup_fs_suffix)
        if [ -z "$ACTION_RESTORE_FS_FILE" ]
        then
            f_log "ERROR: Failed to find most recent backup file."
        fi
    fi

    if [ -n "$ACTION_RESTORE_FS_FILE" ]
    then
        s3_source="s3://$BUCKET_NAME/$ACTION_RESTORE_FS_FILE"
        f_log "Using file: $ACTION_RESTORE_FS_FILE"
        [ -d "$repository_location" ] || f_fatal "Cannot find directory: $repository_location"
        if [ -d "$repository_path" ]
        then
            # With NFS persistent volumes, deletion may end up in error with lingering NFS lock files
            # So, 1. move folder before deleting and 2. only log deletion errors as warnings.
            f_log "Moving and deleting existing repository directory '$repository_path'"
            repository_path_old="${repository_path}_$(date +%Y%m%d_%H%M%S)"
            mv -v $repository_path $repository_path_old || f_fatal
            rm -rf "$repository_path_old" || \
                f_log "WARNING: existing repository could not be completely deleted, files left in '$repository_path_old'"
        fi

        if [ -n "$USE_TEMP_DIRECTORY" ]
        then
            [ -d "$USE_TEMP_DIRECTORY" ] || f_fatal "Could not find directory '$USE_TEMP_DIRECTORY'"
            if f_aws_cmd cp $s3_source $USE_TEMP_DIRECTORY/$ACTION_RESTORE_FS_FILE
            then
                f_log "File $ACTION_RESTORE_FS_FILE copied to $USE_TEMP_DIRECTORY"
                if tar xz -C "$repository_location" $USE_TEMP_DIRECTORY/$ACTION_RESTORE_FS_FILE
                then
                    f_log "Successfuly untarred repository to '$repository_location'"
                    rm -v $USE_TEMP_DIRECTORY/$ACTION_RESTORE_FS_FILE
                else
                    error_count=$((error_count+1))
                    f_log "ERROR: Repository filesystem restore failed"
                fi
            else
                error_count=$((error_count+1))
                f_log "ERROR: Repository filesystem restore failed"
            fi
        else
            # TODO: test export AWS_CLIENT_TIMEOUT=900000 (120000ms  is the default)
            if f_aws_cmd cp $s3_source - | tar xz -C "$repository_location"
            then
                f_log "SUCCESS: Repository filesystem restored successfuly"
            else
                error_count=$((error_count+1))
                f_log "ERROR: Repository filesystem restore failed"
            fi
        fi
    else
        error_count=$((error_count+1))
    fi
fi

# # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # #
# EXIT
if [ $error_count -ne 0 ]
then
    f_log -s "ERROR: One or more operations failed"
    exit $error_count
fi
