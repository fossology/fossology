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

#
# Implement S3 Backup / Restore of Database and repository folder,
# with incremental backup support.
#
# Backups are stored with date tag in their filenames.
# Incremental backup files are accompanied by a tar-style diff file and
# a file listing all previous backups to be restored before.
#
# TODO: delete all temporary (small) files linked to incrementatal backup & restore


. $(dirname $0)/fo-config-common.sh

backup_db_prefix="fossology_db_"
backup_db_suffix=".pg"
backup_fs_prefix="fossology_fs_"
backup_fs_suffix=".tgz"
# holds the list of succuessive dumps incremental backups
backup_fs_suffix_inc_list=".inc-list"
backup_fs_suffix_tar_diff=".tar-diff"
backup_fs_suffix_tar_diff_initial=".initial"
repository_location="/srv/fossology" # FIXME: variabalize directory
repository_dirname="repository"
repository_path="$repository_location/$repository_dirname"
now_tag=$(date +%Y%m%d_%H%M%S)
s3_cnx_tested=false
aws_cmd='/usr/local/bin/aws'

# It looks like copying from S3 and untarring to the filesystem in the same pipe
# causes trouble and breaks the pipe.
# [un]comment below to [not] use a temporary folder.
USE_TEMP_DIRECTORY=/tmp

f_aws_cmd() {
    $aws_cmd --endpoint-url $BUCKET_HOST --region $BUCKET_REGION s3 "$@"
}

# Example command to list Bucket contents
# aws --endpoint-url $BUCKET_HOST --region $BUCKET_REGION s3 ls s3://$BUCKET_NAME/
# alias awss3='aws --endpoint-url $BUCKET_HOST --region $BUCKET_REGION s3'

f_help() {
  cat <<EOF
Usage: fo-backup-s3 [options]
  -i or --install : Install required tools (requires internet access and root privileges)
  -a or --alt-s3  : Use alternative environment variable names for S3 credentials
                    (prefixed with 'ALT_', where available)
  -l or --list    : List files in bucket
  -h or --help    : Print this help

  -b or --backup-all      : Database and incremental repository filesystem backups
  -B or --backup-all-full : Database and full repository filesystem backups
  -d or --backup-db       : Database backup
  -f or --backup-fs       : Incremental repository filesystem backup
  -F or --backup-fs-full  : Full repository filesystem backup

  -r or --restore-latest               : Restore database and repository filesystem,
                                         using most recent backups from S3 Bucket
  -e or --restore-db-latest            : Restore latest database backup
  -E or --restore-db <backup filename> : Restore specific database backup
  -g or --restore-fs-latest            : Restore latest repository filsystem backup
  -G or --restore-fs <backup filename> : Restore specific repository filsystem backup

Expects S3 connection and credentials details in following environment variables:
  AWS_SECRET_ACCESS_KEY, AWS_ACCESS_KEY_ID, BUCKET_HOST, BUCKET_NAME, BUCKET_REGION

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

# Copies all given files to S3 buckets
# Arg1. Local file to be copied
# Arg1. Optional remote file name
f_copy_to_s3() {
    f_aws_cmd cp $1 "s3://$BUCKET_NAME/$2" || f_fatal "Failed to upload '$1' to S3 storge"
}

# Copies all given files from S3 buckets
# Arg1. Remote files to be copied
# Arg2. Local target directory or file name
f_copy_from_s3() {
    f_aws_cmd cp "s3://$BUCKET_NAME/$1" $2 || f_fatal "Failed to download '$1' from S3 storge"
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
OPTS=$(getopt -o abBdeE:fFgG:hilrt --long 'alt-s3,backup-all,backup-all-full,backup-db,backup-fs,backup-fs-full,restore-latest,restore-db:,restore-db-latest,restore-fs:,--restore-fs-latest,test,install,list,help' -n "$(basename $0)" -- "$@")
[ $? -ne 0 ] && OPTS="--help"
[ $# -eq 0 ] && OPTS="--help"

ACTION_USE_ALT_ENV_VARS=false
ACTION_INSTALL=false
ACTION_LIST=false
ACTION_BACKUP_DB=false
ACTION_BACKUP_FS=false
ACTION_BACKUP_FS_INC=false
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
        [ -n "$ALT_BUCKET_REGION" ]         && BUCKET_REGION="$ALT_BUCKET_REGION"
        shift;;
      -b|--backup-all)        ACTION_BACKUP_DB=true; ACTION_BACKUP_FS=true; ACTION_BACKUP_FS_INC=true ; shift;;
      -B|--backup-all-full)   ACTION_BACKUP_DB=true; ACTION_BACKUP_FS=true; shift;;
      -d|--backup-db)         ACTION_BACKUP_DB=true; shift;;
      -f|--backup-fs)         ACTION_BACKUP_FS=true; ACTION_BACKUP_FS_INC=true ; shift;;
      -F|--backup-fs-full)    ACTION_BACKUP_FS=true; shift;;
      -r|--restore-latest)    ACTION_RESTORE_DB=true; ACTION_RESTORE_FS=true; shift;;
      -e|--restore-db-latest) ACTION_RESTORE_DB=true; shift;;
      -E|--restore-db)        ACTION_RESTORE_DB=true; ACTION_RESTORE_DB_FILE=$2 ; shift 2;;
      -g|--restore-fs-latest) ACTION_RESTORE_FS=true; shift;;
      -G|--restore-fs)        ACTION_RESTORE_FS=true; ACTION_RESTORE_FS_FILE=$2 ; shift 2;;
      -h|--help)              f_help; exit;;
      -i|--install)           ACTION_INSTALL=true; shift;;
      -l|--list)              ACTION_LIST=true; shift;;
      -t|--test)              ACTION_TEST_S3=true; shift;;
      --)                     shift; break;;
      *)                      echo "Error: option $1 not recognised, try --help"; exit 1;;
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
    f_check_file_exists "$s3_file" || f_fatal "ERROR: Database backup failed"
    f_log "SUCCESS: Database backed up to '$s3_dest'"
