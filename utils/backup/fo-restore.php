#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2009 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/**************************************************************
 fo-restore

 Implement restore process.

 @return 0 for success, 1 for failure.
 *************************************************************/
/* Have to set this or else plugins will not load. */
$GlobalReady = 1;
/* Load all code */
require_once (dirname(__FILE__) . '/../php/pathinclude.php');
global $WEBDIR;
$UI_CLI = 1; /* this is a command-line program */
require_once ("$WEBDIR/common/common.php");
cli_Init();
error_reporting(E_NOTICE & E_STRICT);


/*********************************************************
  AddReunpackjob(): Given an uploadpk, add a reunpack job.
  Return $jobpk if Success, or return fail string
*********************************************************/

function AddReunpackjob ($uploadpk,$Depends=NULL,$priority=0)
{
  global $DB;
  if (empty($DB)) {
      return;
  }
  $Job_name = str_replace("'", "''", "unpack");

  $SQLInsert = "INSERT INTO job
         (job_queued,job_priority,job_name,job_upload_fk) VALUES
          (now(),'$priority','$Job_name','$uploadpk');";

  $SQLcheck = "SELECT job_pk FROM job WHERE job_upload_fk = '$uploadpk' AND job_name = '$Job_name' AND job_user_fk is NULL;";
  $Results = $DB->Action($SQLcheck);
  if (empty($Results)) {
      $DB->Action($SQLInsert);
      $SQLcheck = "SELECT job_pk FROM job WHERE job_upload_fk = '$uploadpk' AND job_name = '$Job_name' AND job_user_fk is NULL;";
      $Results = $DB->Action($SQLcheck);
  }
  $jobpk = $Results[0]['job_pk'];

  if (empty($jobpk) || ($jobpk < 0)) { return("Failed to insert job record! $SQLInsert"); }
  if (!empty($Depends) && !is_array($Depends)) { $Depends = array($Depends); }

  /* job "unpack" has jobqueue item "unpack" */
  $jqargs = "SELECT pfile.pfile_sha1 || '.' || pfile.pfile_md5 || '.' || pfile.pfile_size AS pfile,
            upload_pk, pfile_fk
            FROM upload
            INNER JOIN pfile ON upload.pfile_fk = pfile.pfile_pk
            WHERE upload.upload_pk = '$uploadpk';";
  $jobqueuepk = JobQueueAdd($jobpk,"unpack",$jqargs,"no","pfile",$Depends,1);
  if (empty($jobqueuepk)) { return("Failed to insert item into job queue"); }

  return ($jobqueuepk);
}/* AddReunpackjob() */


global $DB;
if (empty($DB)) {
  return;
}
$SQL = "SELECT job_pk,jq_pk,job_upload_fk  FROM jobqueue
        INNER JOIN job ON jq_job_fk = job_pk
        WHERE jq_end_bits = 0 AND jq_starttime IS NOT NULL AND jq_endtime IS NULL AND job_name NOT IN('unpack','wget','fo_notify')
ORDER BY job_Pk;";
$Results = $DB->Action($SQL);
$i = 0;
while(!empty($Results[$i]['job_pk'])) {
  $jq_parent = AddReunpackjob($Results[$i]['job_upload_fk']);
  print $jq_parent;
  $jq_child = $Results[$i]['jq_pk'];
  JobQueueAddDependency($jq_child,$jq_parent);
  $i++;
}
return (0);
