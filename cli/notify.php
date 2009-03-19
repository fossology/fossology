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
 *  fossjobstat
 *
 * Report the status of a fossology job.  Either email analysis results to the
 * user or report the job status on the command line.
 *
 * @return 0 for success, 1 for failure.
 */

/* Have to set this or else plugins will not load. */
$GlobalReady = 1;

/* Load all code */
require_once(dirname(__FILE__) . '/../share/fossology/php/pathinclude.php');
global $WEBDIR;
$UI_CLI = 1; /* this is a command-line program */
require_once("$WEBDIR/common/common.php");
cli_Init();

global $Plugins;

error_reporting(E_NOTICE & E_STRICT);

$Usage = "Usage: " . basename($argv[0]) . " [options]
  Options:
    -h             = this help message
    -e <address>   = optional email address, e.g. nobody@localhost
    -j string      = optional, Name of the job to include in the email
    -n string      = optional, user name to address email to, this is not the email
                     address.
    -u <upload_id> = Upload ID. (required)

    If not -e option is supplied, status is printed to standard out.
  ";

/*
 * NOTE: when called with both -e and -n, use the -e value as the ToEmail, and
 * use the UserName for the Dear UserName,.....
 */

function printMsg($Message) {
  if (empty($Message)) {
    return;
  }
  print wordwrap($Message,72) . "\n";
}

/* Load command-line options */
global $DB;

$JobName   = "";
$JobStatus = "";

/* Process some of the parameters */
$options = getopt("he:n:j:u:");
if (empty($options)) {
  print $Usage;
  exit(1);
}

if (array_key_exists("h",$options)) {
  print $Usage;
  exit(0);
}
//print "  NOT: parameters passed in are:\n"; print_r($options) . "\n";

/*
 * Note the logic below makes -e a  required parameter... not what we want for
 * interactive use.  Think about this.
 */
/* no -e implies interactive, just print to stdout */
$Interactive = FALSE;
/* Default TO: is the users email */
if (array_key_exists("e",$options)) {
  $ToEmail = trim($options['e']);
  if (empty($ToEmail)) {
    print $Usage;
    exit(1);
  }
}
else {
  $Interactive = TRUE;
}

/* Optional Salutation */
if (array_key_exists("n",$options)) {
  $UserName = $options['n'];
  if (empty($UserName)) {
    print $Usage;
    exit(1);
  }
}

if (array_key_exists("u",$options)) {
  $upload_id = $options['u'];
  if (empty($upload_id)) {
    print $Usage;
    exit(1);
  }
}
if (empty($UserName)){
  $UserName = $ToEmail;
}
/* gather the data from the db:
 * - User name
 * - Job name
 * - job status
 */
if(empty($UserName)) {
  /* no User name passed in, get the user name for this upload */
  $Sql = "select job_submitter, user_pk, user_name from job, users " .
           "where job_upload_fk = $upload_id and user_pk = job_submitter limit 1;";
  $Results = $DB->Action($Sql);
  if (!empty($Results[0]['user_name'])) {
    $UserName = $Results[0]['user_name'];
  }
}
/********** Set Message Preamble ******************************************/
/* if still no UserName, then use email address as the name */
if (empty($UserName)){
  //print "  FJS: check UserName is EMPTY!\n";
  $UserName = $ToEmail;
}
$Preamble = "Dear $UserName,\n" .
            "Do not reply to this message.  " .
            "This is an automattically generated message by the FOSSology system.\n\n";

/* Optional Job Name */
if (array_key_exists("j",$options)) {
  $JobName = $option['j'];
}
/* No job name supplied, go get it*/
if(empty($JobName)) {
  /* Get Upload Filename, use that as the 'job' name which is what the jobs display
   * screen does. */
  $Sql = "SELECT upload_filename FROM upload WHERE upload_pk = $upload_id;";
  $Results = $DB->Action($Sql);
  $Row = $Results[0];
  if (!empty($Results[0]['upload_filename'])) {
    $JobName = $Row['upload_filename'];
  }
  else {
    print "ERROR: $upload_id is not a valid upload Id. See fossjobs(1)\n";
    exit(1);
  }
}
/* get job status */
$summary = JobListSummary($upload_id);
//print "  DEBUG: summary for upload $upload_id is:\n"; print_r($summary) . "\n";

/* Job aborted */
if ($summary['total'] == 0 &&
$summary['completed'] == 0 &&
$summary['active'] == 0 &&
$summary['failed'] == 0 ) {
  $MessagePart = "No results, your job $JobName was killed";
  $Message = $Preamble . $MessagePart;
  if ($Interactive) {
    printMsg($MessagePart);
    exit(0);
  }
}
/* NOTE: if run as an agent we assume we are the last job in the jobqueue, so
 * when we check status, completed should be 1 less than the total.  If this
 * check is not made, then the job is always reported as still active...
 */
/* Job is done, OK status */
$Done = FALSE;

if ($summary['total'] == $summary['completed']) {
  if ($summary['failed'] == 0) {
    $Done = TRUE;
  }
}
elseif ($summary['total'] == $summary['completed']+1) {
  if ($summary['failed'] == 0) {
    $Done = TRUE;
  }
}

if ($Done) {
  $JobStatus = "completed with no errors";
  $MessagePart = "Your requested FOSSology results are ready. " .
                 "Your job $JobName has $JobStatus.";
  $Message = $Preamble . $MessagePart;
  if ($Interactive) {
    printMsg($MessagePart);
  }
}

/* Job is still active */
elseif ($summary['active'] > 0) {
  $JobStatus = "is still active.";
  $MessagePart = "Your requested FOSSology results are  not ready. " .
                 "Your job $JobName $JobStatus";
  $Message = $Preamble . $MessagePart;
  if ($Interactive) {
    printMsg($MessagePart);
  }
}
/* called as agent, or -e passed in, send mail. */
if (!$Interactive) {
  /* use php mail to queue it up */
  //print "  NOT: sending email to:$ToEmail with message:\n$Message\n";
  $Sender = "The FOSSology Application";
  $From = "root@localhost";
  $Recipient = $ToEmail;
  $Mail_body = wordwrap($Message,72);
  $Subject = "FOSSology Results for $JobName";
  $Header = "From: " . $Sender . " <" . $From . ">\r\n";
  if($rtn = mail($Recipient, $Subject, $Mail_body, $Header)){
    print "Mail has been queued by notify\n";
    exit(0);
  }
  else {
    print "Warning: Mail was NOT queued by notify\n";
    exit(1);
  }
}
exit(0);
?>
