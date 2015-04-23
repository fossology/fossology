<?php
/*
 Copyright (C) 2015, Siemens AG
 Author: Shaheem Azmal<shaheem.azmal@siemens.com>, 
         Anupam Ghosh <anupam.ghosh@siemens.com>

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
 */

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Dao\ShowJobsDao;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

define("TITLE_ajaxShowJobs", _("ShowJobs"));

class AjaxShowJobs extends FO_Plugin
{
  /** @var showJobsDao */
  private $showJobsDao;
  /** @var maxUploadsPerPage */
  private $maxUploadsPerPage = 10;  /* max number of uploads to display on a page */
  /** @var colors */
  private $colors = array(
          "Queued" => "#FFFFCC", // "white-ish",
          "Scheduled" => "#99FFFF", // "blue-ish",
          "Running" => "#99FF99", // "green",
          "Finished" => "#D3D3D3", // "lightgray",
          "Blocked" => "#FFCC66", // "orange",
          "Failed" => "#FF6666" // "red"
          );

  function __construct()
  {
    $this->Name = "ajaxShowJobs";
    $this->Title = TITLE_ajaxShowJobs;
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->LoginFlag = 0;
    $this->NoMenu = 0;
    $this->OutputType = 'JSON';
    $this->OutputToStdout = true;

    global $container;
    $this->showJobsDao = $container->get('dao.showJobs');

    parent::__construct();
  }

  function OutputOpen()
  {
    if ($this->State != PLUGIN_STATE_READY){
      return null;
    }
    $uploadId = GetParm("upload", PARM_INTEGER);
    if (empty($uploadId)){
      return null;
    }

  }

  /**
   * @brief Sort compare function to order $JobsInfo by job_pk
   * @param $JobsInfo1 Result from GetJobInfo
   * @param $JobsInfo2 Result from GetJobInfo
   * @return <0,==0, >0 
   */
  protected function compareJobsInfo($JobsInfo1, $JobsInfo2)
  {
    $job_pk1 = $JobsInfo1["job"]["job_pk"];
    $job_pk2 = $JobsInfo2["job"]["job_pk"];

    return $job_pk2 - $job_pk1;
  }

  /**
   * @brief Returns geeky scan details about the jobqueue item
   * @param $job_pk
   * @return Return job and jobqueue record data in an html table.
   **/
  protected function showJobDB($job_pk)
  {
    global $container;
    /** @var DbManager */
    $dbManager = $container->get('db.manager');
    
    $i=0;
    $fields=array('jq_pk'=>'jq_pk',
                  'job_pk'=>'jq_job_fk',
                  'Job Name'=> 'job_name',
                  'Agent Name'=>'jq_type',
                  'Priority'=>'job_priority',
                  'Args'=>'jq_args',
                  'jq_runonpfile'=>'jq_runonpfile',
                  'Queued'=>'job_queued',
                  'Started'=>'jq_starttime',
                  'Ended'=>'jq_endtime',
                  'Elapsed HH:MM:SS'=>'elapsed',
                  'Status'=>'jq_end_bits',
                  'Items processed'=>'jq_itemsprocessed',
                  'Submitter'=>'job_user_fk',
                  'Upload'=>'job_upload_fk', 
                  'Log'=>'jq_log');
    $uri = Traceback_uri() . "?mod=showjobs&upload=";

    $statementName = __METHOD__."ShowJobDBforjob";
    $dbManager->prepare($statementName,
    "SELECT *, jq_endtime-jq_starttime as elapsed FROM jobqueue LEFT JOIN job ON job.job_pk = jobqueue.jq_job_fk WHERE jobqueue.jq_pk =$1");
    $result = $dbManager->execute($statementName, array($job_pk));
    $row = $dbManager->fetchArray($result);
    $dbManager->freeResult($result);

    $table = array();
    foreach($fields as $labelKey=>$field){
      $value ="";
      $label = $labelKey;
      switch($field){
        case 'jq_itemsprocessed':  
          $value = number_format($row[$field]);
          break;
        case 'jq_end_bits':
          $value = $this->jobqueueStatus($row);
          break;
        case 'jq_pk':
          if(!empty($row['job_upload_fk'])){
            $value = "<a href='$uri" . $row['job_upload_fk'] . "'>" . htmlentities($row[$field]) . "</a>"." (" . _("Click to view jobs for this upload") . ")";
          }else{
            $uri2 = Traceback_uri() . "?mod=showjobs";
            $back = "(" . _("Click to return to Show Jobs") . ")";
            $value = "(" . _("Click to return to Show Jobs") . ")"."<a href='$uri2'>$row[$field] $back</a>";
          }
          break;
        case 'job_upload_fk':
          if(!empty($row[$field])){
            $browse = Traceback_uri() . "?mod=browse&upload=" . htmlentities($row[$field]);
            $value = "<a href='$browse'>" . htmlentities($row[$field]) . "</a>"." (" . _("Click to browse upload") . ")";
          }
          break;
        case 'jq_log':
          if(!empty($row[$field])){
            if(file_exists($row[$field])){ 
              $value = "<pre>" .file_get_contents($row[$field])."</pre>"; 
            }
          }
          break;
        case 'job_user_fk':
          if(!empty($row[$field])){
            $statementName = __METHOD__."UserRow";
            $userRow = $dbManager->getSingleRow(
            "select user_name from users where user_pk=$1",
            array($row[$field]),
            $statementName
            );
            $value = $userRow['user_name'];
          }
          break;
        case 'jq_args':
          $jq_args_temp = $row[$field];
          $jq_args_show = $jq_args_temp;
          if (!empty($jq_args_temp)){
            $pos = strpos($jq_args_temp, ' SVN ');
            if ($pos) {
              $jq_args_show = substr($jq_args_temp, 0, $pos + 4);
            }
            $pos = strpos($jq_args_temp, ' CVS ');
            if ($pos) {
              $jq_args_show = substr($jq_args_temp, 0, $pos + 4);
            }
            $pos = strpos($jq_args_temp, ' Git ');
            if ($pos) {
              $jq_args_show = substr($jq_args_temp, 0, $pos + 4);
            }
            $value = $jq_args_show;
          }
          break;
        default:
          if (array_key_exists($field, $row)) {  $value = htmlentities($row[$field]); }
          break;
      }
      $table[]= array('DT_RowId' => $i++,
                      '0'=>$label,
                      '1'=> $value);
    }
    $table = array_values($table); 
    return new JsonResponse(array('sEcho' => intval($_GET['sEcho']),
                                  'aaData' => $table,
                                  'iTotalRecords' => count($table),
                                  'iTotalDisplayRecords' => count($table)));
    
  } // showJobDB()

