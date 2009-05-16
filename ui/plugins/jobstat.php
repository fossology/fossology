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

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

require_once('common/common.php');
/*
 * Make this into a jobs menu item.  MyJobs
 * - get user name from session
 * - query db to get user_pk
 *   - Use user_pk to get all jobs for this user, getting the
 *     - job_upload_fk, job_name
 *     SELECT user_pk, user_name from users WHERE user_name=[session];
 *     ??SELECT job_pk,job_upload_fk,job_name from job WHERE job_user_fk=3;
 *     SELECT job_upload_fk FROM job WHERE job_user_fk="$_SESSION[UserId]";
 *     SELECT job_upload_fk,upload_filename from job,upload WHERE job_user_fk=3
 *     and upload_pk=job_upload_fk order by upload_pk;
 *     that will get the data we need.  this gets used by the fill_table method
 *     which will use the querys to get the data and fill in the table entry,
 *     returns big'o string that is the table entry for that job.
 *  - Need to create stop and start buttons with appropriate javascript that
 *  edits the dom to remove and insert the meta tag that does the refresh.
 *
 *  for bonus points, have a select type widget that allows you to set the
 *  refresh time. (can't go below 10 seconds?).
 */

global $DB;
/**
 * jobStatus
 *
 * Display the jobs the user has running, update the screen every 30 seconds
 *
 * @param takes an upload id (for now)
 *
 * @author markd
 *
 */
class jobStatus extends FO_Plugin {
  public $Name       = "jobstat";
  public $Title      = "Job Status";
  public $MenuList   = "Jobs::MyJobs";
  public $MenuOrder  = 0;    // Don't appear in a menu (internal plugin)
  public $MenuTarget = 0;
  public $LoginFlag  = 1;    // Must be logged in
  public $Dependency = array('upload_file', 'upload_url', 'upload_srv_files');

  /**
   * displayJob
   *
   *  Display the jobs the user has running, update the screen every 30 seconds
   *
   * @param int $uploadId (upload_pk).
   *
   */
  public function displayJob($uploadId=NULL) {
    // fix this logic... it needs to check for an uploadid.
    $UploadPk = GetParm('upload',PARM_INTEGER);
    //print "<pre>upload is:$UploadPk\n</pre>";
    if(empty($UploadPk)) {
      print "<h2 align='center'>Sorry, no Job Id supplied, I can't look up any jobs</h2>\n";
      return;       // display nothing, fix this... should display an appropriate message.
    }
    else {
      $status = JobListSummary($UploadPk);
      //print "<pre>jobstatus is:\n"; print_r($status) . "\n";
      $P = NULL;
      //      $P .= "\n</body>\n<body class='text' onload='settick'>\n";
      $P .= "<table border=2 align='left' cellspacing=1 cellpadding=2>\n";
      $P .= "<th colspan=2 align='center'>Job Status for job ID $UploadPk</th>\n";
      $P .= "<tr>\n";
      $P .= "<td>Tasks Scheduled</td>\n";
      $P .= "<td>$status[total]</td>\n";
      $P .= "</tr>\n";
      $P .= "<tr>\n";
      $P .= "<td>Tasks currently active</td>\n";
      $P .= "<td>$status[active]</td>\n";
      $P .= "</tr>\n";
      $P .= "<tr>\n";
      $P .= "<td>Tasks Pending</td>\n";
      $P .= "<td>$status[pending]</td>\n";
      $P .= "</tr>\n";
      $P .= "<tr>\n";
      $P .= "<td>Tasks Completed</td>\n";
      $P .= "<td>$status[completed]</td>\n";
      $P .= "</tr>\n";
      $P .= "<tr>\n";
      $P .= "<td>Tasks Failed</td>\n";
      $P .= "<td>$status[failed]</td>\n";
      $P .= "</tr>\n";
      $P .= "<caption align='bottom'>Page will refresh every 30 seconds</caption>\n";
      $P .= "</table>\n";
      $P .= "<META HTTP-EQUIV='refresh' CONTENT=15>\n";
      $P .= "<script type='text/javascript'>\n";
      $P .= "function brefreshed() {
                document.location.reload(true);
             }\n";
      $P .= "function settick() {
                var Iclear = setInterval('berefreshed',5000);
             }\n";
      $P .= "</script>\n";
      print $P;
    }
  }

  protected function MakeJobTblRow() {

    global $DB;
    if(empty($DB)) {
      print "<h3>some message</h3>\n";
      return(FALSE);
    }

    $SqlUploadList = "SELECT  DISTINCT ON (job_upload_fk) job_upload_fk," .
                     "upload_filename from job,upload " .
                    "WHERE job_user_fk=$_SESSION[UserId] " .
                    "AND upload_pk=job_upload_fk order by job_upload_fk;\n";
    $JobPhase = array('total',
                       'active',
                       'completed',
                       'pending',
                       'failed');
    $UploadList = $DB->Action($SqlUploadList);
    print "";
    // should be an array of uploadpk's
    foreach($UploadPkList as $UploadPk) {
      $status = JobListSummary($UploadPk);
      // check the status if completed (no pending and no active?) the push
      // to the completed job list else process as active job
      foreach($JobPhase as $job) {
        // do some stuff.
      }
    }
  } // makeTbl4Job

  function Output() {
    if ($this->State != PLUGIN_STATE_READY) { return; }   /* State is set by FO_Plugin */
    $V="";
    switch($this->OutputType) {                             /* OutputType is set by FO_Plugin */
      case "XML":
        break;
      case "HTML":
        $V .= $this->displayJob();
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) {
      return($V);
    }
    print($V);
    return;
  }
};
$NewPlugin = new jobStatus;
?>
