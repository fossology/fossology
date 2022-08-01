<?php
/*
 SPDX-FileCopyrightText: © 2018, 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Helper to handle package uploads
 */
namespace Fossology\UI\Api\Helper;

use Slim\Psr7\Request;
use Fossology\UI\Api\Helper\UploadHelper\HelperToUploadFilePage;
use Fossology\UI\Api\Helper\UploadHelper\HelperToUploadVcsPage;
use Fossology\UI\Api\Helper\UploadHelper\HelperToUploadUrlPage;
use Fossology\UI\Api\Helper\UploadHelper\HelperToUploadSrvPage;
use Fossology\UI\Api\Models\UploadSummary;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Proxy\ScanJobProxy;
use Fossology\Lib\Proxy\UploadTreeProxy;
use Fossology\Lib\Dao\AgentDao;
use Fossology\UI\Api\Models\Findings;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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
   * @var HelperToUploadUrlPage $uploadUrlPage
   * Object to handle URL based uploads
   */
  private $uploadUrlPage;

  /**
   * @var HelperToUploadSrvPage $uploadSrvPage
   * Object to handle server based uploads
   */
  private $uploadSrvPage;

  /**
   * @var array VALID_VCS_TYPES
   * Array of valid inputs for vcsType parameter
   */
  const VALID_VCS_TYPES = array(
    "git",
    "svn"
  );

  /**
   * @var array VALID_UPLOAD_TYPES
   * Array of valid inputs for uploadType parameter
   */
  const VALID_UPLOAD_TYPES = array(
    "vcs",
    "url",
    "server"
  );

  /**
   * Constructor to get UploadFilePage and UploadVcsPage objects.
   */
  public function __construct()
  {
    $this->uploadFilePage = new HelperToUploadFilePage();
    $this->uploadVcsPage = new HelperToUploadVcsPage();
    $this->uploadUrlPage = new HelperToUploadUrlPage();
    $this->uploadSrvPage = new HelperToUploadSrvPage();
  }

  /**
   * Get a request from Slim and translate to Symfony request to be
   * processed by FOSSology
   *
   * @param array|null $request
   * @param string $folderId ID of the folder to upload the file
   * @param string $fileDescription Description of file uploaded
   * @param string $isPublic   Upload is `public, private or protected`
   * @param boolean $ignoreScm True if the SCM should be ignored.
   * @param string $uploadType Type of upload (if other than file)
   * @param boolean $applyGlobal True if global decisions should be applied.
   * @return array Array with status, message and upload id
   * @see createVcsUpload()
   * @see createFileUpload()
   */
  public function createNewUpload($reqBody, $folderId, $fileDescription,
    $isPublic, $ignoreScm, $uploadType, $applyGlobal = false)
  {
    $symReq = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
    $uploadedFile = $symReq->files->get($this->uploadFilePage::FILE_INPUT_NAME,
      null);

    if ($applyGlobal) {
      // If global decisions should be ignored
      $applyGlobal = 1;
    } else {
      $applyGlobal = 0;
    }

    if (! empty($ignoreScm) && ($ignoreScm == "true")) {
      // If SCM should be ignored
      $ignoreScm = 1;
    } else {
      $ignoreScm = 0;
    }
    if (empty($uploadedFile)) {
      if (empty($uploadType)) {
        return array(false, "Missing 'uploadType' header",
          "Send file with parameter " . $this->uploadFilePage::FILE_INPUT_NAME .
          " or define 'uploadType' header with appropriate body.",
          - 1
        );
      }
      return $this->handleUpload($reqBody, $uploadType, $folderId,
        $fileDescription, $isPublic, $ignoreScm, $applyGlobal);
    } else {
      return $this->createFileUpload($uploadedFile, $folderId,
        $fileDescription, $isPublic, $ignoreScm, $applyGlobal);
    }
  }

  /**
   * Create request required by UploadFilePage
   *
   * @param UploadedFile $uploadedFile Uploaded file object
   * @param string $folderId    ID of the folder to upload the file
   * @param string $fileDescription Description of file uploaded
   * @param string $isPublic    Upload is `public, private or protected`
   * @param integer $ignoreScm  1 if the SCM should be ignored.
   * @param integer $applyGlobal 1 if global decisions should be applied.
   * @return array Array with status, message and upload id
   */
  private function createFileUpload($uploadedFile, $folderId, $fileDescription,
    $isPublic, $ignoreScm = 0, $applyGlobal = 0)
  {
    $symfonyRequest = new \Symfony\Component\HttpFoundation\Request();
    $symfonySession = $GLOBALS['container']->get('session');
    $symfonySession->set(
      $this->uploadFilePage::UPLOAD_FORM_BUILD_PARAMETER_NAME, "restUpload");

    $symfonyRequest->request->set($this->uploadFilePage::FOLDER_PARAMETER_NAME,
      $folderId);
    $symfonyRequest->request->set(
      $this->uploadFilePage::DESCRIPTION_INPUT_NAME,
      [$fileDescription]);
    $symfonyRequest->files->set($this->uploadFilePage::FILE_INPUT_NAME,
      [$uploadedFile]);
    $symfonyRequest->setSession($symfonySession);
    $symfonyRequest->request->set(
      $this->uploadFilePage::UPLOAD_FORM_BUILD_PARAMETER_NAME, "restUpload");
    $symfonyRequest->request->set('public', $isPublic);
    $symfonyRequest->request->set('globalDecisions', $applyGlobal);
    $symfonyRequest->request->set('scm', $ignoreScm);

    return $this->uploadFilePage->handleRequest($symfonyRequest);
  }

  /**
   * Create request required by Upload pages
   *
   * @param array $body Parsed upload request
   * @param string $uploadType Type of upload (url, vcs or server)
   * @param string $folderId   ID of the folder to upload the file
   * @param string $fileDescription Description of file uploaded
   * @param string $isPublic   Upload is `public, private or protected`
   * @param integer $ignoreScm 1 if the SCM should be ignored.
   * @param integer $applyGlobal 1 if global decisions should be applied.
   * @return array Array with status, message and upload id
   */
  private function handleUpload($body, $uploadType, $folderId, $fileDescription,
    $isPublic, $ignoreScm = 0, $applyGlobal = 0)
  {
    $sanity = false;
    switch ($uploadType) {
      case "vcs":
        $sanity = $this->sanitizeVcsData($body);
        break;
      case "url":
        $sanity = $this->sanitizeUrlData($body);
        break;
      case "server":
        $sanity = $this->sanitizeSrvData($body);
        break;
      default:
        $message = "Invalid 'uploadType'";
        $statusDescription = "uploadType should be any of (" .
          implode(",", self::VALID_UPLOAD_TYPES) . ")";
        $code = 400;
        $sanity = array(false, $message, $statusDescription, $code);
    }
    if ($sanity !== true) {
      return $sanity;
    }
    $uploadResponse = false;
    switch ($uploadType) {
      case "vcs":
        $uploadResponse = $this->generateVcsUpload($body, $folderId,
          $fileDescription, $isPublic, $ignoreScm, $applyGlobal);
        break;
      case "url":
        $uploadResponse = $this->generateUrlUpload($body, $folderId,
          $fileDescription, $isPublic, $ignoreScm, $applyGlobal);
        break;
      case "server":
        $uploadResponse = $this->generateSrvUpload($body, $folderId,
          $fileDescription, $isPublic, $ignoreScm, $applyGlobal);
        break;
    }
    return $uploadResponse;
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
   * @brief Check if the passed URL object is correct or not.
   *
   * 1. Check if all the required parameters are passed by user.
   * 2. Add missing keys with empty data to prevent warnings.
   *
   * @param array $urlData Parsed URL object to be sanitized
   * @return array|boolean True if everything is correct, error array otherwise
   */
  private function sanitizeUrlData(&$urlData)
  {
    $message = "";
    $statusDescription = "";
    $code = 0;

    if (! array_key_exists("url", $urlData)) {
      $message = "Missing url";
      $statusDescription = "Missing upload url from request";
      $code = 400;
    }

    if (! array_key_exists("name", $urlData)) {
      $urlData["name"] = "";
    }
    if (! array_key_exists("accept", $urlData)) {
      $urlData["accept"] = "";
    }
    if (! array_key_exists("reject", $urlData)) {
      $urlData["reject"] = "";
    }
    if (! array_key_exists("maxRecursionDepth", $urlData)) {
      $urlData["maxRecursionDepth"] = "";
    }
    if ($code !== 0) {
      return array(false, $message, $statusDescription, $code);
    } else {
      return true;
    }
  }

  /**
   * @brief Check if the passed server upload object is correct or not.
   *
   * 1. Check if all the required parameters are passed by user.
   * 2. Add missing keys with empty data to prevent warnings.
   *
   * @param array $srvData Parsed server upload object to be sanitized
   * @return array|boolean True if everything is correct, error array otherwise
   */
  private function sanitizeSrvData(&$srvData)
  {
    $message = "";
    $statusDescription = "";
    $code = 0;

    if (! array_key_exists("path", $srvData)) {
      $message = "Missing path";
      $statusDescription = "Missing upload path from request";
      $code = 400;
    }

    if (! array_key_exists("name", $srvData)) {
      $srvData["name"] = "";
    }
    if ($code !== 0) {
      return array(false, $message, $statusDescription, $code);
    } else {
      return true;
    }
  }

  /**
   * Generate the upload by calling handleRequest of HelperToUploadVcsPage
   * @param array   $vcsData         Information from POST
   * @param string  $folderId        ID of the folder
   * @param string  $fileDescription Description of the upload
   * @param string  $isPublic        Upload is `public, private or protected`
   * @param integer $ignoreScm       1 if the SCM should be ignored.
   * @param boolean $applyGlobal     1 if global decisions should be applied.
   * @return array Array with status, message and upload id
   */
  private function generateVcsUpload($vcsData, $folderId, $fileDescription,
    $isPublic, $ignoreScm, $applyGlobal)
  {
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
      $folderId);
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
    $symfonyRequest->request->set('globalDecisions', $applyGlobal);
    $symfonyRequest->request->set('scm', $ignoreScm);

    return $this->uploadVcsPage->handleRequest($symfonyRequest);
  }

  /**
   * Generate the upload by calling handleRequest of HelperToUploadUrlPage
   * @param array   $urlData         Information from POST
   * @param string  $folderId        ID of the folder
   * @param string  $fileDescription Description of the upload
   * @param string  $isPublic        Upload is `public, private or protected`
   * @param integer $ignoreScm       1 if the SCM should be ignored.
   * @param integer $applyGlobal     1 if global decisions should be applied.
   * @return array Array with status, message and upload id
   */
  private function generateUrlUpload($urlData, $folderName, $fileDescription,
    $isPublic, $ignoreScm, $applyGlobal)
  {
    $url = $urlData["url"];
    $name = $urlData["name"];
    $accept = $urlData["accept"];
    $reject = $urlData["reject"];
    $maxRecursionDepth = $urlData["maxRecursionDepth"];

    $symfonySession = $GLOBALS['container']->get('session');
    $symfonySession->set($this->uploadUrlPage::UPLOAD_FORM_BUILD_PARAMETER_NAME,
      "restUpload");

    $symfonyRequest = new \Symfony\Component\HttpFoundation\Request();
    $symfonyRequest->setSession($symfonySession);

    $symfonyRequest->request->set($this->uploadUrlPage::FOLDER_PARAMETER_NAME,
      $folderName);
    $symfonyRequest->request->set($this->uploadUrlPage::DESCRIPTION_INPUT_NAME,
      $fileDescription);
    $symfonyRequest->request->set(
      $this->uploadUrlPage::UPLOAD_FORM_BUILD_PARAMETER_NAME, "restUpload");
    $symfonyRequest->request->set('public', $isPublic);
    $symfonyRequest->request->set($this->uploadUrlPage::NAME_PARAM, $name);
    $symfonyRequest->request->set($this->uploadUrlPage::ACCEPT_PARAM, $accept);
    $symfonyRequest->request->set($this->uploadUrlPage::REJECT_PARAM, $reject);
    $symfonyRequest->request->set($this->uploadUrlPage::GETURL_PARAM, $url);
    $symfonyRequest->request->set($this->uploadUrlPage::LEVEL_PARAM,
      $maxRecursionDepth);
    $symfonyRequest->request->set('globalDecisions', $applyGlobal);
    $symfonyRequest->request->set('scm', $ignoreScm);

    return $this->uploadUrlPage->handleRequest($symfonyRequest);
  }

  /**
   * Generate the upload by calling handleRequest of HelperToUploadSrvPage
   * @param array   $srvData         Information from POST
   * @param string  $folderId        ID of the folder
   * @param string  $fileDescription Description of the upload
   * @param string  $isPublic        Upload is `public, private or protected`
   * @param integer $ignoreScm       1 if the SCM should be ignored.
   * @param integer $applyGlobal     1 if global decisions should be applied.
   * @return array Array with status, message and upload id
   */
  private function generateSrvUpload($srvData, $folderName, $fileDescription,
    $isPublic, $ignoreScm, $applyGlobal)
  {
    $path = $srvData["path"];
    $name = $srvData["name"];

    $symfonySession = $GLOBALS['container']->get('session');
    $symfonySession->set($this->uploadSrvPage::UPLOAD_FORM_BUILD_PARAMETER_NAME,
      "restUpload");

    $symfonyRequest = new \Symfony\Component\HttpFoundation\Request();
    $symfonyRequest->setSession($symfonySession);

    $symfonyRequest->request->set($this->uploadSrvPage::FOLDER_PARAMETER_NAME,
      $folderName);
    $symfonyRequest->request->set($this->uploadSrvPage::DESCRIPTION_INPUT_NAME,
      $fileDescription);
    $symfonyRequest->request->set(
      $this->uploadSrvPage::UPLOAD_FORM_BUILD_PARAMETER_NAME, "restUpload");
    $symfonyRequest->request->set('public', $isPublic);
    $symfonyRequest->request->set($this->uploadSrvPage::SOURCE_FILES_FIELD,
      $path);
    $symfonyRequest->request->set($this->uploadSrvPage::NAME_PARAM, $name);
    $symfonyRequest->request->set('globalDecisions', $applyGlobal);
    $symfonyRequest->request->set('scm', $ignoreScm);

    return $this->uploadSrvPage->handleRequest($symfonyRequest);
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
    $copyrightDao = $container->get('dao.copyright');
    $agentDao = $container->get('dao.agent');

    $agentName = "copyright";
    $uploadTreeTableName = $uploadDao->getUploadtreeTableName($uploadId);

    $noLicenseUploadTreeView = new UploadTreeProxy($uploadId,
      array(UploadTreeProxy::OPT_SKIP_THESE => "noLicense",
        UploadTreeProxy::OPT_GROUP_ID => $groupId),
      $uploadTreeTableName,
      'no_license_uploadtree' . $uploadId);
    $clearingCount = $noLicenseUploadTreeView->count();

    $nonClearedUploadTreeView = new UploadTreeProxy($uploadId,
      array(UploadTreeProxy::OPT_SKIP_THESE => "alreadyCleared",
        UploadTreeProxy::OPT_GROUP_ID => $groupId),
      $uploadTreeTableName,
      'already_cleared_uploadtree' . $uploadId);
    $filesToBeCleared = $nonClearedUploadTreeView->count();

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
    $summary->setAssignee($uploadDao->getAssignee($uploadId, $groupId));
    if ($mainLicenses !== null) {
      $summary->setMainLicense(implode(",", $mainLicenses));
    }
    $summary->setUniqueLicenses($hist['uniqueLicenseCount']);
    $summary->setTotalLicenses($hist['scannerLicenseCount']);
    $summary->setUniqueConcludedLicenses($hist['editedUniqueLicenseCount']);
    $summary->setTotalConcludedLicenses($hist['editedLicenseCount']);
    $summary->setFilesToBeCleared($filesToBeCleared);
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
   * Get the copyright list for given upload scanned by copyright agent
   * @param integer $uploadId        Upload ID
   * @return array Array containing `copyright` and
   * `filepath` for each upload tree item
   */
  public function getUploadCopyrightList($uploadId)
  {
    global $container;
    $restHelper = $container->get('helper.restHelper');
    $uploadDao = $restHelper->getUploadDao();
    $agentDao = $container->get('dao.agent');

    $uploadTreeTableName = $uploadDao->getUploadtreeTableName($uploadId);
    $parent = $uploadDao->getParentItemBounds($uploadId, $uploadTreeTableName);

    $scanProx = new ScanJobProxy($agentDao, $uploadId);
    /** @var UIExportList $copyrightListObj
     * UIExportList object to get copyright
     */

    $copyrightListObj = $restHelper->getPlugin('export-list');
    $copyrightList = $copyrightListObj->getCopyrights($uploadId,
      $parent->getItemId(), $uploadTreeTableName, -1, '');
    if (array_key_exists("warn", $copyrightList)) {
      unset($copyrightList["warn"]);
    }

    $responseList = array();
    foreach ($copyrightList as $copyFilepath) {
      $flag=0;
      foreach ($responseList as $response) {
        if ($copyFilepath['content'] == $response['copyright']) {
          $flag=1;
          break;
        }
      }
      if ($flag==0) {
        $copyrightContent = array();
        foreach ($copyrightList as $copy) {
          if (strcasecmp($copyFilepath['content'], $copy['content']) == 0) {
            $copyrightContent[] = $copy['filePath'];
          }
        }
        $responseRow = array();
        $responseRow['copyright'] = $copyFilepath['content'];
        $responseRow['filePath'] = $copyrightContent;
        $responseList[] = $responseRow;
      }
    }
    return $responseList;
  }

  /**
   * Get the license and copyright list for given upload scanned by provided agents
   * @param integer $uploadId        Upload ID
   * @param array $agents            List of agents to get list from
   * @param boolean $printContainers If true, print container info also
   * @param boolean $boolLicense If true, return license
   * @param boolean $boolCopyright If true return copyright also
   * @return array Array containing `filePath`, `agentFindings` and
   * `conclusions` for each upload tree item
   */
  public function getUploadLicenseList($uploadId, $agents, $printContainers, $boolLicense, $boolCopyright)
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

    /** @var UIExportList $licenseListObj
     * UIExportList object to get licenses
     */
    if ($boolLicense) {
      $licenseListObj = $restHelper->getPlugin('export-list');
      $licenseList = $licenseListObj->createListOfLines($uploadTreeTableName,
        $parent->getItemId(), $agent_ids, -1, true, '', !$printContainers);
      if (array_key_exists("warn", $licenseList)) {
        unset($licenseList["warn"]);
      }
    }

    /** @var UIExportList $copyrightListObj
     * UIExportList object to get copyright
     */
    if ($boolCopyright) {
      $copyrightListObj = $restHelper->getPlugin('export-list');
      $copyrightList = $copyrightListObj->getCopyrights($uploadId,
        $parent->getItemId(), $uploadTreeTableName, -1, '');
      if (array_key_exists("warn", $copyrightList)) {
        unset($copyrightList["warn"]);
      }
    }

    $responseList = array();

    if ($boolLicense) {
      foreach ($licenseList as $license) {
        if ($boolCopyright) {
          $copyrightContent = array();
          foreach ($copyrightList as $copy) {
            if (($license['filePath'] == $copy['filePath']) !== false ) {
              array_push($copyrightContent,$copy['content']);
            }
          }
          if (count($copyrightContent)==0) {
            $copyrightContent = null;
          }
        }

        $findings = new Findings($license['agentFindings'],
          $license['conclusions'], $copyrightContent);
        $responseRow = array();
        $responseRow['filePath'] = $license['filePath'];
        $responseRow['findings'] = $findings->getArray();
        $responseList[] = $responseRow;
      }
    } elseif (!$boolLicense && $boolCopyright) {
      foreach ($copyrightList as $copyFilepath) {
        $copyrightContent = array();
        foreach ($copyrightList as $copy) {
          if (($copyFilepath['filePath'] == $copy['filePath']) === true) {
            array_push($copyrightContent,$copy['content']);
          }
        }
        $findings = new Findings();
        $findings->setCopyright($copyrightContent);
        $responseRow = array();
        $responseRow['filePath'] = $copy['filePath'];
        $responseRow['copyright'] = $findings->getCopyright();
        $responseList[] = $responseRow;
      }
    }
    return $responseList;
  }
}