  /**
   * @brief Returns an upload job status in html
   * @param $jobData
   * @return Returns an upload job status in html
   **/
  protected function show($jobData, $page)
  {
    global $container;
    /** @var DbManager */
    $dbManager = $container->get('db.manager');

    $outBuf = '';
    $pagination = '';
    $numJobs = count($jobData);
    if ($numJobs == 0){
      return array('showJobsData' => "There are no jobs to display");
    }
    $uri = Traceback_uri() . "?mod=showjobs";
    $uriFull = $uri . Traceback_parm_keep(array("upload"));
    $uriFullMenu = $uri . Traceback_parm_keep(array("allusers"));
    /* Next/Prev menu */
    $next = $numJobs > $this->maxUploadsPerPage;
    if ($numJobs > $this->maxUploadsPerPage)  
    $pagination .= MenuEndlessPage($page, $next,$uriFullMenu); 

    /*****************************************************************/
    /* Now display the summary */
    /*****************************************************************/

    $job=-1;
    
    $uploadStyle = "style='font:bold 10pt verdana, arial, helvetica; background:gold; color:white;'";
    $noUploadStyle = "style='font:bold 10pt verdana, arial, helvetica; background:gold; color:black;'";
    $jobStyle = "style='font:bold 8pt verdana, arial, helvetica; background:lavender; color:black;'";
    $prevupload_pk = "";

    $firstJob = $page * $this->maxUploadsPerPage;
    $lastJob = ($page * $this->maxUploadsPerPage) + $this->maxUploadsPerPage;
    $jobNumber = -1;
    /** if $single_browse is 1, represent alread has an upload browse link, if single_browse is 0, no upload browse link */
    $single_browse = 0;
    foreach ($jobData as $job_pk => $job){
      /* Upload  */
      if (!empty($job["upload"])){
        $uploadName = GetArrayVal("upload_filename", $job["upload"]);
        $uploadDesc = GetArrayVal("upload_desc", $job["upload"]);
        $upload_pk = GetArrayVal("upload_pk", $job["upload"]);
        $jobId = GetArrayVal("job_pk", $job["job"]);
        /** the column pfile_fk of the record in the table(upload) is NULL when this record is inserted */
        if ((!empty($upload_pk) && $prevupload_pk != $upload_pk) || (empty($upload_pk) && 0 == $single_browse)){
          $prevupload_pk = $upload_pk;
          $jobNumber++;

          /* Only display the jobs for this page */
          if ($jobNumber >= $lastJob) break;
          if ($jobNumber < $firstJob) continue;

          /* blank line separator between pfiles */
          $outBuf .= "<tr><td colspan=8> <hr> </td></tr>";
          $outBuf .= "<tr>";
          $outBuf .= "<th $uploadStyle></th>";
          $outBuf .= "<th colspan=6 $uploadStyle>";

          if(!empty($job['uploadtree'])) {
            $uploadtree_pk = $job['uploadtree']['uploadtree_pk'];
            $outBuf .= "<a title='Click to browse' href='" . Traceback_uri() . "?mod=browse&upload=" . $job['job']['job_upload_fk'] . "&item=" . $uploadtree_pk . "'>";
          }else{
            $outBuf .= "<a $noUploadStyle>";
          }  
          
          /* get $userName if all jobs are shown */
          $userName = "";
          $allusers = GetParm("allusers",PARM_INTEGER);
          if ($allusers > 0){
            $statementName = __METHOD__."UploadRec";
            $uploadRec = $dbManager->getSingleRow(
            "select * from upload where upload_pk=$1",
            array($job['job']['job_upload_fk']),
            $statementName
            );

            if (!empty($uploadRec['user_fk'])){
              $statementName = __METHOD__."UserRec";
              $userRec = $dbManager->getSingleRow(
              "select * from users where user_pk=$1",
              array($uploadRec['user_fk']),
              $statementName
              );
              $userName = "&nbsp;&nbsp;&nbsp;($userRec[user_name])";
            }else{
              $statementName = __METHOD__."UserRec1";
              $userRec = $dbManager->getSingleRow(
              "select * from users where user_pk=$1",
              array($job['job']['job_user_fk']),
              $statementName
              );
              $userName = "&nbsp;&nbsp;&nbsp;($userRec[user_name])";
            }

          }

          $outBuf .= $uploadName . $userName;
          if (!empty($uploadDesc)) $outBuf .= " (" . $uploadDesc . ")";
          $outBuf .= "</a>";
          $outBuf .= "</th>";
          $outBuf .= "<th $uploadStyle><a>".$this->showJobsDao->getEstimatedTime($jobId)."</a></th>";
          $outBuf .= "</tr>";
          $single_browse = 1;
        }else{ 
          if ($jobNumber < $firstJob) continue; 
        }
      }else{  /* Show Jobs that are not attached to an upload */

        $jobNumber++;
        /* Only display the jobs for this page */
        if ($jobNumber >= $lastJob) break;
        if ($jobNumber < $firstJob) continue;

        /* blank line separator between pfiles */
        $outBuf .= "<tr><td colspan=7> <hr> </td></tr>";
        $outBuf .= "<tr>";
        $outBuf .= "<th $noUploadStyle></th>";
        $outBuf .= "<th colspan=4 $noUploadStyle>";
        $outBuf .= $job["job"]["job_name"];
        $outBuf .= "</th>";
        $outBuf .= "<th $noUploadStyle></th>";
        $outBuf .= "</tr>";
      }

      /* Job data */
      $outBuf .= "<tr>";
      $outBuf .= "<th $jobStyle>";
      $outBuf .= _("Job/Dependency");
      $outBuf .= "</th>";

      $outBuf .= "<th $jobStyle>";
      $outBuf .= _("Status");
      $outBuf .= "</th>";

      $outBuf .= "<th colspan=3 $jobStyle>";
      $outBuf .= $job["job"]["job_name"];
      $outBuf .= "</th>";
      
      $outBuf .= "<th $jobStyle>";
      $outBuf .= _("Average items/sec");
      $outBuf .= "</th>";
      
      $outBuf .= "<th $jobStyle>";
      $outBuf .= _("ETA");
      $outBuf .= "</th>";

      $outBuf .= "<th $jobStyle>";
      $outBuf .= "</th></tr>";
  
      /* Job queue */
      foreach ($job['jobqueue'] as $jq_pk => $jobqueueRec){
        $rowColor = $this->getColor($jobqueueRec);
        $jobqueueStyle = $this->jobqueueStyle($rowColor);
        $outBuf .= "<tr $jobqueueStyle>";

        /* Job/Dependency */
        $outBuf .= "<td $jobqueueStyle>";
        $outBuf .= "<a href='$uriFull&show=job&job=" . $jq_pk . "'>" ;
        $outBuf .= $jq_pk;
        $outBuf .= "</a>";
        $count = 0;
        if (!empty($jobqueueRec["jdep_jq_depends_fk"])){
          foreach ($jobqueueRec["depends"] as $depend_jq_pk){
            $outBuf .= ($count++ == 0) ? " / " : ", ";
            $outBuf .= "<a href='$uriFull&show=job&job=" . $depend_jq_pk . "'>" ;
            $outBuf .= $depend_jq_pk;
            $outBuf .= "</a>";
          }
        }
        $outBuf .= "</td>";

        /* status */
        $status = $jobqueueRec["jq_endtext"];
        $outBuf .= "<td style='text-align:center'>$status</td>";
        $isPaused = ($status == "Paused") ? true : false;

        /* agent name */
        $outBuf .= "<td>$jobqueueRec[jq_type]</td>";

        /* items processed */
        if ( $jobqueueRec["jq_itemsprocessed"] > 0){
          $items = number_format($jobqueueRec['jq_itemsprocessed']);
          $outBuf .= "<td style='text-align:right'>$items items</td>";
        }else{
          $outBuf .= "<td></td>";
        } 

        /* dates */
        $outBuf .= "<td>";
        $outBuf .= substr($jobqueueRec['jq_starttime'], 0, 16);
        if (!empty($jobqueueRec["jq_endtime"])) {
          $outBuf .= " - " . substr($jobqueueRec['jq_endtime'], 0, 16);
          $numSecs = strtotime($jobqueueRec['jq_endtime']) - strtotime($jobqueueRec['jq_starttime']);
        }else{
          $numSecs = time()  - strtotime($jobqueueRec['jq_starttime']);
        } 
        $outBuf .= "</td>";
        $outBuf .= "<td><span style='float:right;margin-right:32%;'>";
        /* Don't display items/sec unless the job has started */
        if ($jobqueueRec['jq_starttime']){
          $text = _(" items/sec");
          $itemsPerSec = $this->showJobsDao->getNumItemsPerSec($jobqueueRec['jq_itemsprocessed'], $numSecs);
          $itemsPerSecFmt = ($itemsPerSec < 2) ? sprintf("%01.2f", $itemsPerSec) : round($itemsPerSec);
          $outBuf .= $itemsPerSecFmt.$text;
        }
        $outBuf .= "</span></td>";
        /* Get ETA for each agent */
        $text = _("Scanned");
        if(empty($jobqueueRec['jq_endtime']))
          $outBuf .= "<td align='center'>".$this->showJobsDao->getEstimatedTime($jobId, $jobqueueRec['jq_type'], $itemsPerSec)."</td>";
        else
          $outBuf .= "<td align='center'>$text</td>";  
        /* actions, must be admin or own the upload  */
        if (($jobqueueRec['jq_end_bits'] == 0) 
             && (($_SESSION[Auth::USER_LEVEL] == PLUGIN_DB_ADMIN)
                 || ($_SESSION[Auth::USER_ID] == $job['job']['job_user_fk'])))
        {
          $outBuf .= "<th $jobStyle>";
          if ($isPaused){
            $text = _("Unpause");
            $outBuf .= "<a href='$uriFull&action=restart&jobid=$jq_pk' title='Un-Pause this job'>$text</a>";
          }else{
            $text = _("Pause");
            $outBuf .= "<a href='$uriFull&action=pause&jobid=$jq_pk' title='Pause this job'>$text</a>";
          }
          $outBuf .= " | ";
          $text = _("Cancel");
          $outBuf .= "<a href='$uriFull&action=cancel&jobid=$jq_pk' title='Cancel this job'>$text</a>";
        }else{
          $outBuf .= "<th>";
        } 

        if (($jobqueueRec['jq_end_bits'] == 1) && ($jobqueueRec['jq_type'] === 'reportgen' || $jobqueueRec['jq_type'] === 'readmeoss')
             && (($_SESSION[Auth::USER_LEVEL] > PLUGIN_DB_ADMIN)
                 || ($_SESSION[Auth::USER_ID] == $job['job']['job_user_fk']))){
          if($jobqueueRec['jq_type'] === 'reportgen')
            $text = _("Download Report");
          else
            $text = _("Download ReadMe_OSS");
          $outBuf .= "<a href='" . Traceback_uri() . "?mod=download&report=$jobqueueRec[jq_job_fk]'>" .$text."</a>";
        }
        $outBuf .= "</th></tr>";
      }
    }
    if ($numJobs > $this->maxUploadsPerPage) $pagination = "<p>" . MenuEndlessPage($page, $next,$uriFullMenu); 

    return array('showJobsData' => $outBuf, 'pagination' => $pagination);
  }

