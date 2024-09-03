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

use Fossology\Lib\Dao\ShowJobsDao;
use Fossology\Lib\Db\DbManager;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpErrorException;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Exceptions\HttpInternalServerErrorException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\UploadHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\JobQueue;
use Fossology\UI\Api\Models\ShowJob;
use Fossology\UI\Api\Models\ApiVersion;
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
   * @throws HttpErrorException
   */
  public function getAllJobs($request, $response, $args)
  {
    $apiVersion = ApiVersion::getVersion($request);
    $this->throwNotAdminException();
    $id = null;
    $limit = 0;
    $page = 1;
    if ($apiVersion == ApiVersion::V2) {
      $query = $request->getQueryParams();
      $limit = $query['limit'] ?? 0;
      $page = $query['page'] ?? 1;
    } else {
      $limit = $request->hasHeader('limit') ? $request->getHeaderLine('limit') : 0;
      $page = $request->hasHeader('page') ? $request->getHeaderLine('page') : 1;
    }
    if (empty($page)) {
      $page = 1;
    }
    if ((! is_numeric($limit) || $limit < 0) ||
      (! is_numeric($page) || $page < 1)) {
      throw new HttpBadRequestException(
        "Limit and page cannot be smaller than 1 and has to be numeric!");
    }

    return $this->getAllResults($id, $request, $response, $limit, $page, $apiVersion);
  }

  /**
   * Get all jobs by a user
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function getJobs($request, $response, $args)
  {
    $apiVersion = ApiVersion::getVersion($request);
    $query = $request->getQueryParams();
    $userId = $this->restHelper->getUserId();
    $limit = 0;
    $page = 1;
    if ($apiVersion == ApiVersion::V2) {
      $limit = $query['limit'] ?? 0;
      $page = $query['page'] ?? 1;
    } else {
      $limit = $request->hasHeader('limit') ? $request->getHeaderLine('limit') : 0;
      $page = $request->hasHeader('page') ? $request->getHeaderLine('page') : 1;
    }
    if (empty($page)) {
      $page = 1;
    }
    if ((! is_numeric($limit) || $limit < 0) ||
      (! is_numeric($page) || $page < 1)) {
      throw new HttpBadRequestException(
        "Limit and page cannot be smaller than 1 and has to be numeric!");
    }

    $id = null;
    if (isset($args['id'])) {
      $id = intval($args['id']);
      if (! $this->dbHelper->doesIdExist("job", "job_pk", $id)) {
        throw new HttpNotFoundException("Job id " . $id . " doesn't exist");
      }
    }

    if ($id !== null) {
      /* If the ID is passed, don't check for upload */
      return $this->getAllResults($id, $request, $response, $limit, $page, $apiVersion);
    }

    if (array_key_exists(self::UPLOAD_PARAM, $query)) {
      /* If the upload is passed, filter accordingly */
      return $this->getFilteredResults(intval($query[self::UPLOAD_PARAM]),
        $request, $response, $limit, $page, $apiVersion);
    } else {
      $id = null;
      return $this->getAllUserResults($id, $userId, $request, $response, $limit, $page, $apiVersion);
    }
  }

  /**
   * Create a new job
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function createJob($request, $response, $args)
  {
    $apiVersion = ApiVersion::getVersion($request);
    $folder = null;
    $upload = null;
    if ($apiVersion == ApiVersion::V2) {
      $query = $request->getQueryParams();
      $folder = $query["folderId"] ?? null;
      $upload = $query["uploadId"] ?? null;
    } else {
      $folder = $request->hasHeader('folderId') ? $request->getHeaderLine('folderId') : null;
      $upload = $request->hasHeader('uploadId') ? $request->getHeaderLine('uploadId') : null;
    }
    if (is_numeric($folder) && is_numeric($upload) && $folder > 0 && $upload > 0) {
      $scanOptionsJSON = $this->getParsedBody($request);
      if (empty($scanOptionsJSON)) {
        throw new HttpBadRequestException("No agents selected!");
      }
      $uploadHelper = new UploadHelper();
      $info = $uploadHelper->handleScheduleAnalysis($upload, $folder,
        $scanOptionsJSON, false, $apiVersion);
      return $response->withJson($info->getArray(), $info->getCode());
    }
    throw new HttpBadRequestException(
      "Folder id and upload id should be integers!");
  }

  /**
   * Delete a job using it's Job ID and Queue ID. Job ID is job_pk in job table
   * and Queue ID is jobqueue_pk in jobqueue table
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function deleteJob($request, $response, $args)
  {
    $userId = $this->restHelper->getUserId();
    $userName = $this->restHelper->getUserDao()->getUserName($userId);

    /* Check if the job exists */
    $jobId  = intval($args['id']);
    if (! $this->dbHelper->doesIdExist("job", "job_pk", $jobId)) {
      throw new HttpNotFoundException("Job id " . $jobId . " doesn't exist");
    }

    /* Check if user has permission to delete this job*/
    $canDeleteJob = $this->restHelper->getJobDao()->hasActionPermissionsOnJob($jobId, $userId, $this->restHelper->getGroupId());
    if (! $canDeleteJob) {
      throw new HttpForbiddenException(
        "You don't have permission to delete this job.");
    }

    $queueId = $args['queue'];

    /* Get Jobs that depend on the job to be deleted */
    $JobQueue = $this->restHelper->getShowJobDao()->getJobInfo([$jobId])[$jobId]["jobqueue"];

    if (!array_key_exists($queueId, $JobQueue)) {
      throw new HttpNotFoundException(
        "Job queue " . $queueId . " doesn't exist in Job " . $jobId);
    }

    $dependentJobs = [];
    $dependentJobs[] = $queueId;

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
      if (!$rv) {
        throw new HttpInternalServerErrorException(
          "Failed to kill job $jobId");
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
   * @param Request $request Request object
   * @param ResponseHelper $response Response object
   * @param integer $limit   Limit of jobs per page
   * @param integer $page    Page number required
   * @param integer $apiVersion API version
   * @return ResponseHelper
   */
  private function getAllUserResults($id, $uid, $request, $response, $limit, $page, $apiVersion)
  {
    list($jobs, $count) = $this->dbHelper->getUserJobs($id, $uid, $limit, $page);
    $finalJobs = [];
    foreach ($jobs as $job) {
      $this->updateEtaAndStatus($job);
      if ($apiVersion == ApiVersion::V2) {
        $this->addJobQueue($job, $request);
      }
      $finalJobs[] = $job->getArray($apiVersion);
    }
    if ($id !== null) {
      $finalJobs = $finalJobs[0];
    } else {
      usort($finalJobs, [$this, "sortJobsByDate"]);
    }
    return $response->withHeader("X-Total-Pages", $count)->withJson($finalJobs, 200);
  }

  /**
   * Get all jobs for the current user.
   *
   * @param integer|null $id Specific job id or null for all jobs
   * @param Request $request Request object
   * @param ResponseHelper $response Response object
   * @param integer $limit   Limit of jobs per page
   * @param integer $page    Page number required
   * @param integer $apiVersion API version
   * @return ResponseHelper
   */
  private function getAllResults($id, $request, $response, $limit, $page, $apiVersion)
  {
    list($jobs, $count) = $this->dbHelper->getJobs($id, $limit, $page);
    $finalJobs = [];
    foreach ($jobs as $job) {
      $this->updateEtaAndStatus($job);
      if ($apiVersion == ApiVersion::V2) {
        $this->addJobQueue($job, $request);
      }
      $finalJobs[] = $job->getArray($apiVersion);
    }
    if ($id !== null) {
      $finalJobs = $finalJobs[0];
    } else {
      usort($finalJobs, [$this, "sortJobsByDate"]);
    }
    return $response->withHeader("X-Total-Pages", $count)->withJson($finalJobs, 200);
  }

  /**
   * Get all jobs for the given upload.
   *
   * @param integer $uploadId Upload id to be filtered
   * @param Request $request Request object
   * @param ResponseHelper $response Response object
   * @param integer $limit Limit of jobs per page
   * @param integer $page Page number required
   * @param integer $apiVersion API version
   * @return ResponseHelper
   * @throws HttpNotFoundException
   */
  private function getFilteredResults($uploadId, $request, $response, $limit, $page, $apiVersion)
  {
    if (! $this->dbHelper->doesIdExist("upload", "upload_pk", $uploadId)) {
      throw new HttpNotFoundException("Upload id " . $uploadId .
        " doesn't exist");
    }
    list($jobs, $count) = $this->dbHelper->getJobs(null, $limit, $page, $uploadId);
    $finalJobs = [];
    foreach ($jobs as $job) {
      $this->updateEtaAndStatus($job);
      if ($apiVersion == ApiVersion::V2) {
        $this->addJobQueue($job, $request);
      }
      $finalJobs[] = $job->getArray($apiVersion);
    }
    usort($finalJobs, [$this, "sortJobsByDate"]);
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

    $jobqueue = [];
    $jobqueue = $jobDao->getChlidJobStatus($job->getId());

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
   * @throws HttpErrorException
   */
  public function getJobsHistory($request, $response, $args)
  {
    $query = $request->getQueryParams();
    if (!array_key_exists(self::UPLOAD_PARAM, $query)) {
      throw new HttpBadRequestException("'upload' is a required query param");
    }
    $upload_fk = intval($query[self::UPLOAD_PARAM]);
    // checking if the upload exists and if yes, whether it is accessible
    $this->uploadAccessible($upload_fk);

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

    // getting the show jobs data for each job
    $showJobData = $this->getJobQueue($allJobPks);

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
   * Get the jobqueue form job_pk
   *
   * @param array $jobqueue The job queue with job id as values
   * @return \Fossology\UI\Ajax\Returns the job queue data
   */
  private function getJobQueue($allJobPks)
  {
    $showJobsDao = $this->restHelper->getShowJobDao();
    $jobsInfo = $showJobsDao->getJobInfo($allJobPks);
    usort($jobsInfo, [$this, "compareJobsInfo"]);

    /**
     * @var \Fossology\UI\Ajax\AjaxShowJobs $ajaxShowJobs
     * initialising the show jobs ajax plugin
     */
    $ajaxShowJobs = $this->restHelper->getPlugin('ajaxShowJobs');
    $showJobData = $ajaxShowJobs->getShowJobsForEachJob($jobsInfo, true);

    return $showJobData;
  }

  /**
   * Add the job queue to the job object
   *
   * @param[in,out] Job $job The job to be updated
   * @param Request $request The request object
   */
  private function addJobQueue(&$job, $request = null)
  {
    $jobQueue = $this->getJobQueue([$job->getId()]);
    $finalJobqueue = array();
    foreach ($jobQueue[0]['job']['jobQueue'] as $jqVal) {
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
      $singleJobQueue = new JobQueue($jqVal['jq_pk'], $jqVal['jq_type'],
        $jqVal['jq_starttime'], $jqVal['jq_endtime'], $jqVal['jq_endtext'],
        $jqVal['jq_itemsprocessed'], $jqVal['jq_log'], $depends,
        $jqVal['itemsPerSec'], $jqVal['canDoActions'], $jqVal['isInProgress'],
        $jqVal['isReady'], $download);
      $finalJobqueue[] = $singleJobQueue->getArray();
    }
    $job->setJobQueue($finalJobqueue);
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
   * @brief Sort compare function to order $JobsInfo by jobqueue start time
   * @param array $job1 Result from finalJobs
   * @param array $job2 Result from finalJobs
   * @return int
   */
  private function sortJobsByDate($job1, $job2)
  {
    return strtotime($job2['queueDate']) - strtotime($job1['queueDate']);
  }

  /**
   * @brief Get the summary statistics of all the jobs
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpForbiddenException
   */
  public function getJobStatistics($request, $response, $args)
  {
    $this->throwNotAdminException();
    /** @var \dashboardReporting $statisticsPlugin */
    $statisticsPlugin = $this->restHelper->getPlugin('dashboard-statistics');
    $res = $statisticsPlugin->CountAllJobs(true);
    return $response->withJson($res, 200);
  }

  /**
   * Get all the server jobs with status
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpForbiddenException
   */
  public function getAllServerJobsStatus($request, $response, $args)
  {
    $this->throwNotAdminException();
    /** @var \Fossology\UI\Ajax\AjaxAllJobStatus $allJobStatusPlugin */
    $allJobStatusPlugin = $this->restHelper->getPlugin('ajax_all_job_status');
    $symfonyRequest = new \Symfony\Component\HttpFoundation\Request();
    $res = $allJobStatusPlugin->handle($symfonyRequest);
    return $response->withJson(json_decode($res->getContent(), true), 200);
  }

  /**
   * @brief Get the scheduler job options for a given operation
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function getSchedulerJobOptionsByOperation($request, $response, $args)
  {
    $this->throwNotAdminException();
    $operation = $args['operationName'];
    /** @var \admin_scheduler $adminSchedulerPlugin */
    $adminSchedulerPlugin = $this->restHelper->getPlugin('admin_scheduler');

    if (!in_array($operation, array_keys($adminSchedulerPlugin->operation_array))) {
      $allowedOperations = implode(', ', array_keys($adminSchedulerPlugin->operation_array));
      throw new HttpBadRequestException("Operation '$operation' not allowed." .
        " Allowed operations are: $allowedOperations");
    }

    /** @var \Fossology\UI\Ajax\AjaxAdminScheduler $schedulerPlugin */
    $schedulerPlugin = $this->restHelper->getPlugin('ajax_admin_scheduler');
    $symfonyRequest = new \Symfony\Component\HttpFoundation\Request();
    $symfonyRequest->request->set('operation', $operation);
    $symfonyRequest->request->set('fromRest', true);
    $res = $schedulerPlugin->handle($symfonyRequest);
    return $response->withJson($res, 200);
  }

  /**
   * Run the scheduler based on the given operation
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function handleRunSchedulerOption($request, $response, $args)
  {
    $this->throwNotAdminException();
    $body = $this->getParsedBody($request);
    $query = $request->getQueryParams();

    $operation = $body['operation'];
    /** @var \admin_scheduler $adminSchedulerPlugin */
    $adminSchedulerPlugin = $this->restHelper->getPlugin('admin_scheduler');

    if (!in_array($operation, array_keys($adminSchedulerPlugin->operation_array))) {
      $allowedOperations = implode(', ', array_keys($adminSchedulerPlugin->operation_array));
      throw new HttpBadRequestException("Operation '$operation' not allowed." .
        " Allowed operations are: $allowedOperations");
    }

    /** @var \Fossology\UI\Ajax\AjaxAdminScheduler $schedulerPlugin */
    $schedulerPlugin = $this->restHelper->getPlugin('ajax_admin_scheduler');
    $symfonyRequest = new \Symfony\Component\HttpFoundation\Request();
    $symfonyRequest->request->set('operation', $operation);
    $symfonyRequest->request->set('fromRest', true);
    $data = $schedulerPlugin->handle($symfonyRequest);

    if ($operation == 'status' || $operation == 'verbose') {
      if (!isset($query['job']) || !in_array($query['job'], $data['jobList'])) {
        $allowedJobs = implode(', ', $data['jobList']);
        throw new HttpBadRequestException("Job '{$query['job']}' not " .
          "allowed. Allowed jobs are: $allowedJobs");
      }
      if (($operation == 'verbose') && (!isset($query['level']) || !in_array($query['level'], $data['verboseList']))) {
        $allowedLevels = implode(', ', $data['verboseList']);
        throw new HttpBadRequestException("Level '{$query['level']}' not " .
          "allowed. Allowed levels are: $allowedLevels");
      }
    } elseif ($operation == 'priority' && (!isset($query['priority']) || !in_array($query['priority'], $data['priorityList']))) {
      $allowedPriorities = implode(', ', $data['priorityList']);
      throw new HttpBadRequestException("Priority '{$query['priority']}' not " .
        "allowed. Allowed priorities are: $allowedPriorities");
    }

    if ($operation == 'status') {
      $query['priority'] = null;
      $query['level'] = null;
    } else if ($operation == 'priority') {
      $query['job'] = null;
      $query['level'] = null;
    } else if ($operation == 'verbose') {
      $query['priority'] = null;
    } else {
      $query['job'] = null;
      $query['priority'] = null;
      $query['level'] = null;
    }

    $response_from_scheduler = $adminSchedulerPlugin->OperationSubmit(
      $operation, array_search($query['job'], $data['jobList']),
      $query['priority'], $query['level']);
    $operation_text = $adminSchedulerPlugin->GetOperationText($operation);
    $status_msg = "";
    $report = "";

    if (!empty($adminSchedulerPlugin->error_info)) {
      $text = _("failed");
      $status_msg .= "$operation_text $text.";
      throw new HttpInternalServerErrorException($status_msg . $report);
    }
    $text = _("successfully");
    $status_msg .= "$operation_text $text.";
    if (! empty($response_from_scheduler)) {
      $report .= $response_from_scheduler;
    }

    $info = new Info(200, $status_msg. $report, InfoType::INFO);
    return $response->withJson($info->getArray(), $info->getCode());
  }
}
