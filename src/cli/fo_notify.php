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
 *  fo-notify
 *
 * \brief Report the status of a fossology job.
 *
 * Either email analysis results to the
 * user or report the job status on the command line.
 *
 * @return 0 for success, 1 for failure.
 *
 * @version "$Id: fo_notify.php 3313 2010-06-25 23:52:42Z rrando $"
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
    -n string      = optional, user name to address email to, this is not the email address.
    -u <upload_id> = Upload ID. (required)
    -w web-server  = required, string, fqdn of the webserver

    If no -e option is supplied, status is printed to standard out.
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
$options = getopt("he:n:j:u:w:");
if (empty($options)) {
	print $Usage;
	exit(1);
}

if (array_key_exists("h",$options)) {
	print $Usage;
	exit(0);
}

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
	$UserName = $ToEmail;
}
$Preamble = "Dear $UserName,\n" .
            "Do not reply to this message.  " .
            "This is an automatically generated message by the FOSSology system.\n\n";

/* Optional Job Name */
if (array_key_exists("j",$options)) {
	$JobName = $options['j'];
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
/********************** get job status **************************************/
$summary = JobListSummary($upload_id);
//print "foNotifyDB: summary for upload $upload_id is:\n"; print_r($summary) . "\n";

/* Construct the URL for the message, must have hostname*/
if(array_key_exists("w",$options))
{
	$hostname = $options['w'];
	//print "DBG: hostname is:$hostname\n";
	if(empty($hostname))
	{
		print "Error, no hostname supplied\n";
		exit(1);
	}
}
else
{
	print "Error, no hostname supplied\n";
	exit(1);
}

$JobHistoryUrl = "http://$hostname/repo/?mod=showjobs&history=1&upload=$upload_id";

// get the item to create the browse/license report link
$licenseLinkError = NULL;
$Sql = "SELECT uploadtree_pk FROM uploadtree WHERE parent is NULL and upload_fk=$upload_id";

$result = pg_query($PG_CONN, $Sql);
DBCheckResult($result, $Sql, __FILE__, __LINE__);
if ( pg_num_rows($result))
{
	$row = pg_fetch_assoc($result);
	$item = $row['uploadtree_pk'];
}
else
{
	$licenseLinkError = "Could not get license report link due to missing" .
											"upload tree parent for upload $upload_id\n";
}
pg_free_result($result);

/*
 * need to determine how many files there are.  As the url's are 
 * different depending on a single file or multiple files. See the
 * comment below.
 */

$Sql = "select uploadtree_pk from uploadtree where upload_fk=$upload_id limit 2;";
$result = pg_query($PG_CONN, $Sql);
DBCheckResult($result, $Sql, __FILE__, __LINE__);
$fileCount = pg_num_rows($result);

/*
 * if no agent_pk, no license analysis
 *    mod = view menu if filecount = 1
 *    mod = browse menu if filecount > 1 (need folder for browse menu)
 *
 * if agent and filecount = 1 mod = view-license
 * if agent and filecount > 1 mod = nomoslicense
 *
 * select parent_fk, child_id from foldercontents where child_id=13; child_id is
 * the uploadPK, this will get the folder_pk of the job.
 */

$Agent_pk = LatestNomosAgentpk($upload_id);
if($Agent_pk == 0)
{
	// No license analysis.  Single file, create view url, otherwise browse url
	if($fileCount == 1)
	{
		// view url
		$licenseReportUrl = "http://$hostname/repo/?mod=view" .
												"&upload=$upload_id&show=detail&item=$item";
	}
	if($fileCount > 1)
	{
		// no license analysis scheduled, create a browse menu link to the upload
		$licenseReportUrl = "http://$hostname/repo/?mod=browse" .
												"&upload=$upload_id&show=detail&item=$item";
	}
	$licenseReport = "\n\nNo License analysis was scheduled, browse the upload at:" .
										"\n$licenseReportUrl\n";
}
// license analysis scheduled, create the license report link
else
{
	// no parent for upload if licenseLinkError is not null.
	if(is_null($licenseLinkError))
	{
		if($fileCount == 1)
		{
			// create view-license link
			$licenseReportUrl = "http://$hostname/repo/?mod=view-license&napk=$Agent_pk" .
													"&show=detail&upload=$upload_id&item=$item";
		}
		if($fileCount > 1)
		{
			// create nomoslicense link
			$licenseReportUrl = "http://$hostname/repo/?mod=nomoslicense" .
													"&upload=$upload_id&show=detail&item=$item";
		}
	}
	else
	{
		$licenseReportUrl = $licenseLinkError;
	}
	$licenseReport = "\n\nLicense analysis was scheduled, the report can be found at " .
										"\n$licenseReportUrl\n";
}
// get the item to create the browse license report link

/* Job aborted */
if ($summary['total'] == 0 &&
$summary['completed'] == 0 &&
$summary['active'] == 0 &&
$summary['failed'] == 0 ) {
	$JobStatus = "was killed";
	$MessagePart = "No results, your job $JobName $JobStatus";
	$Message = $Preamble . $MessagePart;
	if ($Interactive) {
		$MessagePart .= " For job details, see: $JobHistoryUrl\n";
		printMsg($MessagePart);
		exit(0);
	}
}

/*
 * NOTE: if run as an agent we assume we are the last job in the jobqueue, so
 * when we check status, completed should be 1 less than the total.  If this
 * check is not made, then the job is always reported as still active...
 */
/* Job is done, OK status */
$Done = FALSE;
/* running as cli */
if ($summary['total'] == $summary['completed']) {
	if ($summary['failed'] == 0) {
		$Done = TRUE;
	}
}
/* As agent */
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
		$MessagePart .= " For job details, see: $JobHistoryUrl\n";
		printMsg($MessagePart);
	}
}
/*
 * Job Failed: the order of checks is important, you can still have pending jobs
 * when a job fails, so check for failed first before pending or active.
 * 
 * @todo wait till after the new scheduler is done... but it would be 
 * nice to report on a failed job what other subjobs are pending.
 */
elseif ($summary['failed'] > 0) {
	$JobStatus = "Failed";
	$MessagePart = "Your requested FOSSology results are  not ready. " .
                 "Your job $JobName $JobStatus.";
	$Message = $Preamble . $MessagePart;
	if ($Interactive) {
		$MessagePart .= "For job details, see: $JobHistoryUrl\n";
		printMsg($MessagePart);
	}
}
/* Job is still active */

elseif ($summary['pending'] > 0 || $summary['active'] > 0) {
	$JobStatus = "is still active.";
	$MessagePart = "Your requested FOSSology results are  not ready. " .
                 "Your job $JobName $JobStatus";
	$Message = $Preamble . $MessagePart;
	if ($Interactive) {
		$MessagePart .= "For more details, see: $JobHistoryUrl\n";
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
	$Mail_body = wordwrap($Message,75);
	$JobHistoryUrl = "\n\nFor job details, see:\n$JobHistoryUrl\n";
	$Mail_body .= $licenseReport . $JobHistoryUrl;
	$Subject = "FOSSology Results: $JobName $JobStatus";
	$Header = "From: " . $Sender . " <" . $From . ">\r\n";
	if($rtn = mail($Recipient, $Subject, $Mail_body, $Header)){
		print "Mail has been queued by fo-notify\n";
		exit(0);
	}
	else {
		print "   WARNING Mail was NOT queued by fo-notify\n";
		print "   WARNING sendmail(1) must be installed and configured for this feature to work\n";
		exit(0);
	}
}
exit(0);
?>
