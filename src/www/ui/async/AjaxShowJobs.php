<?php
/*
 SPDX-FileCopyrightText: © 2015-2019, 2021 Siemens AG
 SPDX-FileCopyrightText: © 2020 Robert Bosch GmbH
 SPDX-FileCopyrightText: © Dineshkumar Devarajan <Devarajan.Dineshkumar@in.bosch.com>
 Author: Shaheem Azmal<shaheem.azmal@siemens.com>,
         Anupam Ghosh <anupam.ghosh@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @namespace Fossology::UI::Ajax
 * Holds the pages which are called in asynchronous method.
 */
namespace Fossology\UI\Ajax;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\ShowJobsDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Db\DbManager;
use Symfony\Component\HttpFoundation\JsonResponse;

define("TITLE_AJAXSHOWJOBS", _("ShowJobs"));

/**
 * @file
 * @brief Provide data for jobs table.
 * @class AjaxShowJobs
 * @brief Provide data for jobs table.
 */
class AjaxShowJobs extends \FO_Plugin
{
  const MAX_LOG_OUTPUT = 32768;

  /** @var dbManager */
  private $dbManager;

  /** @var ShowJobsDao */
  private $showJobsDao;

  /** @var UserDao */
  private $userDao;

  /** @var ClearingDao */
  private $clearingDao;

  function __construct()
  {
    $this->Name = "ajaxShowJobs";
    $this->Title = TITLE_AJAXSHOWJOBS;
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->LoginFlag = 0;
    $this->NoMenu = 0;
    $this->OutputType = 'JSON';
    $this->OutputToStdout = true;

    global $container;
    $this->showJobsDao = $container->get('dao.show_jobs');
    $this->userDao = $container->get('dao.user');
    $this->clearingDao = $container->get('dao.clearing');
    $this->dbManager = $container->get('db.manager');

    parent::__construct();
  }