  /**
   * @brief Are there any unfinished jobqueues in this job?
   * @param $job
   * @return true if $job contains unfinished jobqueue's
   **/
  protected function isUnfinishedJob($job)
  {
    foreach ($job['jobqueue'] as $jq_pk => $jobqueueRec){
      if ($jobqueueRec['jq_end_bits'] == 0) return true;
    } 
    return false;
  }  /* isUnfinishedJob()  */
  

  /**
   * @brief Get the style for a jobqueue rec.
   * This is color coded based on $color
   * @param $color
   * @return a string containing the style
   **/
  protected function jobqueueStyle($color)
  {
    $jobqueueStyle = "style='font:normal 8pt verdana, arial, helvetica; background:$color; color:black;'";
    return $jobqueueStyle;
  }  /* jobqueueStyle() */


  /**
   * @brief Get the jobqueue row color
   * @return the color as a string
   **/
  protected function getColor($jobqueueRec)
  {
    $color=$this->colors['Queued']; /* default */
    if ($jobqueueRec['jq_end_bits'] > 1){
      $color=$this->colors['Failed'];
    }
    else if (!empty($jobqueueRec['jq_starttime']) && empty($jobqueueRec['jq_endtime'])){
      $color=$this->colors['Scheduled'];
    }else if (!empty($jobqueueRec['jq_starttime']) && !empty($jobqueueRec['jq_endtime'])){
      $color=$this->colors['Finished'];
    }
    return $color;
  } /* getColor() */


