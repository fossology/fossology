#!/usr/bin/php
<?php
/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

/**
 * Are there any jobs running?
 *
 * NOTE: this program depends on the UI testing infrastructure at this
 * point.
 * @return
 *
 * @version "$Id: $"
 *
 * Created on Jan. 15, 2009
 */

/* Query to use, (taken from jobs-showjobs)
 * SELECT *
        FROM jobqueue
        INNER JOIN job ON jobqueue.jq_job_fk = job.job_pk
        LEFT OUTER JOIN upload ON upload_pk = job.job_upload_fk
        LEFT JOIN jobdepends ON jobqueue.jq_pk = jobdepends.jdep_jq_fk
        WHERE (jobqueue.jq_starttime IS NULL OR jobqueue.jq_endtime IS
        NULL OR jobqueue.jq_end_bits > 1)
        ORDER BY upload_filename,upload.upload_pk,job.job_pk,jobqueue.jq_pk,jobdepends.jdep_jq_fk;
 */

require_once('TestEnvironment.php');
require_once('testClasses/db.php');

$myDB = new db();
$connection = $myDB->connect();
if(!(is_resource($connection))) {
  print "FATAL ERROR!, could not connect to the data-base\n";
  exit(1);
}

$Sql = "SELECT *
        FROM jobqueue
        INNER JOIN job ON jobqueue.jq_job_fk = job.job_pk
        LEFT OUTER JOIN upload ON upload_pk = job.job_upload_fk
        LEFT JOIN jobdepends ON jobqueue.jq_pk = jobdepends.jdep_jq_fk
        WHERE (jobqueue.jq_starttime IS NULL OR jobqueue.jq_endtime IS
        NULL OR jobqueue.jq_end_bits > 1)
        ORDER BY upload_filename,upload.upload_pk,job.job_pk,jobqueue.jq_pk," .
        "jobdepends.jdep_jq_fk;";

$results = $myDB->Query($Sql);

print "results are:\n"; print_r($results) . "\n";


?>
