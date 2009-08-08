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

global $WEBDIR;
require_once("$WEBDIR/common/common.php");
/*
 * jobjstat plugin
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
 * @todo Fix this defect: This code only uses upload_pk's it needs to find all
 * running jobs by the user (that should get default jobs like removing folders).
 *
 * @todo investigate how to tie cp2foss uploads to the user, is it possible?
 */
class jobStatus extends FO_Plugin {
  public $Name       = "jobstat";
  public $Title      = "Job Status";
  public $MenuList   = "Jobs::MyJobs";
  public $MenuOrder  = 0;    // Don't appear in a menu (internal plugin) BUG!
  public $MenuTarget = 0;
  public $LoginFlag  = 1;    // Must be logged in
  public $Dependency = array('upload_file', 'upload_url', 'upload_srv_files');
  private $Interval  = 7;    // default refresh time

  /**
   * displayJob
   *
   *  Display the jobs the user has running, update the screen every 30 seconds
   *
   * @param int $uploadId (upload_pk).
   *
   */
  public function displayJob($uploadId=NULL) {

    //$this->Interval = 7;

    // Create the style and heading
    $Heading = "<table border=2 align='center' cellspacing=1 cellpadding=5>\n" .
        "   <tr>\n" .
        "     <th colspan=6 align='center'>Running Jobs</th>\n" .
        "   </tr>\n" .
        "   <tr>\n" .
        "     <th align='center'>Job Name:Id</th>\n" .
        "     <th align='center'>Total<br>Tasks</th>\n" .
        "     <th align='center'>Completed<br>Tasks</th>\n" .
        "     <th align='center'>Active<br>Tasks</th>\n" .
        "     <th align='center'>Pending<br>Tasks</th>\n" .
        "     <th align='center'>Failed<br>Tasks</th>\n" .
        "   </tr>\n";

    $Tbl = $this->MakeJobTblRow();
    $Refresh = "<META HTTP-EQUIV='refresh' CONTENT=$this->Interval name='refresher'/>\n";

    $RunningJobs = $Heading . $Tbl . $Refresh;
    print $RunningJobs;
  }

  protected function MakeJobTblRow() {

    global $DB;
    global $CompletedJobs;

    if(empty($DB)) {
      print "<h3 color='red'>Fatal internal ERROR! Cannot connect to the DataBase</h3>\n";
      return(FALSE);
    }

    $SqlUploadList = "SELECT  DISTINCT ON (job_upload_fk) job_upload_fk," .
                     "upload_filename from job,upload " .
                    "WHERE job_user_fk=$_SESSION[UserId] " .
                    "AND upload_pk=job_upload_fk order by job_upload_fk;\n";
    $JobPhase = array('total' => 'bgcolor="#FFFFCC"',
                      'completed' => 'bgcolor="#D3D3D3"',
                      'active' => 'bgcolor="#99FF99"',
                      'pending' => 'bgcolor="#99FFFF"',
                      'failed' => 'bgcolor="#FF6666"');
    $UploadList = $DB->Action($SqlUploadList);
    $CompletedJobs = array();
    $T = '';
    foreach($UploadList as $upload) {
      $status = JobListSummary($upload['job_upload_fk']);
      // check the status if completed (no pending and no active?) then push
      // to the completed job list else process as active job
      if ($status['total'] == $status['completed']) {
        array_push(&$CompletedJobs,$upload);
        continue;
      }
      // build the table entry
      $T .= "   <tr>\n";
      $T .= "     <td>$upload[upload_filename]:$upload[job_upload_fk]</td>\n";
      $color = "";
      foreach($JobPhase as $phase => $color) {
        /* Only cells with something going on get a color */
        if($status[$phase] == 0){
          $T .= "     <td align='center'>$status[$phase]</td>\n";
        }
        else {
          $T .= "     <td align='center' $color>$status[$phase]</td>\n";
        }
      }
      $T .= "   </tr>\n";      // close the row and table
    }

    $T .= "<caption align='bottom'>Page updates every $this->Interval seconds</caption>\n";
    $T .= "</table>\n";
    return($T);
  } // makeTbl4Job

  function Output() {
    if ($this->State != PLUGIN_STATE_READY) { return; }   /* State is set by FO_Plugin */
    $V="";
    switch($this->OutputType) {                             /* OutputType is set by FO_Plugin */
      case "XML":
        break;
      case "HTML":
          $this->displayJob(NULL);
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
