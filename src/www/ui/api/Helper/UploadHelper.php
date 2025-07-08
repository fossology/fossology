<?php
/*
 SPDX-FileCopyrightText: © 2018, 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>
 SPDX-FileContributor: Kaushlendra Pratap <kaushlendra-pratap.singh@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Helper to handle package uploads
 */
namespace Fossology\UI\Api\Helper;

use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Exception;
use Fossology\Lib\Proxy\ScanJobProxy;
use Fossology\Lib\Proxy\UploadTreeProxy;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpForbiddenException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Helper\UploadHelper\HelperToUploadFilePage;
use Fossology\UI\Api\Helper\UploadHelper\HelperToUploadSrvPage;
use Fossology\UI\Api\Helper\UploadHelper\HelperToUploadUrlPage;
use Fossology\UI\Api\Helper\UploadHelper\HelperToUploadVcsPage;
use Fossology\UI\Api\Models\Analysis;
use Fossology\UI\Api\Models\ApiVersion;
use Fossology\UI\Api\Models\Decider;
use Fossology\UI\Api\Models\FileLicenses;
use Fossology\UI\Api\Models\Findings;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\Reuser;
use Fossology\UI\Api\Models\Scancode;
use Fossology\UI\Api\Models\ScanOptions;
use Fossology\UI\Api\Models\UploadSummary;
use Fossology\UI\Page\BrowseLicense;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use UIExportList;

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
   * Schedule Analysis after the upload
   * @param integer $uploadId Upload ID
   * @param integer $folderId Folder ID
   * @param array $scanOptionsJSON scanOptions
   * @param boolean $newUpload Request is for new upload?
   * @return Info Response
   * @throws HttpBadRequestException If no parameters are selected for agents
   * @throws HttpForbiddenException If the user does not have write access to the upload
   * @throws HttpNotFoundException If the folder does not contain the upload
   */
  public function handleScheduleAnalysis($uploadId, $folderId, $scanOptionsJSON,
                                         $newUpload = false, $apiVersion = ApiVersion::V1)
  {
    global $container;
    $restHelper = $container->get('helper.restHelper');

    $parametersSent = false;
    $analysis = new Analysis();

    if (array_key_exists("analysis", $scanOptionsJSON) && ! empty($scanOptionsJSON["analysis"])) {
      $analysis->setUsingArray($scanOptionsJSON["analysis"], $apiVersion);
      $parametersSent = true;
    }

    $decider = new Decider();
    $decider->setDeciderAgentPlugin($restHelper->getPlugin('agent_decider'));
    if (array_key_exists("decider", $scanOptionsJSON) && ! empty($scanOptionsJSON["decider"])) {
      $decider->setUsingArray($scanOptionsJSON["decider"], $apiVersion);
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
        $reuser->setUsingArray($scanOptionsJSON["reuse"], $apiVersion);
        $parametersSent = true;
      }
    } catch (\UnexpectedValueException $e) {
      throw new HttpBadRequestException($e->getMessage(), $e);
    }

    if (! $parametersSent) {
      throw new HttpBadRequestException("No parameters selected for agents!");
    }

    $scanOptions = new ScanOptions($analysis, $reuser, $decider, $scancode);
    return $scanOptions->scheduleAgents($folderId, $uploadId, $newUpload);
  }


  /**
   * Get a request from Slim and translate to Symfony request to be
   * processed by FOSSology
   *
   * @param array|null $reqBody
   * @param string $folderId ID of the folder to upload the file
   * @param string $fileDescription Description of file uploaded
   * @param string $isPublic   Upload is `public, private or protected`
   * @param boolean $ignoreScm True if the SCM should be ignored.
   * @param boolean $excludefolder True if the Configured Folders should be ignored.
   * @param string $uploadType Type of upload (if other than file)
   * @param boolean $applyGlobal True if global decisions should be applied.
   * @return array Array with status, message and upload id
   * @see createVcsUpload()
   * @see createFileUpload()
   */
  public function createNewUpload($reqBody, $folderId, $fileDescription,
                                  $isPublic, $ignoreScm, $uploadType,
                                  $applyGlobal = false, $excludefolder = false)
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
    if ($excludefolder) {
      // If configured folder should be ignored
      $excludefolder = 1;
    } else {
      $excludefolder = 0;
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
        $fileDescription, $isPublic, $ignoreScm, $applyGlobal, $excludefolder);
    } else {
      return $this->createFileUpload($uploadedFile, $folderId,
        $fileDescription, $isPublic, $ignoreScm, $applyGlobal, $excludefolder);
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
   * @param integer $excludefolder  1 if the Configured Folder should be ignored.
   * @param integer $applyGlobal 1 if global decisions should be applied.
   * @return array Array with status, message and upload id
   */
  private function createFileUpload($uploadedFile, $folderId, $fileDescription,
    $isPublic, $ignoreScm = 0, $applyGlobal = 0, $excludefolder = 0)
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
    $symfonyRequest->request->set('excludefolder', $excludefolder);

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
   * @param integer $excludefolder 1 if the Configured Folders should be ignored.
   * @param integer $applyGlobal 1 if global decisions should be applied.
   * @return array Array with status, message and upload id
   */
  private function handleUpload($body, $uploadType, $folderId, $fileDescription,
    $isPublic, $ignoreScm = 0, $applyGlobal = 0, $excludefolder = 0)
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
          $fileDescription, $isPublic, $ignoreScm, $applyGlobal, $excludefolder);
        break;
      case "url":
        $uploadResponse = $this->generateUrlUpload($body, $folderId,
          $fileDescription, $isPublic, $ignoreScm, $applyGlobal, $excludefolder);
        break;
      case "server":
        $uploadResponse = $this->generateSrvUpload($body, $folderId,
          $fileDescription, $isPublic, $ignoreScm, $applyGlobal, $excludefolder);
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
   * @param integer $excludefolder       1 if the Configured Folders should be ignored.
   * @param boolean $applyGlobal     1 if global decisions should be applied.
   * @return array Array with status, message and upload id
   */
  private function generateVcsUpload($vcsData, $folderId, $fileDescription,
    $isPublic, $ignoreScm, $applyGlobal, $excludefolder)
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
    $symfonyRequest->request->set('excludefolder', $excludefolder);

    return $this->uploadVcsPage->handleRequest($symfonyRequest);
  }

  /**
   * Generate the upload by calling handleRequest of HelperToUploadUrlPage
   * @param array   $urlData         Information from POST
   * @param string  $folderName      Name of the folder
   * @param string  $fileDescription Description of the upload
   * @param string  $isPublic        Upload is `public, private or protected`
   * @param integer $ignoreScm       1 if the SCM should be ignored.
   * @param integer $excludefolder   1 if the Configured Folders should be ignored.
   * @param integer $applyGlobal     1 if global decisions should be applied.
   * @return array Array with status, message and upload id
   */
  private function generateUrlUpload($urlData, $folderName, $fileDescription,
    $isPublic, $ignoreScm, $applyGlobal, $excludefolder)
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
    $symfonyRequest->request->set('excludefolder', $excludefolder);

    return $this->uploadUrlPage->handleRequest($symfonyRequest);
  }

  /**
   * Generate the upload by calling handleRequest of HelperToUploadSrvPage
   * @param array   $srvData         Information from POST
   * @param string  $folderName      Name of the folder
   * @param string  $fileDescription Description of the upload
   * @param string  $isPublic        Upload is `public, private or protected`
   * @param integer $ignoreScm       1 if the SCM should be ignored.
   * @param integer $excludefolder   1 if the Configured Folders should be ignored.
   * @param integer $applyGlobal     1 if global decisions should be applied.
   * @return array Array with status, message and upload id
   */
  private function generateSrvUpload($srvData, $folderName, $fileDescription,
    $isPublic, $ignoreScm, $applyGlobal, $excludefolder)
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
    $symfonyRequest->request->set('excludefolder', $excludefolder);

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

    /** @var BrowseLicense $uiLicense */
    $uiLicense = $restHelper->getPlugin("license");
    $hist = $uiLicense->getUploadHist($itemTreeBounds);
    if (!is_array($hist) || !array_key_exists('uniqueLicenseCount', $hist)) {
      $hist = [];
      $hist['uniqueLicenseCount'] = 0;
      $hist['scannerLicenseCount'] = 0;
      $hist['editedUniqueLicenseCount'] = 0;
      $hist['editedLicenseCount'] = 0;
    }

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
    $sql = "SELECT rf_shortname FROM license_ref lf JOIN upload_clearing_license ucl"
            . " ON lf.rf_pk=ucl.rf_fk WHERE upload_fk=$1 AND ucl.group_fk=$2";
    $stmt = __METHOD__.'.collectMainLicenses';
    $rows = $dbManager->getRows($sql, array($uploadId, $groupId), $stmt);
    if (empty($rows)) {
      return null;
    }
    $mainLicenses = [];
    foreach ($rows as $row) {
      $mainLicenses[] = $row['rf_shortname'];
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

    $uploadTreeTableName = $uploadDao->getUploadtreeTableName($uploadId);
    $parent = $uploadDao->getParentItemBounds($uploadId, $uploadTreeTableName);

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
   * Get the clearing status for files within an upload
   * @param ItemTreeBounds $itemTreeBounds ItemTreeBounds object for the uploadtree
   * @param ClearingDao $clearingDao ClearingDao object
   * @param integer $groupId groupId of the user
   * @return string String containing the Clearing status message
   * @throws Exception In case decision type not found
   */
  public function fetchClearingStatus($itemTreeBounds, $clearingDao, $groupId)
  {
    $decTypes = new DecisionTypes;
    if ($itemTreeBounds !== null) {
      $clearingList = $clearingDao->getFileClearings($itemTreeBounds, $groupId);
    } else {
      $clearingList = [];
    }

    $clearingArray = [];
    foreach ($clearingList as $clearingDecision) {
      $clearingArray[] = $clearingDecision->getType();
    }

    if (empty($clearingArray) || $clearingArray[0] === null) {
      return "NOASSERTION";
    } else {
      return $decTypes->getTypeName($clearingArray[0]);
    }
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
  public function getUploadLicenseList($uploadId, $agents, $printContainers, $boolLicense, $boolCopyright, $page = 0, $limit = 50, $apiVersion=ApiVersion::V1)
  {
    global $container;
    $restHelper = $container->get('helper.restHelper');
    $uploadDao = $restHelper->getUploadDao();
    $agentDao = $container->get('dao.agent');
    $uploadTreeTableName = $uploadDao->getUploadtreeTableName($uploadId);
    $parent = $uploadDao->getParentItemBounds($uploadId, $uploadTreeTableName);
    $groupId = $restHelper->getGroupId();
    $scanProx = new ScanJobProxy($agentDao, $uploadId);
    $scanProx->createAgentStatus($agents);
    $agent_ids = $scanProx->getLatestSuccessfulAgentIds();
    $clearingDao = $container->get("dao.clearing");

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
        $copyrightContent = null;
        if ($boolCopyright) {
          $copyrightContent = [];
          foreach ($copyrightList as $copy) {
            if (($license['filePath'] == $copy['filePath']) !== false ) {
              $copyrightContent[] = $copy['content'];
            }
          }
          if (count($copyrightContent)==0) {
            $copyrightContent = null;
          }
        }

        $findings = new Findings($license['agentFindings'],
          $license['conclusions'], $copyrightContent);
        $uploadTreeTableId = $license['uploadtree_pk'];
        $uploadtree_tablename = $uploadDao->getUploadtreeTableName($uploadId);
        if ($uploadTreeTableId!==null) {
          $itemTreeBounds = $uploadDao->getItemTreeBounds($uploadTreeTableId,$uploadtree_tablename);
        } else {
          $itemTreeBounds=null;
        }
        $clearingDecision = $this->fetchClearingStatus($itemTreeBounds,
          $clearingDao, $groupId);
        $responseRow = new FileLicenses($license['filePath'], $findings,
          $clearingDecision);
        $responseList[] = $responseRow->getArray($apiVersion);
      }
    } elseif (!$boolLicense && $boolCopyright) {
      foreach ($copyrightList as $copyFilepath) {
        $copyrightContent = array();
        foreach ($copyrightList as $copy) {
          if (($copyFilepath['filePath'] == $copy['filePath']) === true) {
            $copyrightContent[] = $copy['content'];
          }
        }
        $findings = new Findings();
        $findings->setCopyright($copyrightContent);
        $responseRow = new FileLicenses($copyFilepath['filePath'], $findings);
        $responseList[] = $responseRow->getArray($apiVersion);
      }
    }
    $offset = $page * $limit;
    $paginatedResponseList = array_slice($responseList, $offset, $limit);
    $paginatedResponseListSize = sizeof($responseList);
    return array($paginatedResponseList, $paginatedResponseListSize);
  }
}
