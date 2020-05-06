<?php
/**
 * *************************************************************
 * Copyright (C) 2018,2020 Siemens AG
 * Author: Gaurav Mishra <mishra.gaurav@siemens.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * *************************************************************
 */

/**
 * @file
 * @brief Helper to handle package uploads
 */
namespace Fossology\UI\Api\Helper;

use Psr\Http\Message\ServerRequestInterface;
use Fossology\UI\Api\Helper\UploadHelper\HelperToUploadFilePage;
use Fossology\UI\Api\Helper\UploadHelper\HelperToUploadVcsPage;
use Fossology\UI\Api\Models\UploadSummary;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Proxy\ScanJobProxy;
use Fossology\Lib\Dao\AgentDao;

/**
 * @class UploadHelper
 * @brief Handle new file uploads from Slim framework and move to FOSSology
 */
class UploadHelper
{
  /**
   * @var HelperToUploadFilePage $uploadFilePage
   * Object to handle file based uploads
   */
  private $uploadFilePage;

  /**
   * @var HelperToUploadVcsPage $uploadVcsPage
   * Object to handle VCS based uploads
   */
  private $uploadVcsPage;

  /**
   * @var array VALID_VCS_TYPES
   * Array of valid inputs for vcsType parameter
   */
  const VALID_VCS_TYPES = array(
    "git",
    "svn"
  );

  /**
   * Constructor to get UploadFilePage and UploadVcsPage objects.
   */
  public function __construct()
  {
    $this->uploadFilePage = new HelperToUploadFilePage();
    $this->uploadVcsPage = new HelperToUploadVcsPage();
  }

  /**
   * Get a request from Slim and translate to Symfony request to be
   * processed by FOSSology
   *
   * @param ServerRequestInterface $request
   * @param string $folderName Name of the folder to upload the file
   * @param string $fileDescription Description of file uploaded
   * @param string $isPublic   Upload is `public, private or protected`
   * @param boolean $ignoreScm True if the SCM should be ignored.
   * @return array Array with status, message and upload id
   * @see createVcsUpload()
   * @see createFileUpload()
   */
  public function createNewUpload(ServerRequestInterface $request, $folderName,
    $fileDescription, $isPublic, $ignoreScm)
  {
    $uploadedFile = $request->getUploadedFiles();
    $vcsData = $request->getParsedBody();

    if (! empty($ignoreScm) && ($ignoreScm == "true" || $ignoreScm)) {
      // If SCM should be ignored
      $ignoreScm = 1;
    } else {
      $ignoreScm = 0;
    }
    if (empty($uploadedFile) ||
      ! isset($uploadedFile[$this->uploadFilePage::FILE_INPUT_NAME])) {
      if (empty($vcsData)) {
        return array(false, "Missing input",
          "Send file with parameter " . $this->uploadFilePage::FILE_INPUT_NAME .
          " or JSON with VCS parameters.",
          - 1
        );
      }
      return $this->createVcsUpload($vcsData, $folderName, $fileDescription,
        $isPublic, $ignoreScm);
    } else {
      $uploadedFile = $uploadedFile[$this->uploadFilePage::FILE_INPUT_NAME];
      return $this->createFileUpload($uploadedFile, $folderName,
        $fileDescription, $isPublic, $ignoreScm);
    }
  }

  /**
   * Create request required by UploadFilePage
   *
   * @param array $uploadedFile Uploaded file object by Slim
   * @param string $folderName  Name of the folder to upload the file
   * @param string $fileDescription Description of file uploaded
   * @param string $isPublic    Upload is `public, private or protected`
   * @param boolean $ignoreScm  True if the SCM should be ignored.
   * @return array Array with status, message and upload id
   */
  private function createFileUpload($uploadedFile, $folderName, $fileDescription,
    $isPublic, $ignoreScm = 0)
  {
    $path = $uploadedFile->file;
    $originalName = $uploadedFile->getClientFilename();
    $originalMime = $uploadedFile->getClientMediaType();
    $originalError = $uploadedFile->getError();
    $symfonyFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
      $path, $originalName, $originalMime, $originalError);
    $symfonyRequest = new \Symfony\Component\HttpFoundation\Request();
    $symfonySession = $GLOBALS['container']->get('session');
    $symfonySession->set(
      $this->uploadFilePage::UPLOAD_FORM_BUILD_PARAMETER_NAME, "restUpload");

    $symfonyRequest->request->set($this->uploadFilePage::FOLDER_PARAMETER_NAME,
      $folderName);
    $symfonyRequest->request->set($this->uploadFilePage::DESCRIPTION_INPUT_NAME,
      $fileDescription);
    $symfonyRequest->files->set($this->uploadFilePage::FILE_INPUT_NAME,
      $symfonyFile);
    $symfonyRequest->setSession($symfonySession);
    $symfonyRequest->request->set(
      $this->uploadFilePage::UPLOAD_FORM_BUILD_PARAMETER_NAME, "restUpload");
    $symfonyRequest->request->set('public', $isPublic);
    $symfonyRequest->request->set('scm', $ignoreScm);