  function OutputOpen()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return null;
    }
    $uploadId = GetParm("upload", PARM_INTEGER);
    if (empty($uploadId)) {
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
    foreach ($fields as $labelKey=>$field) {
      $value ="";
      $label = $labelKey;
      switch ($field) {
        case 'job_queued':
        case 'jq_starttime':
        case 'jq_endtime':
          if (! empty($row[$field])) {
            $value = Convert2BrowserTime($row[$field]);
          }
          break;
        case 'jq_itemsprocessed':
          $value = number_format($row[$field]);
          break;
        case 'jq_end_bits':
          $value = $this->jobqueueStatus($row);
          break;
        case 'jq_pk':
          if (!empty($row['job_upload_fk'])) {
            $value = "<a href='$uri" . $row['job_upload_fk'] . "'>" . htmlentities($row[$field]) . "</a>"." (" . _("Click to view jobs for this upload") . ")";
          } else {
            $uri2 = Traceback_uri() . "?mod=showjobs";
            $back = "(" . _("Click to return to Show Jobs") . ")";
            $value = "<a href='$uri2'>$row[$field] $back</a>";
          }
          break;
        case 'job_upload_fk':
          if (!empty($row[$field])) {
            $browse = Traceback_uri() . "?mod=browse&upload=" . htmlentities($row[$field]);
            $value = "<a href='$browse'>" . htmlentities($row[$field]) . "</a>"." (" . _("Click to browse upload") . ")";
          }
          break;
        case 'jq_log':
          if (empty($row[$field]) || $row[$field] == 'removed' || !file_exists($row[$field])) {
            break;
          }
          if (filesize($row[$field]) > self::MAX_LOG_OUTPUT) {
            $value = "<pre>" .file_get_contents($row[$field],false,null,-1,self::MAX_LOG_OUTPUT)."</pre>"
                    .'<a href="'.Traceback_uri() . '?mod=download&log=' . $row['jq_pk'] . '">Download full log</a>';
          } else {
            $value = "<pre>" . file_get_contents($row[$field]). "</pre>";
          }
          break;
        case 'job_user_fk':
          if (!empty($row[$field])) {
            $value = $this->userDao->getUserName($row[$field]);
          }
          break;
        case 'jq_args':
          $jq_args_temp = $row[$field];
          $jq_args_show = $jq_args_temp;
          if (! empty($jq_args_temp)) {
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
          if (array_key_exists($field, $row)) {
            $value = htmlentities($row[$field]);
          }
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
   * @brief Returns an upload job status in array for API or browser
   * @param array $jobData
   * @param bool $forApi
   * @return Returns an upload job status in array for API or browser
   **/
  public function getShowJobsForEachJob($jobData, $forApi = false)
  {
    if (count($jobData) == 0) {
      return array('showJobsData' => "There are no jobs to display");
    }
    $returnData = [];
    foreach ($jobData as $jobId => $jobs) {
      $jobArr = array(
        'jobId' => $jobs['job']['job_pk'],
        'jobName' => $jobs['job']['job_name'],
        'jobQueue' => $jobs['jobqueue']
      );
      foreach ($jobArr['jobQueue'] as $key => $singleJobQueue) {
        if (! $forApi) {
          if (! empty($jobArr['jobQueue'][$key]['jq_starttime'])) {
            $jobArr['jobQueue'][$key]['jq_starttime'] = Convert2BrowserTime(
              $jobArr['jobQueue'][$key]['jq_starttime']);
          }
          if (! empty($jobArr['jobQueue'][$key]['jq_endtime'])) {
            $jobArr['jobQueue'][$key]['jq_endtime'] = Convert2BrowserTime(
              $jobArr['jobQueue'][$key]['jq_endtime']) ;
          }
        }
        if (! empty($singleJobQueue["jq_endtime"])) {
          $numSecs = strtotime($singleJobQueue['jq_endtime']) -
            strtotime($singleJobQueue['jq_starttime']);
          $numSecs = ($numSecs == 0) ? 1 : $numSecs; // If difference is in milliseconds
        } else {
          $numSecs = time() - strtotime($singleJobQueue['jq_starttime']);
        }

        $jobArr['jobQueue'][$key]['itemsPerSec'] = $itemsPerSec = 0;
        if ($singleJobQueue['jq_starttime']) {
          $itemsPerSec = $this->showJobsDao->getNumItemsPerSec(
            $singleJobQueue['jq_itemsprocessed'], $numSecs);
          $jobArr['jobQueue'][$key]['itemsPerSec'] = $itemsPerSec;
        }
        if (empty($singleJobQueue['jq_endtime'])) {
          $jobArr['jobQueue'][$key]['eta'] = $this->showJobsDao->getEstimatedTime(
            $singleJobQueue['jq_job_fk'], $singleJobQueue['jq_type'],
            $itemsPerSec, $jobs['job']['job_upload_fk']);
          if ($singleJobQueue['jq_type'] === 'monkbulk' ||
            $singleJobQueue['jq_type'] === 'deciderjob') {
            $noOfMonkBulk = $this->showJobsDao->getItemsProcessedForDecider(
              'decider', $singleJobQueue['jq_job_fk']);
            if (! empty($noOfMonkBulk)) {
              $totalCountOfMb = $this->clearingDao->getPreviousBulkIds(
                $noOfMonkBulk[1], Auth::getGroupId(), Auth::getUserId(),
                $onlyCount = 1);
            }
            if (! empty($totalCountOfMb)) {
              $jobArr['jobQueue'][$key]['isNoOfMonkBulk'] = $noOfMonkBulk[0] .
                "/" . $totalCountOfMb;
            }
          }
        }

        $jobArr['jobQueue'][$key]['canDoActions'] = ($_SESSION[Auth::USER_LEVEL] ==
          PLUGIN_DB_ADMIN) || (Auth::getUserId() == $jobs['job']['job_user_fk']);
        $jobArr['jobQueue'][$key]['isInProgress'] = ($singleJobQueue['jq_end_bits'] ==
          0);
        $jobArr['jobQueue'][$key]['isReady'] = ($singleJobQueue['jq_end_bits'] ==
          1);

        switch ($singleJobQueue['jq_type']) {
          case 'readmeoss':
            $jobArr['jobQueue'][$key]['download'] = "ReadMeOss";
            break;
          case 'spdx2':
            $jobArr['jobQueue'][$key]['download'] = "SPDX2 report";
            break;
          case 'spdx2tv':
            $jobArr['jobQueue'][$key]['download'] = "SPDX2 tag/value report";
            break;
          case 'spdx2csv':
            $jobArr['jobQueue'][$key]['download'] = "SPDX2 CSV report";
            break;
          case 'dep5':
            $jobArr['jobQueue'][$key]['download'] = "DEP5 copyright file";
            break;
          case 'spdx3jsonld':
            $jobArr['jobQueue'][$key]['download'] = "SPDX3 JSON-LD report";
            break;
          case 'spdx3json':
            $jobArr['jobQueue'][$key]['download'] = "SPDX3 JSON report";
            break;
          case 'spdx3rdf':
            $jobArr['jobQueue'][$key]['download'] = "SPDX3 RDF report";
            break;
          case 'spdx3tv':
            $jobArr['jobQueue'][$key]['download'] = "SPDX3 tag/value report";
            break;
          case 'reportImport':
            $jobArr['jobQueue'][$key]['download'] = "uploaded SPDX2 report";
            break;
          case 'unifiedreport':
            $jobArr['jobQueue'][$key]['download'] = "Unified Report";
            break;
          case 'clixml':
            $jobArr['jobQueue'][$key]['download'] = "Clixml Report";
            break;
          case 'cyclonedx':
            $jobArr['jobQueue'][$key]['download'] = "CycloneDX json Report";
            break;
          case 'decisionexporter':
            $jobArr['jobQueue'][$key]['download'] = "FOSSology Decisions";
            break;
          default:
            $jobArr['jobQueue'][$key]['download'] = "";
        }
      }
      if (! empty($jobs['upload'])) {
        $uploadArr = array(
          'uploadName' => $jobs['upload']['upload_filename'],
          'uploadId' => $jobs['upload']['upload_pk'],
          'uploadDesc' => $jobs['upload']['upload_desc'],
          'uploadItem' => empty($jobs['uploadtree']) ? -1 : $jobs['uploadtree']['uploadtree_pk'],
          'uploadEta' => $this->showJobsDao->getEstimatedTime($jobs['job']['job_pk'], '', 0, $jobs['upload']['upload_pk'])
        );
      } else {
        $uploadArr = null;
      }
      $returnData[] = array(
        'job' => $jobArr,
        'upload' => $uploadArr,
      );
    }
    return $returnData;
  } /* getShowJobsForEachJob() */

  /**
   * @brief Are there any unfinished jobqueues in this job?
   * @param $job
   * @return true if $job contains unfinished jobqueue's
   **/
  protected function isUnfinishedJob($job)
  {
    foreach ($job['jobqueue'] as $jobqueueRec) {
      if ($jobqueueRec['jq_end_bits'] === 0) {
        return true;
      }
    }
    return false;
  }

    /* isUnfinishedJob() */

  /**
   * @brief array $jobqueueRec get the jobqueue row color
   * @return string css class
   */
  protected function getClass($jobqueueRec)
  {
    if ($jobqueueRec['jq_end_bits'] > 1) {
      return 'jobFailed';
    } else if (! empty($jobqueueRec['jq_starttime']) &&
      empty($jobqueueRec['jq_endtime'])) {
      return 'jobScheduled';
    } else if (!empty($jobqueueRec['jq_starttime']) && !empty($jobqueueRec['jq_endtime'])) {
      return 'jobFinished';
    } else {
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

    /*
     * check the jobqueue status. If the job is finished, return the status.
     */
    if (! empty($jobqueueRec['jq_endtext'])) {
      $status .= "$jobqueueRec[jq_endtext]";
    }

    if (! strstr($status, "Success") && ! strstr($status, "Fail") &&
      $jobqueueRec["jq_end_bits"]) {
      $status .= "<br>";
      if ($jobqueueRec["jq_end_bits"] == 0x1) {
        $status .= _("Success");
      } else if ($jobqueueRec["jq_end_bits"] == 0x2) {
        $status .= _("Failure");
      } else if ($jobqueueRec["jq_end_bits"] == 0x4) {
        $status .= _("Nonfatal");
      }
    }
    return $status;
  }

    /* jobqueueStatus() */

  /**
   * @brief get data of all jobs using uploadpk
   * @return a json jobqueue data.
   */
  protected function getJobs($uploadPk)
  {
    $page = GetParm('page', PARM_INTEGER);
    $allusers = GetParm("allusers", PARM_INTEGER);
    $uri = "?mod=showjobs";
    if (!empty($allusers) && $allusers > 0) {
      $uri .= "&allusers=$allusers";
    }
    if ($uploadPk > 0) {
      $uri .= "&upload=$uploadPk";
    }

    if (empty($allusers)) {
      $allusers = 0;
    }
    $totalPages = 0;
    if ($uploadPk > 0) {
      $upload_pks = array($uploadPk);
      list($jobs, $totalPages) = $this->showJobsDao->uploads2Jobs($upload_pks, $page);
    } else {
      list($jobs, $totalPages) = $this->showJobsDao->myJobs($allusers, $page);
    }
    $jobsInfo = $this->showJobsDao->getJobInfo($jobs);
    usort($jobsInfo, array($this,"compareJobsInfo"));

    $pagination = ($totalPages > 1 ? MenuPage($page, $totalPages, $uri) : "");

    $showJobData = $this->getShowJobsForEachJob($jobsInfo, false);
    return new JsonResponse(
      array(
        'showJobsData' => $showJobData,
        'pagination' => $pagination
      ));
  } /* getJobs()*/

  public function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return 0;
    }
    $output = $this->jsonContent();
    if (!$this->OutputToStdout) {
      return;
    }
    return $output;
  }

  protected function jsonContent()
  {
    $action = GetParm("do", PARM_STRING);
    switch ($action) {
      case "showjb":
        $uploadPk = GetParm('upload', PARM_INTEGER);
        if (! empty($uploadPk)) {
          return $this->getJobs($uploadPk);
        }
        break;
      case "showSingleJob":
        $job_pk1 = GetParm('jobId', PARM_INTEGER);
        return $this->getGeekyScanDetailsForJob($job_pk1);
    }
  }
}

$NewPlugin = new AjaxShowJobs();
