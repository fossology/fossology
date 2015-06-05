<?php
/***********************************************************
 Copyright (C) 2012-2014 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015 Siemens AG

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


define("TITLE_showjobs", _("Show Jobs"));

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Dao\ShowJobsDao;

class showjobs extends FO_Plugin
{
  /** @var ShowJobsDao */
  private $showJobsDao;

  function __construct()
  {
    $this->Name       = "showjobs";
    $this->Title      = TITLE_showjobs;
    $this->MenuOrder  = 5;
    $this->Dependency = array("browse");
    $this->DBaccess   = PLUGIN_DB_WRITE;

    global $container;
    $this->showJobsDao = $container->get('dao.show_jobs');

    parent::__construct();
  }


  function RegisterMenus()
  {
    menu_insert("Main::Jobs::My Recent Jobs",$this->MenuOrder -1,$this->Name, $this->MenuTarget);

    if ($_SESSION[Auth::USER_LEVEL] != PLUGIN_DB_ADMIN) return;

    if (GetParm("mod", PARM_STRING) == $this->Name){
      /* Set micro menu to select either all users or this user */
      $allusers = GetParm("allusers",PARM_INTEGER);
      if ($allusers == 0){
        $text = _("Show uploads from all users");
        $URI = $this->Name . Traceback_parm_keep(array( "page" )) . "&allusers=1";
      }else{
        $text = _("Show only your own uploads");
        $URI = $this->Name . Traceback_parm_keep(array( "page")) . "&allusers=0";
      }
      menu_insert("showjobs::$text", 1, $URI, $text);
    }

  } // RegisterMenus()


  /**
   * @brief Returns geeky scan details about the jobqueue item
   * @param $job_pk
   * @return Return job and jobqueue record data in an html table.
   **/
  function showJobDB($job_pk)
  {
    global $container;
    /** @var DbManager */
    $dbManager = $container->get('db.manager');
    
    $statementName = __METHOD__."ShowJobDBforjob";
    $dbManager->prepare($statementName,
    "SELECT *, jq_endtime-jq_starttime as elapsed FROM jobqueue LEFT JOIN job ON job.job_pk = jobqueue.jq_job_fk WHERE jobqueue.jq_pk = $1");
    $result = $dbManager->execute($statementName, array($job_pk));
    $row = $dbManager->fetchArray($result);
    $dbManager->freeResult($result);

    if (!empty($row["job_upload_fk"])){
      /* get the upload filename */
      $statementName = __METHOD__."upload_filenameforShowJobDB";
      $dbManager->prepare($statementName,
      "select upload_filename, upload_desc from upload where upload_pk =$1");
      $uploadresult = $dbManager->execute($statementName, array($row['job_upload_fk']));
      $uploadRow = $dbManager->fetchArray($uploadresult);
      if (empty($uploadRow)){
        /* upload has been deleted so try to get the job name from the original upload job record */
        $jobName = $this->showJobsDao->getJobName($row["job_upload_fk"]);
        $upload_filename = "Deleted " . $jobName;
        $upload_desc = '';
      }else{
        $upload_filename = $uploadRow['upload_filename'];
        $upload_desc = $uploadRow['upload_desc'];
      }
      $dbManager->freeResult($uploadresult);

      if (empty($row['jq_pk'])){ 
        return _("Job history record is no longer available"); 
      }

      $uploadtree_tablename = GetUploadtreeTableName($row['job_upload_fk']);
      if (NULL == $uploadtree_tablename) strcpy($uploadtree_tablename, "uploadtree");

      /* Find the uploadtree_pk for this upload so that it can be used in the browse link */
      $statementName = __METHOD__."uploadtreeRec";
      $uploadtreeRec = $dbManager->getSingleRow(
      "select * from $uploadtree_tablename where parent is NULL and upload_fk=$1",
      array($row['job_upload_fk']),
      $statementName
      );
      $uploadtree_pk = $uploadtreeRec['uploadtree_pk'];
    }
    /* upload file name link to browse */
    if (!empty($row['job_upload_fk'])){
      $uploadTreeName = "";      
      $uploadTreeName = "<a title='Click to browse this upload' href='" . Traceback_uri() . "?mod=browse&upload=" . $row['job_upload_fk'] . "&item=" . $uploadtree_pk . "'>" . $upload_filename . "</a>";
      return $uploadTreeName;
    }    
  } // showJobDB()


  public function Output()
  {
    $v="";
    $page = "";
    $uploadPk = GetParm('upload',PARM_INTEGER);
    if (empty($uploadPk)){ 
      $uploadPk = -1; 
    }else{
      $uploadPerm = GetUploadPerm($uploadPk);
      if ($uploadPerm < Auth::PERM_WRITE){
        $text = _("Permission Denied");
        return "<h2>$text<h2>";
      }
    }

    $this->vars['uploadId']= $uploadPk;
    // micro menus
    $v .= menu_to_1html(menu_find($this->Name, $MenuDepth),0);

    /* Process any actions */
    if ($_SESSION[Auth::USER_LEVEL] >= PLUGIN_DB_WRITE){

      $jq_pk = GetParm("jobid",PARM_INTEGER);
      $action = GetParm("action",PARM_STRING);
      $uploadPk = GetParm("upload",PARM_INTEGER);
      if (!empty($uploadPk)){
        $uploadPerm = GetUploadPerm($uploadPk);
        if ($uploadPerm < Auth::PERM_WRITE){
          $text = _("Permission Denied");
          echo "<h2>$text<h2>";
          return;
        }
      }
      $page = GetParm('page',PARM_INTEGER);
      if (empty($page)) $page = 0;
      $jqtype = GetParm("jqtype",PARM_STRING);
      $thisURL = Traceback_uri() . "?mod=" . $this->Name . "&upload=$uploadPk";
      $job = GetParm('job',PARM_INTEGER);
      switch($action)
      {
        case 'pause':
          if (empty($jq_pk)) break;
          $command = "pause $jq_pk";
          $rv = fo_communicate_with_scheduler($command, $response_from_scheduler, $error_info);
          if ($rv == false) $this->vars['errorInfo'] =  _("Unable to pause job.") . " " . $response_from_scheduler . $error_info;
          echo "<script type=\"text/javascript\"> window.location.replace(\"$thisURL\"); </script>";
          break;

        case 'restart':
          if (empty($jq_pk)) break;
          $command = "restart $jq_pk";
          $rv = fo_communicate_with_scheduler($command, $response_from_scheduler, $error_info);
          if ($rv == false) $this->vars['errorInfo'] =  _("Unable to restart job.") . " " . $response_from_scheduler . $error_info;
          echo "<script type=\"text/javascript\"> window.location.replace(\"$thisURL\"); </script>";
          break;

        case 'cancel':
          if (empty($jq_pk)) break;
          $Msg = "\"" . _("Killed by") . " " . $_SESSION[Auth::USER_NAME] . "\"";
          $command = "kill $jq_pk $Msg";
          $rv = fo_communicate_with_scheduler($command, $response_from_scheduler, $error_info);
          if ($rv == false) $this->vars['errorInfo'] =  _("Unable to cancel job.") . $response_from_scheduler . $error_info; 
          echo "<script type=\"text/javascript\"> window.location.replace(\"$thisURL\"); </script>";
          break;
        default:
          break;
      }
    }
    if (!empty($job)){
      $this->vars['jobId'] = $job;
      $this->vars['uploadName'] = $this->showJobDB($job);
    }else{
      $allusersval=GetParm("allusers",PARM_INTEGER);
      if(!$allusersval) $allusersval = 0;
      $this->vars['allusersval'] = $allusersval;
      if(!$page) $page=0;
      $this->vars['page'] = $page;
      $this->vars['clockTime'] = $this->getTimeToRefresh();
      $this->vars['allusersdiv'] = menu_to_1html(menu_find($this->Name, $MenuDepth),0);  
    }
  }


  /**
   * @brief getTimeToRefresh() get the refresh time from DB.
   * @Returns time in seconds to refresh the jobs.
   **/
  public function getTimeToRefresh()
  {
    global $container;
    /** @var DbManager */
    $dbManager = $container->get('db.manager');

    $result = $dbManager->getSingleRow(
    "SELECT conf_value FROM sysconfig WHERE variablename = 'ShowJobsAutoRefresh' LIMIT 1"
    );
    return $result['conf_value'];
  } /* getTimeToRefresh() */


  public function getTemplateName()
  {
    $job = GetParm('job', PARM_INTEGER);
    if (empty($job))
      return "ui-showjobs.html.twig";
    else
      return "ui-job-show.html.twig";
  }

}

$NewPlugin = new showjobs;
$NewPlugin->Initialize();
