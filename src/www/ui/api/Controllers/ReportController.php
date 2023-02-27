<?php
/*
 SPDX-FileCopyrightText: © 2018, 2021 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Controller for report queries
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\CliXml\UI\CliXmlGeneratorUi;
use Fossology\DecisionExporter\UI\FoDecisionExporter;
use Fossology\DecisionImporter\UI\AgentDecisionImporterPlugin;
use Fossology\ReadmeOSS\UI\ReadMeOssPlugin;
use Fossology\SpdxTwo\UI\SpdxTwoGeneratorUi;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UnifiedReport\UI\FoUnifiedReportGenerator;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\StreamFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * @class ReportController
 * @brief Controller for report path
 */
class ReportController extends RestController
{

  /**
   * @var array $reportsAllowed
   * Allowed agent names to create report
   */
  private $reportsAllowed = array(
    'dep5',
    'spdx2',
    'spdx2tv',
    'readmeoss',
    'unifiedreport',
    'clixml',
    'decisionexporter'
  );

  /**
   * @var string[] $importAllowed
   * Allowed report types to be imported.
   */
  private $importAllowed = [
    'decisionimporter'
  ];

  /**
   * Get the required report for the required upload
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getReport($request, $response, $args)
  {
    $uploadId = $request->getHeaderLine('uploadId');
    $reportFormat = $request->getHeaderLine('reportFormat');

    if (! in_array($reportFormat, $this->reportsAllowed)) {
      $error = new Info(400,
        "reportFormat must be from [" . implode(",", $this->reportsAllowed) . "]",
        InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }
    $upload = $this->getUpload($uploadId);
    if (get_class($upload) === Info::class) {
      return $response->withJson($upload->getArray(), $upload->getCode());
    }
    $jobId = null;
    $jobQueueId = null;
    $error = "";

    try {
      switch ($reportFormat) {
        case $this->reportsAllowed[0]:
        case $this->reportsAllowed[1]:
        case $this->reportsAllowed[2]:
          /** @var SpdxTwoGeneratorUi $spdxGenerator */
          $spdxGenerator = $this->restHelper->getPlugin('ui_spdx2');
          list ($jobId, $jobQueueId, $error) = $spdxGenerator->scheduleAgent(
            $this->restHelper->getGroupId(), $upload, $reportFormat);
          break;
        case $this->reportsAllowed[3]:
          /** @var ReadMeOssPlugin $readmeGenerator */
          $readmeGenerator = $this->restHelper->getPlugin('ui_readmeoss');
          list ($jobId, $jobQueueId, $error) = $readmeGenerator->scheduleAgent(
            $this->restHelper->getGroupId(), $upload);
          break;
        case $this->reportsAllowed[4]:
          /** @var FoUnifiedReportGenerator $unifiedGenerator */
          $unifiedGenerator = $this->restHelper->getPlugin('agent_founifiedreport');
          list ($jobId, $jobQueueId, $error) = $unifiedGenerator->scheduleAgent(
            $this->restHelper->getGroupId(), $upload);
          break;
        case $this->reportsAllowed[5]:
          /** @var CliXmlGeneratorUi $clixmlGenerator */
          $clixmlGenerator = $this->restHelper->getPlugin('ui_clixml');
          list ($jobId, $jobQueueId) = $clixmlGenerator->scheduleAgent(
            $this->restHelper->getGroupId(), $upload);
          break;
        case $this->reportsAllowed[6]:
          /** @var FoDecisionExporter $decisionExporter */
          $decisionExporter = $this->restHelper->getPlugin('agent_fodecisionexporter');
          list($jobId, $jobQueueId) = $decisionExporter->scheduleAgent(
            $this->restHelper->getGroupId(), $upload);
          break;
        default:
          $error = new Info(500, "Some error occured!", InfoType::ERROR);
          return $response->withJson($error->getArray(), $error->getCode());
      }
    } catch (\Exception $e) {
      $error = new Info(500, $e->getMessage(), InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }
    if (! empty($error)) {
      $info = new Info(500, $error, InfoType::ERROR);
    } else {
      $download_path = $this->buildDownloadPath($request, $jobId);
      $info = new Info(201, $download_path, InfoType::INFO);
    }
    return $response->withJson($info->getArray(), $info->getCode());
  }

  /**
   * Get the upload object from a given upload id
   *
   * @param int $uploadId Upload Id to get from
   * @return Fossology::UI::Api::Models::Info|Upload|NULL
   */
  private function getUpload($uploadId)
  {
    $upload = null;
    if (empty($uploadId) || ! is_numeric($uploadId) || $uploadId <= 0) {
      $upload = new Info(400, "uploadId must be a positive integer!",
        InfoType::ERROR);
    }
    $uploadDao = $this->restHelper->getUploadDao();
    if (! $uploadDao->isAccessible($uploadId, $this->restHelper->getGroupId())) {
      $upload = new Info(403, "Upload is not accessible!", InfoType::ERROR);
    }
    if ($upload !== null) {
      return $upload;
    }
    $upload = $uploadDao->getUpload($uploadId);
    if ($upload === null) {
      $upload = new Info(404, "Upload does not exists!", InfoType::ERROR);
    }
    return $upload;
  }

  /**
   * Generate the path to download URL based on current request and new Job id
   * @param ServerRequestInterface $request
   * @param integer $jobId The new job id created by agent
   * @return string The path to download the report
   */
  private function buildDownloadPath($request, $jobId)
  {
    $path = $request->getUri()->getHost();
    $path .= $request->getRequestTarget();
    $url_parts = parse_url($path);
    $download_path = "";
    if (array_key_exists("scheme", $url_parts)) {
      $download_path .= $url_parts["scheme"] . "://";
    }
    if (array_key_exists("user", $url_parts)) {
      $download_path .= $url_parts["user"];
    }
    if (array_key_exists("pass", $url_parts)) {
      $download_path .= ':' . $url_parts["pass"];
    }
    if (array_key_exists("host", $url_parts)) {
      $download_path .= $url_parts["host"];
    }
    if (array_key_exists("port", $url_parts)) {
      $download_path .= ':' . $url_parts["port"];
    }
    if (substr($url_parts["path"], -1) !== '/') {
      $url_parts["path"] .= '/';
    }
    $download_path .= $url_parts["path"] . $jobId;
    if (array_key_exists("query", $url_parts)) {
      $download_path .= '?' . $url_parts["query"];
    }
    if (array_key_exists("fragment", $url_parts)) {
      $download_path .= '#' . $url_parts["fragment"];
    }
    return $download_path;
  }

  /**
   * Download the report with the given job id
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function downloadReport($request, $response, $args)
  {
    $id = $args['id'];
    $returnVal = $this->checkReport($id);
    if ($returnVal !== true) {
      $newResponse = $response;
      if ($returnVal->getCode() == 503) {
        $newResponse = $response->withHeader('Retry-After', '10');
      }
      return $newResponse->withJson($returnVal->getArray(),
        $returnVal->getCode());
    }
    $ui_download = $this->restHelper->getPlugin('download');
    try {
      /**
       * @var BinaryFileResponse $responseFile
       */
      $responseFile = $ui_download->getReport($args['id']);
      /**
       * @var File $responseContent
       */
      $responseContent = $responseFile->getFile();
      $newResponse = $response->withHeader('Content-Description',
        'File Transfer')
        ->withHeader('Content-Type',
        $responseContent->getMimeType())
        ->withHeader('Content-Disposition',
        $responseFile->headers->get('Content-Disposition'))
        ->withHeader('Cache-Control', 'must-revalidate')
        ->withHeader('Pragma', 'private')
        ->withHeader('Content-Length', filesize($responseContent->getPathname()));
      $sf = new StreamFactory();
      $newResponse = $newResponse->withBody(
        $sf->createStreamFromFile($responseContent->getPathname())
      );

      return $newResponse;
    } catch (\Exception $e) {
      $error = new Info(500, $e->getMessage(), InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }
  }

  /**
   * Check if a report is scheduled with the given job id
   *
   * @param int $id Job id
   * @return Fossology::UI::Api::Models::Info|true
   */
  private function checkReport($id)
  {
    $dbManager = $this->dbHelper->getDbManager();
    $row = $dbManager->getSingleRow(
      'SELECT jq_type FROM jobqueue WHERE jq_job_fk = $1', array(
        $id
      ), "reportValidity");
    if (! in_array($row['jq_type'], $this->reportsAllowed)) {
      return new Info(404, "No report scheduled with given job id.",
        InfoType::ERROR);
    }
    $row = $dbManager->getSingleRow('SELECT job_upload_fk FROM job WHERE job_pk = $1',
      array($id), "reportFileUpload");
    $uploadId = intval($row['job_upload_fk']);
    $uploadDao = $this->restHelper->getUploadDao();
    if (! $uploadDao->isAccessible($uploadId, $this->restHelper->getGroupId())) {
      return new Info(403, "Report is not accessible.", InfoType::INFO);
    }
    $row = $dbManager->getSingleRow('SELECT * FROM reportgen WHERE job_fk = $1',
      array($id), "reportFileName");
    if ($row === false) {
      return new Info(503, "Report is not ready. Retry after 10s.", InfoType::INFO);
    }
    // Everything went well
    return true;
  }

  /**
   * Download the report with the given job id
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function importReport(ServerRequestInterface $request, ResponseHelper $response, array $args): ResponseHelper
  {
    $returnVal = null;
    $query = $request->getQueryParams();
    if (!array_key_exists("upload", $query)) {
      $returnVal = new Info(400, "Bad Request. **upload** is a required query param", InfoType::INFO);
    } elseif (!array_key_exists("reportFormat", $query) || !in_array($query["reportFormat"], $this->importAllowed)) {
      $returnVal = new Info(400, "Bad Request. Missing or wrong query param 'reportFormat'", InfoType::ERROR);
    }
    if ($returnVal !== null) {
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }
    $upload_pk = intval($query['upload']);
    // checking if the scheduler is running or not
    $commu_status = fo_communicate_with_scheduler('status', $response_from_scheduler, $error_info);
    if ($commu_status) {
      $reqBody = $this->getParsedBody($request);
      $res = true;
      if (! $this->dbHelper->doesIdExist("upload", "upload_pk", $upload_pk)) {
        $returnVal = new Info(404, "Upload does not exist", InfoType::ERROR);
        $res = false;
      } else if (! $this->restHelper->getUploadDao()->isAccessible($upload_pk, $this->restHelper->getGroupId())) {
        $returnVal = new Info(403, "Upload is not accessible", InfoType::ERROR);
        $res = false;
      }
      if (!$res) {
        return $response->withJson($returnVal->getArray(), $returnVal->getCode());
      }
      $reportFormat = $query["reportFormat"];
      switch ($reportFormat) {
        case $this->importAllowed[0]:
          $returnVal = $this->importDecisionJson($request, $response, $reqBody, $upload_pk);
      }
      return $returnVal;
    }
    $returnVal = new Info(503, "Scheduler is not running!", InfoType::ERROR);
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }

  /**
   * Run decision importer agent for JSON report.
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $reqBody
   * @param int $uploadId
   * @return ResponseHelper
   */
  private function importDecisionJson(ServerRequestInterface $request, ResponseHelper $response, array $reqBody, int $uploadId): ResponseHelper
  {
    /** @var AgentDecisionImporterPlugin $decisionImporter */
    $decisionImporter = $this->restHelper->getPlugin("ui_fodecisionimporter");
    $symfonyRequest = new Request();

    $files = $request->getUploadedFiles();

    if (empty($files['report'])) {
      $info = new Info(400, "No file uploaded", InfoType::ERROR);
      return $response->withJson($info->getArray(), $info->getCode());
    } elseif (!array_key_exists("importerUser", $reqBody)) {
      $info = new Info(400, "Missing parameter 'importerUser'", InfoType::ERROR);
      return $response->withJson($info->getArray(), $info->getCode());
    }

    $importerUser = intval($reqBody["importerUser"]);
    if (empty($importerUser)) {
      $importerUser = $this->restHelper->getUserId();
    }

    /** @var \Slim\Psr7\UploadedFile $slimFile */
    $slimFile = $files['report'];

    $uploadedFile = new UploadedFile($slimFile->getFilePath(), $slimFile->getClientFilename(), $slimFile->getClientMediaType());

    $symfonyRequest->files->set('report', $uploadedFile);
    $symfonyRequest->request->set('uploadselect', $uploadId);
    $symfonyRequest->request->set('userselect', $importerUser);

    try {
      $agentResp = $decisionImporter->handleRequest($symfonyRequest);
    } catch (\Exception $e) {
      $error = new Info(500, $e->getMessage(), InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }

    if ($agentResp === false) {
      $info = new Info(400, "Missing required fields", InfoType::ERROR);
    } else {
      $info = new Info(201, intval($agentResp[0]), InfoType::INFO);
    }
    return $response->withJson($info->getArray(), $info->getCode());
  }
}