  /**
   * @brief Get the status of a jobqueue item
   * If the job isn't known to the scheduler, then report the status based on the
   * jobqueue table.  If it is known to the scheduler, use that for the status.
   * @param $jobqueueRec
   * @return a string describing the status
   **/
  protected function jobqueueStatus($jobqueueRec)
  {
    $status = "";
    
    /* check the jobqueue status.  If the job is finished, return the status. */
    if (!empty($jobqueueRec['jq_endtext'])) 
      $status .= "$jobqueueRec[jq_endtext]";

    if (!strstr($status, "Success") and !strstr($status, "Fail") and $jobqueueRec["jq_end_bits"]){
      $status .= "<br>";
      if ($jobqueueRec["jq_end_bits"] == 0x1)
        $status .= _("Success");
      else if ($jobqueueRec["jq_end_bits"] == 0x2)
        $status .= _("Failure");
      else if ($jobqueueRec["jq_end_bits"] == 0x4)
        $status .= _("Nonfatal");
    }
    return $status;
  } /* jobqueueStatus() */

  
  /**
   * @brief get data of all jobs using uploadpk
   * @return a json jobqueue data. 
   **/
  protected function getJobs($uploadPk)
  {
    $page = GetParm('page', PARM_INTEGER);
    
    if ($uploadPk>0){
        $upload_pks = array($uploadPk);
        $jobs = $this->showJobsDao->uploads2Jobs($upload_pks, $page);
    }else{
      $allusers = GetParm("allusers", PARM_INTEGER); 
      $jobs = $this->showJobsDao->myJobs($allusers);
    } 
    $jobsInfo = $this->showJobsDao->getJobInfo($jobs, $page);
    usort($jobsInfo, "compareJobsInfo");
      
    $showJobData = $this->show($jobsInfo, $page);
    return new JsonResponse($showJobData);
  } /* getJobs()*/

  public function Output()
  {
    if ($this->State != PLUGIN_STATE_READY){
      return 0;
    }
    $output = $this->jsonContent();
    if (!$this->OutputToStdout){
      return;
    }
    if ($output === "success"){
      header('Content-type: text/json');
      return $output;
    }
    header('Content-type: text/json');
    return $output;
  }

  protected function jsonContent()
  {
    global $SysConf;

    $userId = $SysConf['auth']['UserId'];
    $uploadPk;
    $job_pk1;
    $action = GetParm("do", PARM_STRING);
    if ($action){
      switch ($action){
        case "showjb":        
          $uploadPk = GetParm('upload',PARM_INTEGER);
          if(empty($uploadPk)){ 
            return;
          }
      }
      switch ($action){
        case "showjb":
          return $this->getJobs($uploadPk);
        case "showSingleJob":
          $job_pk1 = GetParm('jobId',PARM_INTEGER);
          return $this->showJobDB($job_pk1);
      }
    }
  }
}

$NewPlugin = new AjaxShowJobs();
