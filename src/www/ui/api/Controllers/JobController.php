<?php
/*
 SPDX-FileCopyrightText: © 2018 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>
 SPDX-FileCopyrightText: © 2022 Samuel Dushimimana <dushsam100@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Controller for job queries
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\ShowJobsDao;
use Fossology\Lib\Db\DbManager;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\UploadHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\JobQueue;
use Fossology\UI\Api\Models\ShowJob;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Request;

/**
 * @class JobController
 * @brief Controller for Job model
 */
class JobController extends RestController
{
  /**
   * Get query parameter name for upload filtering
   */
  const UPLOAD_PARAM = "upload";
  /**
   * Job completed successfully
   */
  const JOB_COMPLETED = 0x1 << 1;
  /**
   * Job started by scheduler
   */
  const JOB_STARTED = 0x1 << 2;
  /**
   * Job waiting to be queued
   */
  const JOB_QUEUED = 0x1 << 3;
  /**
   * Job failed
   */
  const JOB_FAILED = 0x1 << 4;

  /**
   * Get all jobs created by all the users
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getAllJobs($request, $response, $args)
  {
    if (Auth::isAdmin()) {
      $id = null;
      $limit = 0;
      $page = 1;
      if ($request->hasHeader('limit')) {
        $limit = $request->getHeaderLine('limit');
        $page = $request->getHeaderLine('page');
        if (empty($page)) {
          $page = 1;
        }
        if ((isset($limit) && (! is_numeric($limit) || $limit < 0)) ||
            (! is_numeric($page) || $page < 1)) {
            $returnVal = new Info(400,
              "Limit and page cannot be smaller than 1 and has to be numeric!",
              InfoType::ERROR);
            return $response->withJson($returnVal->getArray(), $returnVal->getCode());
        }
      }
      return $this->getAllResults($id, $response, $limit, $page);
    } else {
      $returnVal = new Info(403,'Access Denied', InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }
  }

  /**
   * Get all jobs by a user
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getJobs($request, $response, $args)
  {
    $query = $request->getQueryParams();
    $userId = $this->restHelper->getUserId();
    $limit = 0;
    $page = 1;
    if ($request->hasHeader('limit')) {
      $limit = $request->getHeaderLine('limit');
      $page = $request->getHeaderLine('page');
      if (empty($page)) {
        $page = 1;
      }
      if ((isset($limit) && (! is_numeric($limit) || $limit < 0)) ||
        (! is_numeric($page) || $page < 1)) {
        $returnVal = new Info(400,
          "Limit and page cannot be smaller than 1 and has to be numeric!",
          InfoType::ERROR);
        return $response->withJson($returnVal->getArray(), $returnVal->getCode());
      }
    }

    $id = null;
    if (isset($args['id'])) {
      $id = intval($args['id']);
      if (! $this->dbHelper->doesIdExist("job", "job_pk", $id)) {
        $returnVal = new Info(404, "Job id " . $id . " doesn't exist",
          InfoType::ERROR);
        return $response->withJson($returnVal->getArray(), $returnVal->getCode());
      }
    }

    if ($id !== null) {
      /* If the ID is passed, don't check for upload */
      return $this->getAllResults($id, $response, $limit, $page);
    }

    if (array_key_exists(self::UPLOAD_PARAM, $query)) {
      /* If the upload is passed, filter accordingly */
      return $this->getFilteredResults(intval($query[self::UPLOAD_PARAM]),
        $response, $limit, $page);
    } else {
      $id = null;
      return $this->getAllUserResults($id,$userId, $response, $limit, $page);
    }
  }

  /**
   * Create a new job
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function createJob($request, $response, $args)
  {
    $folder = $request->getHeaderLine("folderId");
    $upload = $request->getHeaderLine("uploadId");
    if (is_numeric($folder) && is_numeric($upload) && $folder > 0 && $upload > 0) {
      $scanOptionsJSON = $this->getParsedBody($request);
      if (empty($scanOptionsJSON)) {
        $error = new Info(403, "No agents selected!", InfoType::ERROR);
        return $response->withJson($error->getArray(), $error->getCode());
      }
      $uploadHelper = new UploadHelper();
      $info = $uploadHelper->handleScheduleAnalysis($upload, $folder,
        $scanOptionsJSON);
      return $response->withJson($info->getArray(), $info->getCode());
    }
    $error = new Info(400, "Folder id and upload id should be integers!", InfoType::ERROR);
    return $response->withJson($error->getArray(), $error->getCode());
  }

  /**
   * Delete a job using it's Job ID and Queue ID. Job ID is job_pk in job table
   * and Queue ID is jobqueue_pk in jobqueue table
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function deleteJob($request, $response, $args)
  {
    $userId = $this->restHelper->getUserId();
    $userName = $this->restHelper->getUserDao()->getUserName($userId);

    /* Check if the job exists */
    $jobId  = intval($args['id']);
    if (! $this->dbHelper->doesIdExist("job", "job_pk", $jobId)) {
      $returnVal = new Info(404, "Job id " . $jobId . " doesn't exist",
        InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }

    /* Check if user has permission to delete this job*/
    $canDeleteJob = $this->restHelper->getJobDao()->hasActionPermissionsOnJob($jobId, $userId, $this->restHelper->getGroupId());
    if (! $canDeleteJob) {
      $returnVal = new Info(403, "You don't have permission to delete this job.",
        InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }

    $queueId = $args['queue'];

    /* Get Jobs that depend on the job to be deleted */
    $JobQueue = $this->restHelper->getShowJobDao()->getJobInfo([$jobId])[$jobId]["jobqueue"];

    $dependentJobs = [];

    if (array_key_exists($queueId, $JobQueue)) {
      $dependentJobs[] = $queueId;
    } else {
      $returnVal = new Info(404, "Job queue " . $queueId . " doesn't exist in Job " . $jobId,
        InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }

    foreach ($JobQueue as $job) {
      if (in_array($queueId, $job["depends"])) {
        $dependentJobs[] = $job["jq_pk"];
      }
    }

    /* Delete All jobs in dependentJobs */
    foreach ($dependentJobs as $job) {
      $Msg = "\"" . _("Killed by") . " " . $userName . "\"";
      $command = "kill $job $Msg";
      $rv = fo_communicate_with_scheduler($command, $response_from_scheduler, $error_info);
      if ($rv == false) {
        $returnVal = new Info(500, "Failed to kill job $jobId", InfoType::ERROR);
        return $response->withJson($returnVal->getArray(), $returnVal->getCode());
      }
    }
    $returnVal = new Info(200, "Job deleted successfully", InfoType::INFO);
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }

  /**
   * Get all jobs created by the current user.
   *
   * @param integer|null $id Specific job id or null for all jobs
   * @param integer $uid Specific user id
   * @param ResponseHelper $response Response object
   * @param integer $limit   Limit of jobs per page
   * @param integer $page    Page number required
   * @return ResponseHelper
   */
  private function getAllUserResults($id, $uid, $response, $limit, $page)
  {
    list($jobs, $count) = $this->dbHelper->getUserJobs($id, $uid, $limit, $page);
    $finalJobs = [];
    foreach ($jobs as $job) {
      $this->updateEtaAndStatus($job);
      $finalJobs[] = $job->getArray();
    }
    if ($id !== null) {
      $finalJobs = $finalJobs[0];
    }
    return $response->withHeader("X-Total-Pages", $count)->withJson($finalJobs, 200);
  }

  /**
   * Get all jobs for the current user.
   *
   * @param integer|null $id Specific job id or null for all jobs
   * @param ResponseHelper $response Response object
   * @param integer $limit   Limit of jobs per page
   * @param integer $page    Page number required
   * @return ResponseHelper
   */
  private function getAllResults($id, $response, $limit, $page)
  {
    list($jobs, $count) = $this->dbHelper->getJobs($id, $limit, $page);
    $finalJobs = [];
    foreach ($jobs as $job) {
      $this->updateEtaAndStatus($job);
      $finalJobs[] = $job->getArray();
    }
    if ($id !== null) {
      $finalJobs = $finalJobs[0];
    }
    return $response->withHeader("X-Total-Pages", $count)->withJson($finalJobs, 200);
  }

  /**
   * Get all jobs for the given upload.
   *
   * @param integer $uploadId Upload id to be filtered
   * @param ResponseHelper $response Response object
   * @param integer $limit    Limit of jobs per page
   * @param integer $page     Page number required
   * @return ResponseHelper
   */
  private function getFilteredResults($uploadId, $response, $limit, $page)
  {
    if (! $this->dbHelper->doesIdExist("upload", "upload_pk", $uploadId)) {
      $returnVal = new Info(404, "Upload id " . $uploadId . " doesn't exist",
        InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }
    list($jobs, $count) = $this->dbHelper->getJobs(null, $limit, $page, $uploadId);
    $finalJobs = [];
    foreach ($jobs as $job) {
      $this->updateEtaAndStatus($job);
      $finalJobs[] = $job->getArray();
    }
    return $response->withHeader("X-Total-Pages", $count)->withJson($finalJobs, 200);
  }

  /**
   * Update the ETA and status for the given job
   *
   * @param[in,out] Job $job The job to be updated
   */
  private function updateEtaAndStatus(&$job)
  {
    $jobDao = $this->restHelper->getJobDao();

    $eta = 0;
    $status = "";
    $jobqueue = [];

    /* Check if the job has no upload like Maintenance job */
    if (empty($job->getUploadId())) {
      $sql = "SELECT jq_pk, jq_end_bits from jobqueue WHERE jq_job_fk = $1;";
      $statement = __METHOD__ . ".getJqpk";
      $rows = $this->dbHelper->getDbManager()->getRows($sql, [$job->getId()],
        $statement);
      if (count($rows) > 0) {
        $jobqueue[$rows[0]['jq_pk']] = $rows[0]['jq_end_bits'];
      }
    } else {
      $jobqueue = $jobDao->getAllJobStatus($job->getUploadId(),
        $job->getUserId(), $job->getGroupId());
    }

    $job->setEta($this->getUploadEtaInSeconds($job->getId(),
      $job->getUploadId()));

    $job->setStatus($this->getJobStatus(array_keys($jobqueue)));
  }

  /**
   * Get the ETA in seconds for the upload.
   *
   * @param integer $jobId    The job ID for which the ETA is required
   * @param integer $uploadId Upload for which the ETA is required
   * @return integer ETA in seconds (0 if job already finished)
   */
  private function getUploadEtaInSeconds($jobId, $uploadId)
  {
    $showJobDao = $this->restHelper->getShowJobDao();
    $eta = $showJobDao->getEstimatedTime($jobId, '', 0, $uploadId);
    $eta = explode(":", $eta);
    if (count($eta) > 1) {
      $eta = (intval($eta[0]) * 3600) + (intval($eta[1]) * 60) + intval($eta[2]);
    } else {
      $eta = 0;
    }
    return $eta;
  }

  /**
   * Get the job status based on jobqueue.
   *
   * @param array $jobqueue The job queue with job id as values
   * @return string Job status (Completed, Processing, Queued or Failed)
   */
  private function getJobStatus($jobqueue)
  {
    $showJobDao = $this->restHelper->getShowJobDao();
    $jobStatus = 0;
    /* Check each job in queue */
    foreach ($jobqueue as $jobId) {
      $jobInfo = $showJobDao->getDataForASingleJob($jobId);
      $endtext = $jobInfo['jq_endtext'];
      switch ($endtext) {
        case 'Completed':
          $jobStatus |= self::JOB_COMPLETED;
          break;
        case 'Started':
        case 'Restarted':
        case 'Paused':
          $jobStatus |= self::JOB_STARTED;
          break;
        default:
          if (empty($jobInfo['jq_endtime'])) {
            $jobStatus |= self::JOB_QUEUED;
          } else {
            $jobStatus |= self::JOB_FAILED;
          }
      }
    }

    $jobStatusString = "";
    if ($jobStatus & self::JOB_FAILED) {
      /* If at least one job is failed, set status as failed */
      $jobStatusString = "Failed";
    } else if ($jobStatus & self::JOB_STARTED) {
      /* If at least one job is started, set status as processing */
      $jobStatusString = "Processing";
    } else if ($jobStatus & self::JOB_QUEUED) {
      $jobStatusString = "Queued";
    } else {
      /* If everything completed successfully, set status as completed */
      $jobStatusString = "Completed";
    }
    return $jobStatusString;
  }

  /**
   * Get the history of all the jobs queued based on an upload
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getJobsHistory($request, $response, $args)
  {
    $query = $request->getQueryParams();
    $returnVal = null;
    if (!array_key_exists(self::UPLOAD_PARAM, $query)) {
      $returnVal = new Info(400, "Bad Request. 'upload' is a required query param", InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }
    $upload_fk = intval($query[self::UPLOAD_PARAM]);
    // checking if the upload exists and if yes, whether it is accessible
    $res = true;
    if (! $this->dbHelper->doesIdExist("upload", "upload_pk", $upload_fk)) {
      $returnVal = new Info(404, "Upload does not exist", InfoType::ERROR);
      $res = false;
    } else if (! $this->restHelper->getUploadDao()->isAccessible($upload_fk, $this->restHelper->getGroupId())) {
      $returnVal = new Info(403, "Upload is not accessible", InfoType::ERROR);
      $res = false;
    }
    if ($res !== true) {
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }

    /**
     * @var DbManager $dbManager
     * initialising the DB manager
     */
    $dbManager = $this->dbHelper->getDbManager();

    // getting all the jobs from the DB for the upload id
    $query = "SELECT job_pk FROM job WHERE job_upload_fk=$1;";
    $statement = __METHOD__.".getJobs";
    $result = $dbManager->getRows($query, [$upload_fk], $statement);

    // creating a list of all the job_pks
    $allJobPks = array_column($result, 'job_pk');

    /**
     * @var ShowJobsDao $showJobsDao
     * initialising the show jobs Dao
     */
    $showJobsDao = $this->container->get('dao.show_jobs');

    $jobsInfo = $showJobsDao->getJobInfo($allJobPks);
    usort($jobsInfo, [$this, "compareJobsInfo"]);

    /**
     * @var \Fossology\UI\Ajax\AjaxShowJobs $ajaxShowJobs
     * initialising the show jobs ajax plugin
     */
    $ajaxShowJobs = $this->restHelper->getPlugin('ajaxShowJobs');

    // getting the show jobs data for each job
    $showJobData = $ajaxShowJobs->getShowJobsForEachJob($jobsInfo);

    // creating the response structure
    $allJobsHistory = array();
    foreach ($showJobData as $jobValObj) {
      $finalJobqueue = array();
      foreach ($jobValObj['job']['jobQueue'] as $jqVal) {
        $depends = [];
        if ($jqVal['depends'][0] != null) {
          $depends = $jqVal['depends'];
        }
        $download = null;
        if (!empty($jqVal['download'])) {
          $download = [
            "text" => $jqVal["download"],
            "link" => ReportController::buildDownloadPath($request,
              $jqVal['jq_job_fk'])
          ];
        }
        $jobQueue = new JobQueue($jqVal['jq_pk'], $jqVal['jq_type'],
          $jqVal['jq_starttime'], $jqVal['jq_endtime'], $jqVal['jq_endtext'],
          $jqVal['jq_itemsprocessed'], $jqVal['jq_log'], $depends,
          $jqVal['itemsPerSec'], $jqVal['canDoActions'], $jqVal['isInProgress'],
          $jqVal['isReady'], $download);
        $finalJobqueue[] = $jobQueue->getArray();
      }
      $job = new ShowJob($jobValObj['job']['jobId'],
        $jobValObj['job']['jobName'], $finalJobqueue,
        $jobValObj['upload']['uploadId']);
      $allJobsHistory[] = $job->getArray();
    }
    return $response->withJson($allJobsHistory, 200);
  }

  /**
   * @brief Sort compare function to order $JobsInfo by job_pk
   * @param array $JobsInfo1 Result from GetJobInfo
   * @param array $JobsInfo2 Result from GetJobInfo
   * @return int
   */
  private function compareJobsInfo($JobsInfo1, $JobsInfo2)
  {
    return $JobsInfo2["job"]["job_pk"] - $JobsInfo1["job"]["job_pk"];
  }

  /**
   * @brief Get the summary statistics of all the jobs
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getJobStatistics($request, $response, $args)
  {
    if (!Auth::isAdmin()) {
      $error = new Info(403, "Only Admin can access the endpoint.", InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }
    $statisticsPlugin = $this->restHelper->getPlugin('dashboard-statistics');
    $res = $statisticsPlugin->CountAllJobs(true);
    return $response->withJson($res, 200);
  }
}
