<?php
/***************************************************************
 Copyright (C) 2018 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

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
 ***************************************************************/
/**
 * @file
 * @brief Controller for report queries
 */

namespace Fossology\UI\Api\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Data\Upload\Upload;

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
    'unifiedreport'
  );

  /**
   * Get the required report for the required upload
   *
   * @param ServerRequestInterface $request
   * @param ResponseInterface $response
   * @param array $args
   * @return ResponseInterface
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
          $spdxGenerator = $this->restHelper->getPlugin('ui_spdx2');
          list ($jobId, $jobQueueId, $error) = $spdxGenerator->scheduleAgent(
            Auth::getGroupId(), $upload, $reportFormat);
          break;
        case $this->reportsAllowed[3]:
          $readmeGenerator = $this->restHelper->getPlugin('ui_readmeoss');
          list ($jobId, $jobQueueId, $error) = $readmeGenerator->scheduleAgent(
            Auth::getGroupId(), $upload);
          break;
        case $this->reportsAllowed[4]:
          $unifiedGenerator = $this->restHelper->getPlugin('agent_founifiedreport');
          list ($jobId, $jobQueueId, $error) = $unifiedGenerator->scheduleAgent(
            Auth::getGroupId(), $upload);
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
    if ($url_parts["path"][-1] !== '/') {
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
   * @param ResponseInterface $response
   * @param array $args
   * @return ResponseInterface
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
      $responseContent = $responseFile->getFile();
      $newResponse = $response->withHeader('Content-Description',
        'File Transfer')
        ->withHeader('Content-Type',
        $responseFile->headers->get('Content-Type'))
        ->withHeader('Content-Disposition',
        $responseFile->headers->get('Content-Disposition'))
        ->withHeader('Cache-Control', 'must-revalidate')
        ->withHeader('Pragma', 'private')
        ->withHeader('Content-Length', filesize($responseContent));

      readfile($responseContent);
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
}
