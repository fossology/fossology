#!/usr/bin/php

<?php
/*
 mk_fmdirs.php
 SPDX-FileCopyrightText: © 2007 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * mk_fmdirs: make the freshmeat directories.
 *
 * @package loadarchive
 * @author mark.donohoe@hp.com
 * @version 0.1
 *
 */

// NOTE: This code is not really used anymore !!!!!!!!!!!!!!!!!!!!!!

// For now, assume previous process has determined if the archive has changed
// and just calls this routine to do the load.  That is, this routine is
// not expected to determine if the archive has changed.

//issues:
// what to do if all the data is not present.  For example, osrb projects
// may not have a version, rank, or other data that is there for projects
// from freshmeat.  Talk with Bob, use some sort of 'default' if there
// is no data.

# 1. process parameter(s) and sanity check.
# 2. connect to db
# 3. If folder doesn't exist, create it.
#    is it possible to make this recursive and do subfolders as well?
# 4. After all needed folders are created,
# things are a bit fuzzy below....
# 5. create upload record.
# 6. unpack
# 7. schedule nomos...
# What else?
/*
 * general process is: createfolders, then use createfoldercontents to
 * associate folders with each other....
 */

require_once("FIXMETOBERELATIVE/pathinclude.php");
/* the items below no longer exist, my this code is old.... */
require_once("$PHPDIR/webcommon.h.php");
require_once("$PHPDIR/jobs.h.php");
require_once("$PHPDIR/db_postgres.h.php");

// if you do it yourself....use the lib...
#$_pg_conn = pg_connect(str_replace(";", " ",
#	   file_get_contents("{$SYSCONFDIR}/{$PROJECT}/Db.conf")));

$path = "{$SYSCONFDIR}/$PROJECT}/Db.conf";
$alpha_buckets = array('a-c',
                       'd-f',
                       'g-i',
                       'j-l',
                       'm-o',
                       'p-r',
                       's-u',
                       'v-z'
                       );
db_init($path);

if (!$_pg_conn) {
  echo "ERROR: could not connect to DB\n";
  exit(1);
}

/*
 * Steps are:
 * 1. create top folder
 * using value returned from cratefolder,
 * 2. use that in a loop to create the other folders.
 * 3. Other folders are created with
 * createrfolder, and then associated with parent folder with
 * createfoldercontents.
 */


$sql = 'select users.root_folder_fk from users';

// who is my parent?
$pfolder4user = db_query1($sql);

// Create top level folder 'Freshmeat'
$f_pk = createfolder($pfolder4user, 'Freshmeat',
                         "Top folder for FM Archives");

$sql_fm =
  "select folder_pk, folder_name from folder where folder_name='Freshmeat'";
$fm_pk = db_query1($sql_fm);

// Create the alpha bucket folders and associate them with the
// Freshmeat folder.
for ($num=0; $num < count($alpha_buckets); $num++){
#echo "arraychunks: {$alpha_buckets[$num]}\n";

$folder_pk = createfolder(
  $fm_pk, "{$alpha_buckets[$num]}", "Holds Freshmeat archives");

$fold_cont_pk = createfoldercontents($fm_pk, $folder_pk, 1);
}