    return $this->uploadFilePage->handleRequest($symfonyRequest);
  }

  /**
   * Create request required by UploadVcsPage
   *
   * @param array $vcsData     Parsed VCS object from request
   * @param string $folderName Name of the folder to upload the file
   * @param string $fileDescription Description of file uploaded
   * @param string $isPublic   Upload is `public, private or protected`
   * @param boolean $ignoreScm True if the SCM should be ignored.
   * @return array Array with status, message and upload id
   */
  private function createVcsUpload($vcsData, $folderName, $fileDescription,
    $isPublic, $ignoreScm = 0)
  {
    $sanity = $this->sanitizeVcsData($vcsData);
    if ($sanity !== true) {
      return $sanity;
    }
    $vcsType = $vcsData["vcsType"];
    $vcsUrl = $vcsData["vcsUrl"];
    $vcsName = $vcsData["vcsName"];
    $vcsUsername = $vcsData["vcsUsername"];
    $vcsPasswd = $vcsData["vcsPassword"];
    $vcsBranch = $vcsData["vcsBranch"];

    $symfonySession = $GLOBALS['container']->get('session');
    $symfonySession->set($this->uploadVcsPage::UPLOAD_FORM_BUILD_PARAMETER_NAME,
      "restUpload");

    $symfonyRequest = new \Symfony\Component\HttpFoundation\Request();
    $symfonyRequest->setSession($symfonySession);

    $symfonyRequest->request->set($this->uploadVcsPage::FOLDER_PARAMETER_NAME,
      $folderName);
    $symfonyRequest->request->set($this->uploadVcsPage::DESCRIPTION_INPUT_NAME,
      $fileDescription);
    $symfonyRequest->request->set($this->uploadVcsPage::GETURL_PARAM, $vcsUrl);
    $symfonyRequest->request->set(
      $this->uploadVcsPage::UPLOAD_FORM_BUILD_PARAMETER_NAME, "restUpload");
    $symfonyRequest->request->set('public', $isPublic);
    $symfonyRequest->request->set('name', $vcsName);
    $symfonyRequest->request->set('vcstype', $vcsType);
    $symfonyRequest->request->set('username', $vcsUsername);
    $symfonyRequest->request->set('passwd', $vcsPasswd);
    $symfonyRequest->request->set('branch', $vcsBranch);
    $symfonyRequest->request->set('scm', $ignoreScm);

    return $this->uploadVcsPage->handleRequest($symfonyRequest);
  }

  /**
   * @brief Check if the passed VCS object is correct or not.
   *
   * 1. Check if all the required parameters are passed by user.
   * 2. Translate the `vcsType` to required values.
   * 3. Add missing keys with empty data to prevent warnings.
   *
   * @param array $vcsData Parsed VCS object to be sanitized
   * @return array|boolean True if everything is correct, error array otherwise
   */
  private function sanitizeVcsData(&$vcsData)
  {
    $message = "";
    $statusDescription = "";
    $code = 0;

    if (! array_key_exists("vcsType", $vcsData) ||
      ! in_array($vcsData["vcsType"], self::VALID_VCS_TYPES)) {
      $message = "Missing vcsType";
      $statusDescription = "vcsType should be any of (" .
        implode(", ", self::VALID_VCS_TYPES) . ")";
      $code = 400;
    }
    $vcsType = "";
    if ($vcsData["vcsType"] == "git") {
      $vcsType = "Git";
    } else {
      $vcsType = "SVN";
    }

    if (! array_key_exists("vcsUrl", $vcsData)) {
      $message = "Missing vcsUrl";
      $statusDescription = "vcsUrl should be passed.";
      $code = 400;
    }

    if (! array_key_exists("vcsName", $vcsData)) {
      $vcsData["vcsName"] = "";
    }
    if (! array_key_exists("vcsUsername", $vcsData)) {
      $vcsData["vcsUsername"] = "";
    }
    if (! array_key_exists("vcsPassword", $vcsData)) {
      $vcsData["vcsPassword"] = "";
    }
    if (! array_key_exists("vcsBranch", $vcsData)) {
      $vcsData["vcsBranch"] = "";
    }
    $vcsData["vcsType"] = $vcsType;
    if ($code !== 0) {
      return array(false, $message, $statusDescription, $code);
    } else {
      return true;
    }
  }

  /**
   * Generate UploadSummary object for given upload respective to given group id
   * @param integer $uploadId Upload ID
   * @param integer $groupId  Group ID
   * @return Fossology::UI::Api::Models::UploadSummary
   */
  public function generateUploadSummary($uploadId, $groupId)
  {
    global $container;
    $restHelper = $container->get('helper.restHelper');
    $uploadDao = $restHelper->getUploadDao();
    $dbManager = $restHelper->getDbHelper()->getDbManager();
    $clearingDao = $container->get('dao.clearing');
    $copyrightDao = $container->get('dao.copyright');
    $agentDao = $container->get('dao.agent');

    $agentName = "copyright";

    $totalClearings = $clearingDao->getTotalDecisionCount($uploadId, $groupId);
    $clearingCount = $clearingDao->getClearingDecisionsCount($uploadId,
      $groupId);
    $uploadTreeTableName = $uploadDao->getUploadtreeTableName($uploadId);
    $itemTreeBounds = $uploadDao->getParentItemBounds($uploadId,
      $uploadTreeTableName);
    $scanProx = new ScanJobProxy($agentDao, $uploadId);
    $scanProx->createAgentStatus([$agentName]);
    $agents = $scanProx->getLatestSuccessfulAgentIds();
    $copyrightCount = 0;
    if (array_key_exists($agentName, $agents) && ! empty($agents[$agentName])) {
      $copyrightCount = count(
        $copyrightDao->getAllEntriesReport($agentName, $uploadId,
          $uploadTreeTableName, null, false, null, "C.agent_fk = " .
          $agents[$agentName], $groupId));
    }

    $mainLicenses = $this->getMainLicenses($dbManager, $uploadId, $groupId);

    $uiLicense = $restHelper->getPlugin("license");
    $hist = $uiLicense->getUploadHist($itemTreeBounds);

    $summary = new UploadSummary();
    $summary->setUploadId($uploadId);
    $summary->setUploadName($uploadDao->getUpload($uploadId)->getFilename());
    if ($mainLicenses !== null) {
      $summary->setMainLicense(implode(",", $mainLicenses));
    }
    $summary->setUniqueLicenses($hist['uniqueLicenseCount']);
    $summary->setTotalLicenses($hist['scannerLicenseCount']);
    $summary->setUniqueConcludedLicenses($hist['editedUniqueLicenseCount']);
    $summary->setTotalConcludedLicenses($hist['editedLicenseCount']);
    $summary->setFilesToBeCleared($totalClearings - $clearingCount);
    $summary->setFilesCleared($clearingCount);
    $summary->setClearingStatus($uploadDao->getStatus($uploadId, $groupId));
    $summary->setCopyrightCount($copyrightCount);
    return $summary;
  }

  /**
   * Get main license selected for the upload
   * @param DbManager $dbManager DbManager object
   * @param integer $uploadId    Upload ID
   * @param integer $groupId     Group ID
   * @return NULL|array
   */
  private function getMainLicenses($dbManager, $uploadId, $groupId)
  {
    $sql = "SELECT rf_shortname FROM upload_clearing_license ucl, license_ref"
         . " WHERE ucl.group_fk=$1 AND upload_fk=$2 AND ucl.rf_fk=rf_pk;";
    $stmt = __METHOD__.'.collectMainLicenses';
    $rows = $dbManager->getRows($sql, array($groupId, $uploadId), $stmt);
    if (empty($rows)) {
      return null;
    }
    $mainLicenses = [];
    foreach ($rows as $row) {
      array_push($mainLicenses, $row['rf_shortname']);
    }
    return $mainLicenses;
  }

  /**
   * Get the license list for given upload scanned by provided agents
   * @param integer $uploadId        Upload ID
   * @param array $agents            List of agents to get list from
   * @param boolean $printContainers If true, print container info also
   * @return array Array containing `filePath`, `agentFindings` and
   * `conclusions` for each upload tree item
   */
  public function getUploadLicenseList($uploadId, $agents, $printContainers)
  {
    global $container;
    $restHelper = $container->get('helper.restHelper');
    $uploadDao = $restHelper->getUploadDao();
    $agentDao = $container->get('dao.agent');

    $uploadTreeTableName = $uploadDao->getUploadtreeTableName($uploadId);
    $parent = $uploadDao->getParentItemBounds($uploadId, $uploadTreeTableName);

    $scanProx = new ScanJobProxy($agentDao, $uploadId);
    $scanProx->createAgentStatus($agents);
    $agent_ids = $scanProx->getLatestSuccessfulAgentIds();

    /** @var ui_license_list $licenseListObj
     * ui_license_list object to get licenses
     */
    $licenseListObj = $restHelper->getPlugin('license-list');
    $licenseList = $licenseListObj->createListOfLines($uploadTreeTableName,
      $parent->getItemId(), $agent_ids, -1, true, '', !$printContainers);
    if (array_key_exists("warn", $licenseList)) {
      unset($licenseList["warn"]);
    }
    return $licenseList;
  }
}
