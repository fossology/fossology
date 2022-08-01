<?php
/*
 SPDX-FileCopyrightText: © 2018 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Controller for job queries
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\UI\Api\Helper\ResponseHelper;
use Psr\Http\Message\ServerRequestInterface;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\Analysis;
use Fossology\UI\Api\Models\Decider;
use Fossology\Lib\Auth\Auth;
use Fossology\UI\Api\Models\Scancode;
use Fossology\UI\Api\Models\Reuser;
use Fossology\UI\Api\Models\ScanOptions;
use Fossology\UI\Api\Models\Job;

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
      $parametersSent = false;
      $analysis = new Analysis();
      if (array_key_exists("analysis", $scanOptionsJSON) && ! empty($scanOptionsJSON["analysis"])) {
        $analysis->setUsingArray($scanOptionsJSON["analysis"]);
        $parametersSent = true;
      }
      $decider = new Decider();
      if (array_key_exists("decider", $scanOptionsJSON) && ! empty($scanOptionsJSON["decider"])) {
        $decider->setUsingArray($scanOptionsJSON["decider"]);
        $parametersSent = true;
      }
      $scancode = new Scancode();
      if (array_key_exists("scancode", $scanOptionsJSON) && ! empty($scanOptionsJSON["scancode"])) {
        $scancode->setUsingArray($scanOptionsJSON["scancode"]);
        $parametersSent = true;
      }
      $reuser = new Reuser(0, 'groupName', false, false);
      try {
        if (array_key_exists("reuse", $scanOptionsJSON) && ! empty($scanOptionsJSON["reuse"])) {
          $reuser->setUsingArray($scanOptionsJSON["reuse"]);
          $parametersSent = true;
        }
      } catch (\UnexpectedValueException $e) {
        $error = new Info($e->getCode(), $e->getMessage(), InfoType::ERROR);
        return $response->withJson($error->getArray(), $error->getCode());
      }

      if (! $parametersSent) {
        $error = new Info(403, "No parameters selected for agents!",
          InfoType::ERROR);
        return $response->withJson($error->getArray(), $error->getCode());
      }

      $scanOptions = new ScanOptions($analysis, $reuser, $decider, $scancode);
      $info = $scanOptions->scheduleAgents($folder, $upload);
      return $response->withJson($info->getArray(), $info->getCode());
    } else {
      $error = new Info(400, "Folder id and upload id should be integers!", InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }
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
      $eta = ($eta[0] * 3600) + ($eta[1] * 60) + ($eta[2]);
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
}
