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
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpErrorException;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Exceptions\HttpInternalServerErrorException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Exceptions\HttpServiceUnavailableException;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UnifiedReport\UI\FoUnifiedReportGenerator;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Request as SlimRequest;
use Slim\Psr7\UploadedFile as SlimUploadedFile;
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
    'decisionexporter',
    'cyclonedx'
  );

  /**
   * @var string[] $importAllowed
   * Allowed report types to be imported.
   */
  private $importAllowed = [
    'decisionimporter',
    'spdxrdf'
  ];

  /**
   * Get the required report for the required upload
   *
   * @param SlimRequest $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function getReport($request, $response, $args)
  {
    $uploadId = $request->getHeaderLine('uploadId');
    $reportFormat = $request->getHeaderLine('reportFormat');

    if (! in_array($reportFormat, $this->reportsAllowed)) {
      throw new HttpBadRequestException(
        "reportFormat must be from [" . implode(",", $this->reportsAllowed) .
        "]");
    }
    $upload = $this->getUpload($uploadId);
    if (get_class($upload) === Info::class) {
      return $response->withJson($upload->getArray(), $upload->getCode());
    }
    $jobId = null;
    $jobQueueId = null;

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
      case $this->reportsAllowed[7]:
        /** @var CycloneDXGeneratorUi $cyclonedxGenerator */
        $cyclonedxGenerator = $this->restHelper->getPlugin('ui_cyclonedx');
        list ($jobId, $jobQueueId) = $cyclonedxGenerator->scheduleAgent(
          $this->restHelper->getGroupId(), $upload);
        break;
      default:
        throw new HttpInternalServerErrorException("Some error occured!");
    }
    $download_path = $this->buildDownloadPath($request, $jobId);
    $info = new Info(201, $download_path, InfoType::INFO);
    return $response->withJson($info->getArray(), $info->getCode());
  }

  /**
   * Get the upload object from a given upload id
   *
   * @param int $uploadId Upload Id to get from
   * @return Fossology::UI::Api::Models::Info|Upload|NULL
   * @throws HttpErrorException
   */
  private function getUpload($uploadId)
  {
    if (empty($uploadId) || ! is_numeric($uploadId) || $uploadId <= 0) {
      throw new HttpBadRequestException("uploadId must be a positive integer!");
    }
    $uploadDao = $this->restHelper->getUploadDao();
    if (! $uploadDao->isAccessible($uploadId, $this->restHelper->getGroupId())) {
      throw new HttpForbiddenException("Upload is not accessible!");
    }
    $upload = $uploadDao->getUpload($uploadId);
    if ($upload === null) {
      throw new HttpNotFoundException("Upload does not exists!");
    }
    return $upload;
  }

  /**
   * Generate the path to download URL based on current request and new Job id
   * @param SlimRequest $request
   * @param integer $jobId The new job id created by agent
   * @return string The path to download the report
   */
  public static function buildDownloadPath($request, $jobId)
  {
    $url_parts = $request->getUri();
    $download_path = "";
    if (!empty($url_parts->getScheme())) {
      $download_path .= $url_parts->getScheme() . "://";
    }
    if (!empty($url_parts->getHost())) {
      $download_path .= $url_parts->getHost();
    }
    if (!empty($url_parts->getPort())) {
      $download_path .= ':' . $url_parts->getPort();
    }
    $endpoint = substr($url_parts->getPath(), 0, strpos($url_parts->getPath(),
        $GLOBALS["apiBasePath"]) + strlen($GLOBALS["apiBasePath"]));
    if (substr($endpoint, -1) !== '/') {
      $endpoint .= '/';
    }
    $endpoint .= "report/" . $jobId;
    $download_path .= $endpoint;
    return $download_path;
  }

  /**
   * Download the report with the given job id
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function downloadReport($request, $response, $args)
  {
    $id = $args['id'];
    $this->checkReport($id);
    /** @var \ui_download $ui_download */
    $ui_download = $this->restHelper->getPlugin('download');
    $responseFile = $ui_download->getReport($args['id']);
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
    return $newResponse->withBody(
      $sf->createStreamFromFile($responseContent->getPathname())
    );
  }

  /**
   * Check if a report is scheduled with the given job id
   *
   * @param int $id Job id
   * @return boolean
   * @throws HttpErrorException
   */
  private function checkReport($id)
  {
    $dbManager = $this->dbHelper->getDbManager();
    $row = $dbManager->getSingleRow(
      'SELECT jq_type FROM jobqueue WHERE jq_job_fk = $1', array(
        $id
      ), "reportValidity");
    if (! in_array($row['jq_type'], $this->reportsAllowed)) {
      throw new HttpNotFoundException(
        "No report scheduled with given job id.");
    }
    $row = $dbManager->getSingleRow('SELECT job_upload_fk FROM job WHERE job_pk = $1',
      array($id), "reportFileUpload");
    $uploadId = intval($row['job_upload_fk']);
    $uploadDao = $this->restHelper->getUploadDao();
    if (! $uploadDao->isAccessible($uploadId, $this->restHelper->getGroupId())) {
      throw new HttpForbiddenException("Report is not accessible!");
    }
    $row = $dbManager->getSingleRow('SELECT * FROM reportgen WHERE job_fk = $1',
      array($id), "reportFileName");
    if (empty($row)) {
      throw (new HttpServiceUnavailableException(
        "Report is not ready. Retry after 10s."))
      ->setHeaders(['Retry-After' => '10']);
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
   * @throws HttpErrorException
   */
  public function importReport(ServerRequestInterface $request,
                               ResponseHelper $response, array $args): ResponseHelper
  {
    $query = $request->getQueryParams();
    if (!array_key_exists("upload", $query)) {
      throw new HttpBadRequestException("Missing query param 'upload'");
    }
    if (!array_key_exists("reportFormat", $query) ||
        !in_array($query["reportFormat"], $this->importAllowed)) {
      throw new HttpBadRequestException(
        "Missing or wrong query param 'reportFormat'");
    }
    $upload_pk = intval($query['upload']);
    // checking if the scheduler is running or not
    $commu_status = fo_communicate_with_scheduler('status',
      $response_from_scheduler, $error_info);
    if (!$commu_status) {
      throw new HttpServiceUnavailableException("Scheduler is not running!");
    }
    $files = $request->getUploadedFiles();

    $this->uploadAccessible($upload_pk);
    if (empty($files['report'])) {
      throw new HttpBadRequestException("No file uploaded");
    }
    /** @var SlimUploadedFile $slimFile */
    $slimFile = $files['report'];

    $reportFormat = $query["reportFormat"];
    switch ($reportFormat) {
      case $this->importAllowed[0]:
        $returnVal = $this->importDecisionJson($request, $response,
          $upload_pk, $slimFile);
        break;
      case $this->importAllowed[1]:
        $returnVal = $this->importSpdxReport($request, $response, $upload_pk,
          $slimFile);
        break;
      default:
        throw new HttpBadRequestException(
          "Report format $reportFormat not supported. Supported formats are [" .
          implode(", ", $this->importAllowed) . "]");
    }
    return $returnVal;
  }

  /**
   * Run decision importer agent for JSON report.
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param int $uploadId
   * @param SlimUploadedFile $slimFile
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  private function importDecisionJson(ServerRequestInterface $request,
                                      ResponseHelper $response, int $uploadId,
                                      SlimUploadedFile $slimFile): ResponseHelper
  {
    $this->throwNotAdminException();
    /** @var AgentDecisionImporterPlugin $decisionImporter */
    $decisionImporter = $this->restHelper->getPlugin("ui_fodecisionimporter");
    $symfonyRequest = new Request();

    $reqBody = $this->getParsedBody($request);

    if (!array_key_exists("importerUser", $reqBody)) {
      throw new HttpBadRequestException("Missing parameter 'importerUser'");
    }

    $importerUser = intval($reqBody["importerUser"]);
    if (empty($importerUser)) {
      $importerUser = $this->restHelper->getUserId();
    }

    $uploadedFile = new UploadedFile($slimFile->getFilePath(),
      $slimFile->getClientFilename(), $slimFile->getClientMediaType());

    $symfonyRequest->files->set('report', $uploadedFile);
    $symfonyRequest->request->set('uploadselect', $uploadId);
    $symfonyRequest->request->set('userselect', $importerUser);

    $agentResp = $decisionImporter->handleRequest($symfonyRequest);

    if ($agentResp === false) {
      throw new HttpBadRequestException("Missing required fields");
    }
    $info = new Info(201, intval($agentResp[0]), InfoType::INFO);
    return $response->withJson($info->getArray(), $info->getCode());
  }

  /**
   * Import a SPDX RDF report
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param int $upload_pk
   * @param SlimUploadedFile $slimFile
   * @return ResponseHelper
   */
  public function importSpdxReport(ServerRequestInterface $request,
                                   ResponseHelper $response, int $upload_pk,
                                   SlimUploadedFile $slimFile): ResponseHelper
  {
    $reqBody = $this->getParsedBody($request);
    /** @var \ReportImportPlugin $reportImport */
    $reportImport = $this->restHelper->getPlugin('ui_reportImport');
    $symfonyRequest = new Request();

    // moving the uploaded file to the ReportImport Directory
    global $SysConf;
    $fileBase = $SysConf['FOSSOLOGY']['path'] . "/ReportImport/";
    if (!is_dir($fileBase)) {
      mkdir($fileBase, 0755, true);
    }
    $targetFile = time() . '_' . rand() . '_' . $slimFile->getClientFilename();
    $slimFile->moveTo($fileBase . $targetFile);

    // Get default values for parameters
    $addNewLicensesAs = "candidate";
    $addLicenseInfoFromInfoInFile = "true";
    $addLicenseInfoFromConcluded = "false";
    $addConcludedAsDecisions = "true";
    $addConcludedAsDecisionsTBD = "true";
    $addCopyrights = "false";
    if (array_key_exists("addNewLicensesAs", $reqBody) &&
        $reqBody["addNewLicensesAs"] === "license") {
      $addNewLicensesAs = "license";
    }
    if (array_key_exists("addLicenseInfoFromInfoInFile", $reqBody) &&
          !filter_var($reqBody["addLicenseInfoFromInfoInFile"],
            FILTER_VALIDATE_BOOLEAN)) {
      $addLicenseInfoFromInfoInFile = "false";
    }
    if (array_key_exists("addLicenseInfoFromConcluded", $reqBody) &&
        filter_var($reqBody["addLicenseInfoFromConcluded"],
          FILTER_VALIDATE_BOOLEAN)) {
      $addLicenseInfoFromConcluded = "true";
    }
    if (array_key_exists("addConcludedAsDecisions", $reqBody) &&
        !filter_var($reqBody["addConcludedAsDecisions"],
          FILTER_VALIDATE_BOOLEAN)) {
      $addConcludedAsDecisions = "false";
    }
    if (array_key_exists("addConcludedAsDecisionsTBD", $reqBody) &&
        !filter_var($reqBody["addConcludedAsDecisionsTBD"],
          FILTER_VALIDATE_BOOLEAN)) {
      $addConcludedAsDecisionsTBD = "false";
    }
    if (array_key_exists("addCopyrights", $reqBody) &&
        filter_var($reqBody["addCopyrights"],
          FILTER_VALIDATE_BOOLEAN)) {
      $addCopyrights = "true";
    }

    // translating values for symfony request
    $symfonyRequest->request->set('addNewLicensesAs', $addNewLicensesAs);
    $symfonyRequest->request->set('addLicenseInfoFromInfoInFile',
      $addLicenseInfoFromInfoInFile);
    $symfonyRequest->request->set('addLicenseInfoFromConcluded',
      $addLicenseInfoFromConcluded);
    $symfonyRequest->request->set('addConcludedAsDecisions',
      $addConcludedAsDecisions);
    $symfonyRequest->request->set('addConcludedAsDecisionsTBD',
      $addConcludedAsDecisionsTBD);
    $symfonyRequest->request->set('addCopyrights', $addCopyrights);

    $agentResp = $reportImport->runImport($upload_pk, $targetFile, $symfonyRequest);
    $returnVal = new Info(201, intval($agentResp[0]), InfoType::INFO);
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }
}