fi

# # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # #
# BACKUP REPOSITORY
if $ACTION_BACKUP_FS
then
    f_test_s3_connection

    if $ACTION_BACKUP_FS_INC
    then
        f_log -s "Back up repository filesystem, incremental, tag=$now_tag"
        incremental_list_latest=$(f_find_most_recent_backup $backup_fs_prefix $backup_fs_suffix_inc_list)
        [ -n "$incremental_list_latest" ] || f_fatal "ERROR: no previous incremental backup found."
        s3_file_prefix_root="$(echo $incremental_list_latest | sed 's/\.[^\.]*$//')"
        s3_file_prefix_root="$(echo $s3_file_prefix_root | sed 's/\_inc_.*$//')"
        incremental_diff="${s3_file_prefix_root}${backup_fs_suffix_tar_diff}"
        s3_file_prefix="${s3_file_prefix_root}_inc_${now_tag}"

        f_log "Download incremental data: $incremental_list_latest"
        f_copy_from_s3 "$incremental_diff" "$repository_location/"
        f_copy_from_s3 "$incremental_list_latest" "$repository_location/"
    else
        f_log -s "Back up repository filesystem, tag=$now_tag"
        s3_file_prefix="fossology_fs_${now_tag}"
        incremental_diff="${s3_file_prefix}${backup_fs_suffix_tar_diff}"
    fi
    s3_file="${s3_file_prefix}${backup_fs_suffix}"
    incremental_list="${s3_file_prefix}${backup_fs_suffix_inc_list}"

    f_log "Perform backup to $s3_file"
    tar czp -C $repository_location -g $repository_location/$incremental_diff $repository_dirname | f_aws_cmd cp - "s3://$BUCKET_NAME/$s3_file" || \
        f_fatal "Failed to backup repository to S3"
    f_copy_to_s3 "$repository_location/$incremental_diff"
    [ -f "$repository_location/$incremental_list_latest" ] && cp "$repository_location/$incremental_list_latest" "$repository_location/$incremental_list"
    echo "$s3_file" >> "$repository_location/$incremental_list"
    echo "New incremental list:"
    cat -n "$repository_location/$incremental_list"
    f_copy_to_s3 "$repository_location/$incremental_list"

    if ! $ACTION_BACKUP_FS_INC
    then
        cp -v "$repository_location/$incremental_diff" "$repository_location/$incremental_diff$backup_fs_suffix_tar_diff_initial"
        f_copy_to_s3 "$repository_location/$incremental_diff$backup_fs_suffix_tar_diff_initial"
    fi

    # FIXME: should check file size too
    f_check_file_exists "$s3_file" || f_fatal "ERROR: repository filesystem backup failed"
    f_log "SUCCESS: repository filesystem backed up to '$s3_file'"
fi

# # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # #
# RESTORE DB
if $ACTION_RESTORE_DB
then
    f_test_s3_connection
    f_log -s "Restore database: '$ACTION_RESTORE_DB_FILE'"
    if [ -n "$ACTION_RESTORE_DB_FILE" ]
    then
        f_check_file_exists $ACTION_RESTORE_DB_FILE || f_fatal "ERROR: Failed to find backup file '$ACTION_RESTORE_DB_FILE'"
    else
        f_log "Selecting most recent DB backup"
        ACTION_RESTORE_DB_FILE=$(f_find_most_recent_backup $backup_db_prefix $backup_db_suffix)
        [ - "$ACTION_RESTORE_DB_FILE" ] || f_fatal "ERROR: Failed to find most recent backup file."
    fi

    if [ -n "$USE_TEMP_DIRECTORY" ]
    then
        [ -d "$USE_TEMP_DIRECTORY" ] || f_fatal "Could not find directory '$USE_TEMP_DIRECTORY'"
        f_log "Download backup to '$USE_TEMP_DIRECTORY'"
        f_copy_from_s3 "$ACTION_RESTORE_DB_FILE" "$USE_TEMP_DIRECTORY/"

        f_log "Restore database"
        cat $USE_TEMP_DIRECTORY/$ACTION_RESTORE_DB_FILE | pg_restore -Fc -c -C -d $FOSSOLOGY_DB_NAME || \
            f_fatal "ERROR: Database restore failed"
        rm -v $USE_TEMP_DIRECTORY/$ACTION_RESTORE_DB_FILE
        f_log "SUCCESS: Database restored from local copy"
    else
        s3_source="s3://$BUCKET_NAME/$ACTION_RESTORE_DB_FILE"
        # TODO: test export AWS_CLIENT_TIMEOUT=900000 (120000ms is the default)
        f_log "Download + Restore database"
        f_aws_cmd cp $s3_source - | pg_restore -Fc -d $FOSSOLOGY_DB_NAME || \
            f_fatal "ERROR: Database restore failed"
        f_log "SUCCESS: Database restored successfuly"
    fi
fi

# # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # #
# RESTORE REPOSITORY

f_restore_fs() {
    local tar_file="$1"

    if [ -n "$USE_TEMP_DIRECTORY" ]
    then
        [ -d "$USE_TEMP_DIRECTORY" ] || f_fatal "Could not find directory '$USE_TEMP_DIRECTORY'"
        f_copy_from_s3 "$tar_file" "$USE_TEMP_DIRECTORY"

        f_log "File $tar_file copied to $USE_TEMP_DIRECTORY"
        tar xzf $USE_TEMP_DIRECTORY/$tar_file -C "$repository_location" || f_fatal "ERROR: Failed to restore $tar_file"
        f_log "SUCCESS: Restored $tar_file"
        rm -v $USE_TEMP_DIRECTORY/$tar_file
    else
        # TODO: test export AWS_CLIENT_TIMEOUT=900000 (120000ms  is the default)
        s3_source="s3://$BUCKET_NAME/$tar_file"
        f_aws_cmd cp $s3_source - | tar xz -C "$repository_location" || f_fatal "ERROR: Failed to restore $tar_file"
        f_log "SUCCESS: Restored $tar_file"
    fi
}

if $ACTION_RESTORE_FS
then
    f_test_s3_connection
    f_log -s "Restore repository fileystem"
    if [ -n "$ACTION_RESTORE_FS_FILE" ]
    then
        f_check_file_exists $ACTION_RESTORE_FS_FILE || \
            f_fatal "ERROR: Failed to find backup file '$ACTION_RESTORE_FS_FILE'"
    else
        f_log "Selecting most recent FS backup"
        ACTION_RESTORE_FS_FILE=$(f_find_most_recent_backup $backup_fs_prefix $backup_fs_suffix)
        [ -n "$ACTION_RESTORE_FS_FILE" ] || f_fatal "ERROR: Failed to find most recent backup file."
    fi

    backup_file_root="$(echo $ACTION_RESTORE_FS_FILE | sed 's/\.[^\.]*$//')"
    backup_file_inc=$backup_file_root$backup_fs_suffix_inc_list
    f_log "Using file: $ACTION_RESTORE_FS_FILE, root: $backup_file_root"
    [ -d "$repository_location" ] || f_fatal "Cannot find directory: $repository_location"

    # Remove existing repository filesystem
    # With NFS persistent volumes, deletion may end up in error with lingering NFS lock files
    # So, 1. move folder before deleting and 2. only log deletion errors as warnings.
    f_log "Moving and deleting existing repository directory '$repository_path'"
    repository_path_old="${repository_path}_$(date +%Y%m%d_%H%M%S)"
    if [ -d $repository_path ]
    then
        mv -v $repository_path $repository_path_old || f_fatal
        rm -rf "$repository_path_old" || \
            f_log "WARNING: existing repository could not be completely deleted, files left in '$repository_path_old'"
    fi

    if f_check_file_exists "$backup_file_inc"
    then
        f_log "Process incremental restore: '$backup_file_inc'"
        f_copy_from_s3 "$backup_file_inc" "$repository_path/"
        cat -n "$repository_path/$backup_file_inc"
        while read f
        do
            f_restore_fs "$f"
        done < "$repository_path/$backup_file_inc"
    else
        f_log "Process non-incremental restore"
        f_restore_fs "${ACTION_RESTORE_FS_FILE}"
    fi
fi
