<?php
/*
 SPDX-FileCopyrightText: © 2012-2014 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015-2016 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/


define("TITLE_SHOWJOBS", _("Show Jobs"));

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\ShowJobsDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\JobDao;
use Fossology\Lib\Db\DbManager;

class showjobs extends FO_Plugin
{
  /** @var ShowJobsDao */
  private $showJobsDao;
  /** @var UploadDao */
  private $uploadDao;
  /** @var JobDao */
  private $jobDao;

  function __construct()
  {
    $this->Name       = "showjobs";
    $this->Title      = TITLE_SHOWJOBS;
    $this->MenuOrder  = 5;
    $this->Dependency = array("browse");
    $this->DBaccess   = PLUGIN_DB_WRITE;

    global $container;
    $this->showJobsDao = $container->get('dao.show_jobs');
    $this->uploadDao = $container->get('dao.upload');
    $this->jobDao = $container->get('dao.job');

    parent::__construct();
  }

  function RegisterMenus()
  {
    menu_insert("Main::Jobs::My Recent Jobs", $this->MenuOrder - 1, $this->Name,
      $this->MenuTarget);

    if (array_key_exists(Auth::USER_LEVEL, $_SESSION) &&
      $_SESSION[Auth::USER_LEVEL] == PLUGIN_DB_ADMIN) {
      $URIpart = $this->Name . Traceback_parm_keep(array(
        "page"
      )) . "&allusers=";

      menu_insert("Main::Jobs::All Recent Jobs", $this->MenuOrder - 2,
        $URIpart . '1', $this->MenuTarget);
    }

  } // RegisterMenus()

  /**
   * @brief Returns uploadname as link for geeky scan
   * @param $job_pk
   * @return string uploadname
   **/
  function getUploadNameForGeekyScan($job_pk)
  {
    $row = $this->showJobsDao->getDataForASingleJob($job_pk);

    if (empty($row["job_upload_fk"])) {
      return '';
    }

    if (empty($row['jq_pk'])) {
      return _("Job history record is no longer available");
    }

    /* get the upload filename */
    $uploadFileName = htmlspecialchars($this->uploadDao->getUpload($row['job_upload_fk'])->getFilename());
    if (empty($uploadFileName)) {
      /* upload has been deleted so try to get the job name from the original upload job record */
      $jobName = $this->showJobsDao->getJobName($row["job_upload_fk"]);
      $uploadFileName = "Deleted " . $jobName;
    }

    $uploadtree_pk = -1;
    /* Find the uploadtree_pk for this upload so that it can be used in the browse link */
    try {
      $uploadtree_pk = $this->uploadDao->getUploadParent($row['job_upload_fk']);
    } catch (Exception $e) {
      echo $e->getMessage(), "\n";
    }

    /* upload file name link to browse */
    return "<a title='Click to browse this upload' href='" . Traceback_uri() . "?mod=browse&upload=" . $row['job_upload_fk'] . "&item=" . $uploadtree_pk . "'>" . $uploadFileName . "</a>";
  } // getUploadNameForGeekyScan()

  public function Output()
  {
    $page = "";
    $uploadPk = GetParm('upload', PARM_INTEGER);
    if (empty($uploadPk)) {
      $uploadPk = - 1;
    } elseif ($uploadPk > 0) {
      if (! $this->uploadDao->isEditable($uploadPk, Auth::getGroupId())) {
        $this->vars['message'] = _("Permission Denied");
        return;
      }
    }
    $this->vars['uploadId']= $uploadPk;

    /* Process any actions */
    $action = GetParm("action",PARM_STRING);
    $page = GetParm('page',PARM_INTEGER) ?: 0;
    if ($_SESSION[Auth::USER_LEVEL] >= PLUGIN_DB_WRITE && !empty($action)) {
      $jq_pk = GetParm("jobid",PARM_INTEGER);
      $uploadPk = GetParm("upload",PARM_INTEGER);

      if (!($uploadPk === -1 &&
            ($_SESSION[Auth::USER_LEVEL] >= PLUGIN_DB_ADMIN ||
             $this->jobDao->hasActionPermissionsOnJob($jq_pk, Auth::getUserId(), Auth::getGroupId()))) &&
          !$this->uploadDao->isEditable($uploadPk, Auth::getGroupId())) {
        $this->vars['message'] = _("Permission Denied to perform action");
      } else {
        $thisURL = Traceback_uri() . "?mod=" . $this->Name . "&upload=$uploadPk";
        switch($action) {
          case 'pause':
            if (empty($jq_pk)) {
              break;
            }
            $command = "pause $jq_pk";
            $rv = fo_communicate_with_scheduler($command, $response_from_scheduler, $error_info);
            if ($rv == false) {
              $this->vars['errorInfo'] =  _("Unable to pause job.") . " " . $response_from_scheduler . $error_info;
            }
            echo "<script type=\"text/javascript\"> window.location.replace(\"$thisURL\"); </script>";
            break;

          case 'restart':
            if (empty($jq_pk)) {
              break;
            }
            $command = "restart $jq_pk";
            $rv = fo_communicate_with_scheduler($command, $response_from_scheduler, $error_info);
            if ($rv == false) {
              $this->vars['errorInfo'] =  _("Unable to restart job.") . " " . $response_from_scheduler . $error_info;
            }
            echo "<script type=\"text/javascript\"> window.location.replace(\"$thisURL\"); </script>";
            break;

          case 'cancel':
            if (empty($jq_pk)) {
              break;
            }
            $Msg = "\"" . _("Killed by") . " " . $_SESSION[Auth::USER_NAME] . "\"";
            $jobId = $this->jobDao->getJobIdFromJobQueue($jq_pk);
            if (empty($jobId)) {
              $this->vars['errorInfo'] =  _("Unable to find job for queue item: ") . $jq_pk;
              break;
            }
            try {
              $jobData = $this->showJobsDao->getJobInfo([$jobId]);
              $jobsToCancel = [];
              if (!empty($jobData)) {
                $jobInfo = reset($jobData);
                if (isset($jobInfo['jobqueue'])) {
                  // Find all jobs that depend on the cancelled job (directly or indirectly)
                  foreach ($jobInfo['jobqueue'] as $jobQueue) {
                    if (in_array($jq_pk, $jobQueue["depends"])) {
                      $jobsToCancel[] = $jobQueue["jq_pk"];
                    }
                  }
                  // Also find jobs that depend on jobs we're about to cancel (recursive dependency)
                  $changed = true;
                  while ($changed) {
                    $changed = false;
                    foreach ($jobInfo['jobqueue'] as $jobQueue) {
                      $currentJqPk = $jobQueue["jq_pk"];
                      if (!in_array($currentJqPk, $jobsToCancel) && !empty(array_intersect($jobQueue["depends"], $jobsToCancel))) {
                        $jobsToCancel[] = $currentJqPk;
                        $changed = true;
                      }
                    }
                  }
                }
              }
              $command = "kill $jq_pk $Msg";
              $rv = fo_communicate_with_scheduler($command, $response_from_scheduler, $error_info);
              if ($rv == false) {
                $this->vars['errorInfo'] =  _("Unable to cancel job.") . $response_from_scheduler . $error_info;
              }
              foreach ($jobsToCancel as $dependentJqPk) {
                $command = "kill $dependentJqPk $Msg";
                $rv = fo_communicate_with_scheduler($command, $response_from_scheduler, $error_info);
                if ($rv == false) {
                  $this->vars['errorInfo'] =  _("Unable to cancel dependent job.") . $response_from_scheduler . $error_info;
                }
              }
            } catch (Exception $e) {
              $this->vars['errorInfo'] =  _("Error during cancellation: ") . $e->getMessage();
            }
            echo "<script type=\"text/javascript\"> window.location.replace(\"$thisURL\"); </script>";
            break;
        }
      }
    }
    $job = GetParm('job', PARM_INTEGER);
    if (! empty($job)) {
      $this->vars['jobId'] = $job;
      $this->vars['uploadName'] = $this->getUploadNameForGeekyScan($job);
    } else {
      $allusersval = GetParm("allusers", PARM_INTEGER);
      if (! $allusersval) {
        $allusersval = 0;
      }
      $this->vars['allusersval'] = $allusersval;
      if (! $page) {
        $page = 0;
      }
      $this->vars['page'] = $page;
      $this->vars['clockTime'] = $this->getTimeToRefresh();
      $this->vars['allusersdiv'] = menu_to_1html(
        menu_find($this->Name, $MenuDepth), 0);
      $this->vars['injectedFoot'] = GetParm("injectedFoot",PARM_TEXT);
      $this->vars['message'] = GetParm("injectedMessage",PARM_TEXT);
    }
  }

  /**
   * @brief getTimeToRefresh() get the refresh time from DB.
   * @Returns time in seconds to refresh the jobs.
   **/
  public function getTimeToRefresh()
  {
    global $SysConf;
    return $SysConf['SYSCONFIG']['ShowJobsAutoRefresh'];
  } /* getTimeToRefresh() */

  public function getTemplateName()
  {
    $job = GetParm('job', PARM_INTEGER);
    if (empty($job)) {
      return "ui-showjobs.html.twig";
    } else {
      return "ui-job-show.html.twig";
    }
  }
}

$NewPlugin = new showjobs;
$NewPlugin->Initialize();
