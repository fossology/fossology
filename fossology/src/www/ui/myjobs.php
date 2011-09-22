<?php
/***********************************************************
 Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

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

/*
 * myJobs plugin
*/

/**
 * \file myJobs.php
 * \brief Display the jobs the user has running, update the screen every x seconds
 *
 * \param takes an upload id (for now)
 *
 * \author markd
 *
 * \todo Fix this defect: This code only uses upload_pk's it needs to find all
 * running jobs by the user (that should get default jobs like removing folders).
 *
 * \todo investigate how to tie cp2foss uploads to the user, is it possible?
 *
 * \version "Id: $"
 */

define("TITLE_myJobs", _("My Jobs Status"));

/**
 * \class myJobs extend from FO_Plugin
 * \brief Display the jobs the user has running, update the screen every x seconds
 */
class myJobs extends FO_Plugin {
  public $Name       = "myjobs";
  public $Title      = TITLE_myJobs;
  public $MenuList   = "Jobs::My Jobs";
  public $LoginFlag  = 1;    // Must be logged in
  public $Dependency = array('db');
  public $DBaccess = PLUGIN_DB_UPLOAD;
  private $Interval  = 7;    // default refresh time

  /**
   * \brief Display the jobs the user has running, update the screen every xx seconds
   *
   * \param $uploadId - upload_pk
   */
  public function displayJob($uploadId=NULL) {

    // Create the style and heading
    $text = _("Click Job Name:Id to see the job details");
    $text1 = _("Page updates every");
    $text2 = _("seconds");
    $text3 = _("Running Jobs");
    $text4 = _("Job Name:Id");
    $text5 = _("Total");
    $text6 = _("Tasks");
    $text7 = _("Completed");
    $text8 = _("Active");
    $text9 = _("Pending");
    $text10 = _("Failed");
    $Heading = "<table border=2 align='center' cellspacing=1 cellpadding=5>\n" .
    "<caption align='top'>$text<br>".
    "$text1 $this->Interval $text2</caption>\n" .
        "   <tr>\n" .
        "     <th colspan=6 align='center'>$text3</th>\n" .
        "   </tr>\n" .
        "   <tr>\n" .
        "     <th align='center'>$text4</th>\n" .
        "     <th align='center'>$text5<br>$text6</th>\n" .
        "     <th align='center'>$text7<br>$text6</th>\n" .
        "     <th align='center'>$text8<br>$text6</th>\n" .
        "     <th align='center'>$text9<br>$text6</th>\n" .
        "     <th align='center'>$text10<br>$text6</th>\n" .
        "   </tr>\n";

    $Tbl = $this->MakeJobTblRow();


    $Refresh = "<META HTTP-EQUIV='refresh' CONTENT=$this->Interval name='refresher'/>\n";

    /*
     $Refresh = "\n\n<script type=\"text/javascript\">

    function brefreshed() {
    self.location.reload();
    }
    </script>";
    */

    print $Heading . $Tbl . $Refresh;

    /*
     $setTimer = "\n\n<script type=\"text/javascript\">
    self.setTimeout(brefreshed(),700000000);
    alert('After setTimeout!');
    </script>";
    print $setTimer;
    */
  }

  /**
   * \brief make job table which contains all ruuning jobs status
   * include: Total Tasks, Completed Tasks, Active Tasks, Pending Tasks, Failed Task
   */
  protected function MakeJobTblRow() {

    global $PG_CONN;
    global $CompletedJobs;

    if(empty($PG_CONN)) {
      $text = _("Fatal internal ERROR! Cannot connect to the DataBase");
      print "<h3 color='red'>$text</h3>\n";
      return(FALSE);
    }

    $uri = Traceback_uri();
    $params = "?mod=showjobs&show=detail&history=1&upload=";
    $jobDetails =  $uri . $params;

    $SqlUploadList = "SELECT  DISTINCT ON (job_upload_fk) job_upload_fk," .
                     "upload_filename from job,upload " .
                    "WHERE user_fk='$_SESSION[UserId]' " .
                    "AND upload_pk=job_upload_fk order by job_upload_fk;\n";
    $JobPhase = array('total' => 'bgcolor="#FFFFCC"',
                      'completed' => 'bgcolor="#D3D3D3"',
                      'active' => 'bgcolor="#99FF99"',
                      'pending' => 'bgcolor="#99FFFF"',
                      'failed' => 'bgcolor="#FF6666"');

    $UploadList = pg_query($PG_CONN, $SqlUploadList);
    DBCheckResult($UploadList, $SqlUploadList, __FILE__, __LINE__);
    $CompletedJobs = array();
    $T = '';
    if(empty($UploadList))
    {
      echo "<h3>No Jobs!?</h3>";
    }
    while ($upload = pg_fetch_row($UploadList)) {
      $status = JobListSummary($upload['job_upload_fk']);

      if ($status['total'] == $status['completed']) {
        array_push($CompletedJobs,$upload);
        continue;
      }
      // build the table entry
      $T .= "   <tr>\n";
      $T .= "     <td><a href=\"$jobDetails$upload[job_upload_fk]\">".
      "$upload[upload_filename]:$upload[job_upload_fk]</a></td>\n";
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
    pg_free_result($UploadList);
    $T .= "</table>\n";
    return($T);
  } // makeTbl4Job

  /**
   * \brief Generate the text for this plugin.
   */
  function Output() {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }   /* State is set by FO_Plugin */
    $V="";
    switch($this->OutputType) {
      /* OutputType is set by FO_Plugin */
      case "XML":
        break;
      case "HTML":
        $this->displayJob();
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
$NewPlugin = new myJobs;
?>
