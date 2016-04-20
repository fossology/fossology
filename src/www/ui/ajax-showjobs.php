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
use Fossology\Lib\Dao\ShowJobsDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Db\DbManager;
use Symfony\Component\HttpFoundation\JsonResponse;

define("TITLE_ajaxShowJobs", _("ShowJobs"));

class AjaxShowJobs extends FO_Plugin
{
  const MAX_LOG_OUTPUT = 32768;

  /** @var dbManager */
  private $dbManager;

  /** @var ShowJobsDao */
  private $showJobsDao;

  /** @var UserDao */
  private $userDao;

  /** @var int $maxUploadsPerPage max number of uploads to display on a page */
  private $maxUploadsPerPage = 10;

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
    $this->showJobsDao = $container->get('dao.show_jobs');
    $this->userDao = $container->get('dao.user');
    $this->dbManager = $container->get('db.manager');

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
  protected function getGeekyScanDetailsForJob($job_pk)
  {    
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

    $row = $this->showJobsDao->getDataForASingleJob($job_pk);

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
            $value = "<a href='$uri2'>$row[$field] $back</a>";
          }
          break;
        case 'job_upload_fk':
          if(!empty($row[$field])){
            $browse = Traceback_uri() . "?mod=browse&upload=" . htmlentities($row[$field]);
            $value = "<a href='$browse'>" . htmlentities($row[$field]) . "</a>"." (" . _("Click to browse upload") . ")";
          }
          break;
        case 'jq_log':
          if(empty($row[$field]) || !file_exists($row[$field])){
            break;
          }  
          if(filesize($row[$field])>self::MAX_LOG_OUTPUT){ 
            $value = "<pre>" .file_get_contents($row[$field],false,null,-1,self::MAX_LOG_OUTPUT)."</pre>"
                    .'<a href="'.Traceback_uri() . '?mod=download&log=' . $row['jq_pk'] . '">...</a>';
          }
          else{ 
            $value = "<pre>" .file_get_contents($row[$field]). "</pre>"; 
          }
          break;
        case 'job_user_fk':
          if(!empty($row[$field])){
            $value = $this->userDao->getUserName($row[$field]);
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
      $table[] = array('DT_RowId' => $i++,
                      '0'=>$label,
                      '1'=> $value);
    }
    $tableData = array_values($table); 
    return new JsonResponse(array('sEcho' => intval($_GET['sEcho']),
                                  'aaData' => $tableData,
                                  'iTotalRecords' => count($tableData),
                                  'iTotalDisplayRecords' => count($tableData)));
    
  } /* getGeekyScanDetailsForJob() */

  /**
   * @brief Returns an upload job status in html
   * @param $jobData, $page, $allusers
   * @return Returns an upload job status in html
   **/
  protected function getShowJobsForEachJob($jobData, $page, $allusers)
  {
    $outBuf = '';
    $pagination = '';
    $uploadtree_pk = 0;
    $numJobs = count($jobData);
    if ($numJobs == 0){
      return array('showJobsData' => "There are no jobs to display");
    }
    $uri = Traceback_uri() . "?mod=showjobs";
    $uriFull = $uri . Traceback_parm_keep(array("upload"));
    $uriFullMenu = $uri . Traceback_parm_keep(array("allusers"));
    /* Next/Prev menu */
    $next = $numJobs > $this->maxUploadsPerPage;
    if ($numJobs > $this->maxUploadsPerPage) {
      $pagination .= MenuEndlessPage($page, $next, $uriFullMenu);
    }

    /*****************************************************************/
    /* Now display the summary */
    /*****************************************************************/

    $uploadStyle = "style='font:bold 10pt verdana, arial, helvetica; background:gold; color:white;'";
    $noUploadStyle = "style='font:bold 10pt verdana, arial, helvetica; background:gold; color:black;'";
    $jobStyle = "style='font:bold 8pt verdana, arial, helvetica; background:lavender; color:black;'";
    $prevupload_pk = "";

    $firstJob = $page * $this->maxUploadsPerPage;
    $lastJob = ($page * $this->maxUploadsPerPage) + $this->maxUploadsPerPage;
    $jobNumber = -1;
    /** if $single_browse is 1, represent alread has an upload browse link, if single_browse is 0, no upload browse link */
    $single_browse = 0;
    foreach ($jobData as $job){
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
          if ($allusers > 0){
            $statementName = __METHOD__."UploadRec";
            $uploadRec = $this->dbManager->getSingleRow("select user_fk from upload where upload_pk=$1",
                array($job['job']['job_upload_fk']),
                $statementName);

            if (!empty($uploadRec['user_fk'])){
              $userName = $this->userDao->getUserName($uploadRec['user_fk']);
            }else{
              $userName = $this->userDao->getUserName($job['job']['job_user_fk']);
            }
            $userName = "&nbsp;&nbsp;&nbsp;(" . htmlentities($userName, ENT_QUOTES) . ")";
          }

          $outBuf .= htmlentities($uploadName, ENT_QUOTES) . $userName;
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
        $outBuf .= "<tr><td colspan=8> <hr> </td></tr>";
        $outBuf .= "<tr>";
        $outBuf .= "<th $noUploadStyle></th>";
        $outBuf .= "<th colspan=6 $noUploadStyle>";
        $outBuf .= htmlentities($job["job"]["job_name"], ENT_QUOTES);
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
      $outBuf .= htmlentities($job["job"]["job_name"], ENT_QUOTES);
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
        $varJobQueueRow = array('jqId'=>$jq_pk,
                                'jobId'=>$jobqueueRec['jq_job_fk'],
                                'class'=>$this->getClass($jobqueueRec),
                                'uriFull'=>$uriFull,
                                'depends'=>$jobqueueRec['jdep_jq_depends_fk'] ? $jobqueueRec['depends'] : array(),
                                'status'=>$jobqueueRec['jq_endtext'],
                                'agentName'=>$jobqueueRec['jq_type'],
                                'itemsProcessed'=>$jobqueueRec['jq_itemsprocessed'],
                                'startTime'=>substr($jobqueueRec['jq_starttime'], 0, 16),
                                'endTime'=>empty($jobqueueRec["jq_endtime"]) ? '' : substr($jobqueueRec['jq_endtime'], 0, 16),
                                'endText'=>$jobqueueRec['jq_endtext'],
                                'page'=>$page,
                                'allusers'=>$allusers);

        if (!empty($jobqueueRec["jq_endtime"])) {
          $numSecs = strtotime($jobqueueRec['jq_endtime']) - strtotime($jobqueueRec['jq_starttime']);
        }else{
          $numSecs = time()  - strtotime($jobqueueRec['jq_starttime']);
        } 
        
        $itemsPerSec = null;
        if ($jobqueueRec['jq_starttime']){
          $itemsPerSec = $this->showJobsDao->getNumItemsPerSec($jobqueueRec['jq_itemsprocessed'], $numSecs);
          $varJobQueueRow['itemsPerSec'] = $itemsPerSec;
        }
        if (empty($jobqueueRec['jq_endtime'])) {
          $varJobQueueRow['eta'] = $this->showJobsDao->getEstimatedTime($jobId, $jobqueueRec['jq_type'], $itemsPerSec, $job['job']['job_upload_fk']);
        }
        $varJobQueueRow['canDoActions'] = 
                ($_SESSION[Auth::USER_LEVEL] == PLUGIN_DB_ADMIN) || (Auth::getUserId() == $job['job']['job_user_fk']);
        $varJobQueueRow['isInProgress'] = ($jobqueueRec['jq_end_bits'] == 0);
        $varJobQueueRow['isReady'] = ($jobqueueRec['jq_end_bits'] == 1);
        
        switch($jobqueueRec['jq_type'])
        {
          case 'readmeoss':
            $varJobQueueRow['download'] = "ReadMeOss";
            break;
          case 'spdx2':
            $varJobQueueRow['download'] = "SPDX2 report";
            break;
          case 'spdx2tv':
            $varJobQueueRow['download'] = "SPDX2 tag/value report";
            break;
          case 'dep5':
            $varJobQueueRow['download'] = "DEP5 copyright file";
            break;
          default:
            $varJobQueueRow['download'] = "";
        }
        
        $outBuf .= $this->renderString('ui-showjobs-jobqueue-row.html.twig', $varJobQueueRow);
      }
    }
    if ($numJobs > $this->maxUploadsPerPage) {
      $pagination = "<p>" . MenuEndlessPage($page, $next, $uriFullMenu);
    }

    return array('showJobsData' => $outBuf, 'pagination' => $pagination);
  } /* getShowJobsForEachJob() */

  /**
   * @brief Are there any unfinished jobqueues in this job?
   * @param $job
   * @return true if $job contains unfinished jobqueue's
   **/
  protected function isUnfinishedJob($job)
  {
    foreach ($job['jobqueue'] as $jobqueueRec){
      if ($jobqueueRec['jq_end_bits'] === 0) {
        return true;
      }
    } 
    return false;
  }  /* isUnfinishedJob()  */


  /**
   * @brief array $jobqueueRec get the jobqueue row color
   * @return string css class
   **/
  protected function getClass($jobqueueRec)
  {
    if ($jobqueueRec['jq_end_bits'] > 1){
      return 'jobFailed';
    }
    else if (!empty($jobqueueRec['jq_starttime']) && empty($jobqueueRec['jq_endtime'])){
      return 'jobScheduled';
    }
    else if (!empty($jobqueueRec['jq_starttime']) && !empty($jobqueueRec['jq_endtime'])) {
      return 'jobFinished';
    }
    else {
      return 'jobQueued';
    }
  }


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
    
    $allusers = 0;
    if ($uploadPk>0){
        $upload_pks = array($uploadPk);
        $jobs = $this->showJobsDao->uploads2Jobs($upload_pks, $page);
    }else{
      $allusers = GetParm("allusers", PARM_INTEGER); 
      $jobs = $this->showJobsDao->myJobs($allusers);
    } 
    $jobsInfo = $this->showJobsDao->getJobInfo($jobs, $page);
    usort($jobsInfo, array($this,"compareJobsInfo"));
      
    $showJobData = $this->getShowJobsForEachJob($jobsInfo, $page, $allusers);
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
    header('Content-type: text/json');
    return $output;
  }

  protected function jsonContent()
  {
    $action = GetParm("do", PARM_STRING);
    switch ($action){
      case "showjb":
        $uploadPk = GetParm('upload',PARM_INTEGER);
        if(!empty($uploadPk)){ 
          return $this->getJobs($uploadPk);
        }
        break;
      case "showSingleJob":
        $job_pk1 = GetParm('jobId',PARM_INTEGER);
        return $this->getGeekyScanDetailsForJob($job_pk1);
    }
  }
}

$NewPlugin = new AjaxShowJobs();
