<?php
/*
 SPDX-FileCopyrightText: © 2018, 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>,
 Soham Banerjee <sohambanerjee4abc@hotmail.com>
 SPDX-FileCopyrightText: © 2022, 2023 Samuel Dushimimana <dushsam100@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Controller for upload queries
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\DelAgent\UI\DeleteMessages;
use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\UploadStatus;
use Fossology\Lib\Exception;
use Fossology\Lib\Proxy\ScanJobProxy;
use Fossology\Lib\Proxy\UploadBrowseProxy;
use Fossology\Lib\Proxy\UploadTreeProxy;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Helper\UploadHelper;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\License;
use Fossology\UI\Api\Models\Obligation;
use Fossology\UI\Api\Models\ScannedLicense;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\StreamFactory;

/**
 * @class UploadController
 * @brief Controller for Upload model
 */
class UploadController extends RestController
{

  /**
   * Get query parameter name for agent listing
   */
  const AGENT_PARAM = "agent";

  /**
   * Get query parameter name for folder id
   */
  const FOLDER_PARAM = "folderId";

  /**
   * Get query parameter name for recursive listing
   */
  const RECURSIVE_PARAM = "recursive";

  /**
   * Get query parameter name for name filtering
   */
  const FILTER_NAME = "name";

  /**
   * Get query parameter name for status filtering
   */
  const FILTER_STATUS = "status";

  /**
   * Get query parameter name for assignee filtering
   */
  const FILTER_ASSIGNEE = "assignee";

  /**
   * Get query parameter name for since filtering
   */
  const FILTER_DATE = "since";

  /**
   * Get query parameter name for page listing
   */
  const PAGE_PARAM = "page";

  /**
   * Get query parameter name for limiting listing
   */
  const LIMIT_PARAM = "limit";

  /**
   * Limit of uploads in get query
   */
  const UPLOAD_FETCH_LIMIT = 100;

  /**
   * Get query parameter name for container listing
   */
  const CONTAINER_PARAM = "containers";

  /**
   * Valid status inputs
   */
  const VALID_STATUS = ["open", "inprogress", "closed", "rejected"];

  /**
   * Agent names list
   */
  private $agentNames = AgentRef::AGENT_LIST;

  /**
   * @var AgentDao $agentDao
   * Agent Dao object
   */
  private $agentDao;

  public function __construct($container)
  {
    parent::__construct($container);
    $groupId = $this->restHelper->getGroupId();
    $dbManager = $this->dbHelper->getDbManager();
    $this->agentDao = $this->container->get('dao.agent');
    $uploadBrowseProxy = new UploadBrowseProxy($groupId, 0, $dbManager, false);
    $uploadBrowseProxy->sanity();
  }

  /**
   * Get list of uploads for current user
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getUploads($request, $response, $args)
  {
    $id = null;
    $folderId = null;
    $recursive = true;
    $retVal = null;
    $query = $request->getQueryParams();
    $name = null;
    $status = null;
    $assignee = null;
    $since = null;

    if (array_key_exists(self::FOLDER_PARAM, $query)) {
      $folderId = filter_var($query[self::FOLDER_PARAM], FILTER_VALIDATE_INT);
      if (! $this->restHelper->getFolderDao()->isFolderAccessible($folderId,
        $this->restHelper->getUserId())) {
        $info = new Info(404, "Folder does not exist", InfoType::ERROR);
        $retVal = $response->withJson($info->getArray(), $info->getCode());
      }
    }

    if (array_key_exists(self::RECURSIVE_PARAM, $query)) {
      $recursive = filter_var($query[self::RECURSIVE_PARAM],
        FILTER_VALIDATE_BOOLEAN);
    }
    if (array_key_exists(self::FILTER_NAME, $query)) {
      $name = $query[self::FILTER_NAME];
    }
    if (array_key_exists(self::FILTER_STATUS, $query)) {
      switch (strtolower($query[self::FILTER_STATUS])) {
        case "open":
          $status = UploadStatus::OPEN;
          break;
        case "inprogress":
          $status = UploadStatus::IN_PROGRESS;
          break;
        case "closed":
          $status = UploadStatus::CLOSED;
          break;
        case "rejected":
          $status = UploadStatus::REJECTED;
          break;
        default:
          $status = null;
      }
    }
    if (array_key_exists(self::FILTER_ASSIGNEE, $query)) {
      $username = $query[self::FILTER_ASSIGNEE];
      if (strcasecmp($username, "-me-") === 0) {
        $assignee = $this->restHelper->getUserId();
      } elseif (strcasecmp($username, "-unassigned-") === 0) {
        $assignee = 1;
      } else {
        $assignee = $this->restHelper->getUserDao()->getUserByName($username);
        if (! empty($assignee)) {
          $assignee = $assignee['user_pk'];
        } else {
          $info = new Info(404, "No user with user name '$username'",
            InfoType::ERROR);
          $retVal = $response->withJson($info->getArray(), $info->getCode());
        }
      }
    }
    if (array_key_exists(self::FILTER_DATE, $query)) {
      $date = filter_var($query[self::FILTER_DATE], FILTER_VALIDATE_REGEXP,
        ["options" => [
          "regexp" => "/^\d{4}\-\d{2}\-\d{2}$/",
          "flags" => FILTER_NULL_ON_FAILURE
        ]]);
      $since = strtotime($date);
    }

    $page = $request->getHeaderLine(self::PAGE_PARAM);
    if (! empty($page) || $page == "0") {
      $page = filter_var($page, FILTER_VALIDATE_INT);
      if ($page <= 0) {
        $info = new Info(400, "page should be positive integer > 0",
          InfoType::ERROR);
        $retVal = $response->withJson($info->getArray(), $info->getCode());
      }
    } else {
      $page = 1;
    }

    $limit = $request->getHeaderLine(self::LIMIT_PARAM);
    if (! empty($limit)) {
      $limit = filter_var($limit, FILTER_VALIDATE_INT);
      if ($limit < 1) {
        $info = new Info(400, "limit should be positive integer > 1",
          InfoType::ERROR);
        $retVal = $response->withJson($info->getArray(), $info->getCode());
      }
    } else {
      $limit = self::UPLOAD_FETCH_LIMIT;
    }

    if (isset($args['id'])) {
      $id = intval($args['id']);
      $upload = $this->uploadAccessible($this->restHelper->getGroupId(), $id);
      if ($upload !== true) {
        return $response->withJson($upload->getArray(), $upload->getCode());
      }
      $temp = $this->isAdj2nestDone($id, $response);
      if ($temp !== true) {
        $retVal = $temp;
      }
    }
    if ($retVal !== null) {
      return $retVal;
    }
    $options = [
      "folderId" => $folderId,
      "name"     => $name,
      "status"   => $status,
      "assignee" => $assignee,
      "since"    => $since
    ];
    list($pages, $uploads) = $this->dbHelper->getUploads(
      $this->restHelper->getUserId(), $this->restHelper->getGroupId(), $limit,
      $page, $id, $options, $recursive);
    if ($id !== null && ! empty($uploads)) {
      $uploads = $uploads[0];
      $pages = 1;
    }
    return $response->withHeader("X-Total-Pages", $pages)->withJson($uploads,
      200);
  }

  /**
   * Gets file response for each upload
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function uploadDownload($request, $response, $args)
  {
    $ui_download = $this->restHelper->getPlugin('download');
    $id = null;

    if (isset($args['id'])) {
      $id = intval($args['id']);
      $upload = $this->uploadAccessible($this->restHelper->getGroupId(), $id);
      if ($upload !== true) {
        return $response->withJson($upload->getArray(), $upload->getCode());
      }
    }
    $dbManager = $this->restHelper->getDbHelper()->getDbManager();
    $uploadDao = $this->restHelper->getUploadDao();
    $uploadTreeTableName = $uploadDao->getUploadtreeTableName($id);
    $itemTreeBounds = $uploadDao->getParentItemBounds($id,$uploadTreeTableName);
    $sql =  "SELECT pfile_fk , ufile_name FROM uploadtree_a WHERE uploadtree_pk=$1";
    $params = array($itemTreeBounds->getItemId());
    $descendants = $dbManager->getSingleRow($sql,$params);
    $path= RepPath(($descendants['pfile_fk']));
    $responseFile = $ui_download->getDownload($path, $descendants['ufile_name']);
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
    return($newResponse);
  }

  /**
   * Get summary of given upload
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getUploadSummary($request, $response, $args)
  {
    $id = intval($args['id']);
    $query = $request->getQueryParams();
    $selectedAgentId = $query['agentId'];
    $agentDao = $this->container->get('dao.agent');
    $upload = $this->uploadAccessible($this->restHelper->getGroupId(), $id);
    if ($upload !== true) {
      return $response->withJson($upload->getArray(), $upload->getCode());
    } else if ($selectedAgentId !== null && !$this->dbHelper->doesIdExist("agent", "agent_pk", $selectedAgentId)) {
      $error = new Info(404, "Agent does not exist", InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }
    $temp = $this->isAdj2nestDone($id, $response);
    if ($temp !== true) {
      return $temp;
    }
    $uploadHelper = new UploadHelper();
    $uploadSummary = $uploadHelper->generateUploadSummary($id, $this->restHelper->getGroupId());
    $browseLicense = $this->restHelper->getPlugin('license');
    $uploadDao = $this->restHelper->getUploadDao();
    $uploadTreeTableName = $uploadDao->getUploadtreeTableName($id);
    $itemTreeBounds = $uploadDao->getParentItemBounds($id, $uploadTreeTableName);
    $scanJobProxy = new ScanJobProxy($agentDao, $id);
    $scannerAgents = array_keys(AgentRef::AGENT_LIST);
    $scanJobProxy->createAgentStatus($scannerAgents);
    $selectedAgentIds = empty($selectedAgentId) ? $scanJobProxy->getLatestSuccessfulAgentIds() : $selectedAgentId;
    $res = $browseLicense->createLicenseHistogram("", "", $itemTreeBounds, $selectedAgentIds, $this->restHelper->getGroupId());
    $uploadSummary->setUniqueConcludedLicenses($res['editedUniqueLicenseCount']);
    $uploadSummary->setTotalConcludedLicenses($res['editedLicenseCount']);
    $uploadSummary->setTotalLicenses($res['scannerLicenseCount']);
    $uploadSummary->setUniqueLicenses($res['uniqueLicenseCount']);
    $uploadSummary->setConcludedNoLicenseFoundCount($res['editedNoLicenseFoundCount']);
    $uploadSummary->setFileCount($res['fileCount']);
    $uploadSummary->setNoScannerLicenseFoundCount($res['noScannerLicenseFoundCount']);
    $uploadSummary->setScannerUniqueLicenseCount($res['scannerUniqueLicenseCount']);

    return $response->withJson($uploadSummary->getArray(), 200);
  }

  /**
   * Delete a given upload
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function deleteUpload($request, $response, $args)
  {
    require_once dirname(dirname(dirname(dirname(__DIR__)))) .
      "/delagent/ui/delete-helper.php";
    $returnVal = null;
    $id = intval($args['id']);

    $upload = $this->uploadAccessible($this->restHelper->getGroupId(), $id);
    if ($upload !== true) {
      return $response->withJson($upload->getArray(), $upload->getCode());
    }
    $result = TryToDelete($id, $this->restHelper->getUserId(),
      $this->restHelper->getGroupId(), $this->restHelper->getUploadDao());
    if ($result->getDeleteMessageCode() !== DeleteMessages::SUCCESS) {
      $returnVal = new Info(500, $result->getDeleteMessageString(),
        InfoType::ERROR);
    } else {
      $returnVal = new Info(202, "Delete Job for file with id " . $id,
        InfoType::INFO);
    }
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }

  /**
   * Move or copy a given upload to a new folder
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function moveUpload($request, $response, $args)
  {
    $action = $request->getHeaderLine('action');
    if (strtolower($action) == "move") {
      $copy = false;
    } else {
      $copy = true;
    }
    return $this->changeUpload($request, $response, $args, $copy);
  }

  /**
   * Perform copy/move based on $isCopy
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @param boolean $isCopy True to perform copy, else false
   * @return ResponseHelper
   */
  private function changeUpload($request, $response, $args, $isCopy)
  {
    $returnVal = null;
    if ($request->hasHeader('folderId') &&
      is_numeric($newFolderID = $request->getHeaderLine('folderId'))) {
      $id = intval($args['id']);
      $returnVal = $this->restHelper->copyUpload($id, $newFolderID, $isCopy);
    } else {
      $returnVal = new Info(400, "folderId header should be an integer!",
        InfoType::ERROR);
    }
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }

  /**
   * Get a new upload from the POST method
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function postUpload($request, $response, $args)
  {
    $uploadType = $request->getHeaderLine('uploadType');
    if (empty($uploadType)) {
      $uploadType = 'vcs';
    }
    $reqBody = $this->getParsedBody($request);
    $scanOptions = [];
    if (array_key_exists('scanOptions', $reqBody)) {
      if ($uploadType == 'file') {
        $scanOptions = json_decode($reqBody['scanOptions'], true);
      } else {
        $scanOptions = $reqBody['scanOptions'];
      }
    }

    if (! is_array($scanOptions)) {
      $scanOptions = [];
    }

    $uploadHelper = new UploadHelper();

    if ($uploadType != "file" && (empty($reqBody) ||
        ! array_key_exists("location", $reqBody))) {
      $error = new Info(400, "Require location object if uploadType != file.",
        InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    } else if ($request->hasHeader('folderId') &&
      is_numeric($folderId = $request->getHeaderLine('folderId')) && $folderId > 0) {

      $allFolderIds = $this->restHelper->getFolderDao()->getAllFolderIds();
      if (!in_array($folderId, $allFolderIds)) {
        $error = new Info(404, "folderId $folderId does not exists!", InfoType::ERROR);
        return $response->withJson($error->getArray(), $error->getCode());
      }
      if (!$this->restHelper->getFolderDao()->isFolderAccessible($folderId)) {
        $error = new Info(403, "folderId $folderId is not accessible!",
          InfoType::ERROR);
        return $response->withJson($error->getArray(), $error->getCode());
      }

      $description = $request->getHeaderLine('uploadDescription');
      $public = $request->getHeaderLine('public');
      $public = empty($public) ? 'protected' : $public;
      $applyGlobal = filter_var($request->getHeaderLine('applyGlobal'),
        FILTER_VALIDATE_BOOLEAN);
      $ignoreScm = $request->getHeaderLine('ignoreScm');

      $locationObject = [];
      if (array_key_exists("location", $reqBody)) {
        $locationObject = $reqBody["location"];
      } elseif ($request->getHeaderLine('uploadType') != 'file') {
        $error = new Info(400, "Require location object if uploadType != file",
          InfoType::ERROR);
        return $response->withJson($error->getArray(), $error->getCode());
      }

      $uploadResponse = $uploadHelper->createNewUpload($locationObject,
        $folderId, $description, $public, $ignoreScm, $uploadType,
        $applyGlobal);
      $status = $uploadResponse[0];
      $message = $uploadResponse[1];
      $statusDescription = $uploadResponse[2];
      if (! $status) {
        $info = new Info(500, $message . "\n" . $statusDescription,
          InfoType::ERROR);
      } elseif (! empty($scanOptions)) {
        $uploadId = $uploadResponse[3];
        $info =  $uploadHelper->handleScheduleAnalysis(intval($uploadId),
          intval($folderId), $scanOptions, true);
        if ($info->getCode() == 201) {
          $info = new Info($info->getCode(), intval($uploadId), $info->getType());
        }
      } else {
        $uploadId = $uploadResponse[3];
        $info = new Info(201, intval($uploadId), InfoType::INFO);
      }
      return $response->withJson($info->getArray(), $info->getCode());
    } else {
      $error = new Info(400, "folderId must be a positive integer!",
        InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }
  }

  /**
   * Get list of licenses and copyright for given upload
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getUploadLicenses($request, $response, $args)
  {
    $id = intval($args['id']);
    $query = $request->getQueryParams();
    $page = $request->getHeaderLine("page");
    $limit = $request->getHeaderLine("limit");

    if (! array_key_exists(self::AGENT_PARAM, $query)) {
      $error = new Info(400, "'agent' parameter missing from query.",
        InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }
    $agents = explode(",", $query[self::AGENT_PARAM]);
    $containers = true;
    if (array_key_exists(self::CONTAINER_PARAM, $query)) {
      $containers = (strcasecmp($query[self::CONTAINER_PARAM], "true") === 0);
    }

    $license = true;
    if (array_key_exists('license', $query)) {
      $license = (strcasecmp($query['license'], "true") === 0);
    }

    $copyright = false;
    if (array_key_exists('copyright', $query)) {
      $copyright = (strcasecmp($query['copyright'], "true") === 0);
    }

    if (!$license && !$copyright) {
      $error = new Info(400, "'license' and 'copyright' atleast one should be true.",
        InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }

    $upload = $this->uploadAccessible($this->restHelper->getGroupId(), $id);
    if ($upload !== true) {
      return $response->withJson($upload->getArray(), $upload->getCode());
    }
    $adj2nest = $this->isAdj2nestDone($id, $response);
    $agentScheduled = $this->areAgentsScheduled($id, $agents, $response);
    if ($adj2nest !== true) {
      return $adj2nest;
    } else if ($agentScheduled !== true) {
      return $agentScheduled;
    }

    /*
     * check if page && limit are numeric, if existing
     */
    if ((! ($page==='') && (! is_numeric($page) || $page < 1)) ||
      (! ($limit==='') && (! is_numeric($limit) || $limit < 1))) {
      $returnVal = new Info(400,
        "Bad Request. page and limit need to be positive integers!",
        InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }

    // set page to 1 by default
    if (empty($page)) {
      $page = 1;
    }

    // set limit to 50 by default and max as 1000
    if (empty($limit)) {
      $limit = 50;
    } else if ($limit > 1000) {
      $limit = 1000;
    }

    $uploadHelper = new UploadHelper();
    list($licenseList, $count) = $uploadHelper->getUploadLicenseList($id, $agents, $containers, $license, $copyright, $page-1, $limit);
    $totalPages = intval(ceil($count / $limit));
    return $response->withHeader("X-Total-Pages", $totalPages)->withJson($licenseList, 200);
  }

   /**
   * Get list of copyright and files for given upload
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */

  public function getUploadCopyrights($request, $response, $args)
  {
    $id = intval($args['id']);
    $upload = $this->uploadAccessible($this->restHelper->getGroupId(), $id);
    if ($upload !== true) {
      return $response->withJson($upload->getArray(), $upload->getCode());
    }
    $adj2nest = $this->isAdj2nestDone($id, $response);
    if ($adj2nest !== true) {
      return $adj2nest;
    }
    $uploadHelper = new UploadHelper();
    $licenseList = $uploadHelper->getUploadCopyrightList($id);
    return $response->withJson($licenseList, 200);
  }

  /**
   * Update an upload
   *
   * @param ServerRequestInterface $request
   * @param ResponseInterface $response
   * @param array $args
   * @return ResponseInterface
   */
  public function updateUpload($request, $response, $args)
  {
    $id = intval($args['id']);
    $query = $request->getQueryParams();
    $userDao = $this->restHelper->getUserDao();
    $userId = $this->restHelper->getUserId();
    $groupId = $this->restHelper->getGroupId();

    $perm = $userDao->isAdvisorOrAdmin($userId, $groupId);
    if (!$perm) {
      $error = new Info(403, "Not advisor or admin of current group. " .
        "Can not update upload.", InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }
    $uploadBrowseProxy = new UploadBrowseProxy(
      $groupId,
      $perm,
      $this->dbHelper->getDbManager()
    );

    $assignee = null;
    $status = null;
    $comment = null;

    $returnVal = true;
    // Handle assignee info
    if (array_key_exists(self::FILTER_ASSIGNEE, $query)) {
      $assignee = filter_var($query[self::FILTER_ASSIGNEE], FILTER_VALIDATE_INT);
      $userList = $userDao->getUserChoices($groupId);
      if (!array_key_exists($assignee, $userList)) {
        $returnVal = new Info(
          404,
          "New assignee does not have permisison on upload.",
          InfoType::ERROR
        );
      } else {
        $uploadBrowseProxy->updateTable("assignee", $id, $assignee);
      }
    }
    // Handle new status
    if (
      array_key_exists(self::FILTER_STATUS, $query) &&
      in_array(strtolower($query[self::FILTER_STATUS]), self::VALID_STATUS) &&
      $returnVal === true
    ) {
      $newStatus = strtolower($query[self::FILTER_STATUS]);
      $comment = '';
      if (in_array($newStatus, ["closed", "rejected"])) {
        $body = $request->getBody();
        $comment = $body->getContents();
        $body->close();
      }
      $status = 0;
      if ($newStatus == self::VALID_STATUS[1]) {
        $status = UploadStatus::IN_PROGRESS;
      } elseif ($newStatus == self::VALID_STATUS[2]) {
        $status = UploadStatus::CLOSED;
      } elseif ($newStatus == self::VALID_STATUS[3]) {
        $status = UploadStatus::REJECTED;
      } else {
        $status = UploadStatus::OPEN;
      }
      $uploadBrowseProxy->setStatusAndComment($id, $status, $comment);
    }
    if ($returnVal !== true) {
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }

    $returnVal = new Info(202, "Upload updated successfully.", InfoType::INFO);
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }

  /**
   * Check if upload is accessible
   * @param integer $groupId Group ID
   * @param integer $id Upload ID
   * @return Fossology::UI::Api::Models::Info|boolean Info object on failure or
   *         true otherwise
   */
  private function uploadAccessible($groupId, $id)
  {
    if (! $this->dbHelper->doesIdExist("upload", "upload_pk", $id)) {
      return new Info(404, "Upload does not exist", InfoType::ERROR);
    } else if (! $this->restHelper->getUploadDao()->isAccessible($id, $groupId)) {
      return new Info(403, "Upload is not accessible", InfoType::ERROR);
    }
    return true;
  }

  /**
   * Check if adj2nest agent finished on upload
   * @param integer $id Upload ID
   * @param ResponseHelper $response
   * @return ResponseHelper|boolean Response if failure, true otherwise
   */
  private function isAdj2nestDone($id, $response)
  {
    $itemTreeBounds = $this->restHelper->getUploadDao()->getParentItemBounds(
      $id);
    if ($itemTreeBounds === false || empty($itemTreeBounds->getLeft())) {
      $returnVal = new Info(503,
        "Ununpack job not started. Please check job status at " .
        "/api/v1/jobs?upload=" . $id, InfoType::INFO);
      return $response->withHeader('Retry-After', '60')
        ->withHeader('Look-at', "/api/v1/jobs?upload=" . $id)
        ->withJson($returnVal->getArray(), $returnVal->getCode());
    }
    return true;
  }

  /**
   * Check if every agent passed is scheduled for the upload
   * @param integer $uploadId Upload ID to check agents for
   * @param array $agents     List of agents to check
   * @param ResponseHelper $response
   * @return ResponseHelper|boolean Error response on failure, true on
   * success
   */
  private function areAgentsScheduled($uploadId, $agents, $response)
  {
    global $container;
    $agentDao = $container->get('dao.agent');

    $agentList = array_keys(AgentRef::AGENT_LIST);
    $intersectArray = array_intersect($agents, $agentList);

    $error = null;
    if (count($agents) != count($intersectArray)) {
      $error = new Info(400, "Agent should be any of " .
        implode(", ", $agentList) . ". " . implode(",", $agents) . " passed.",
        InfoType::ERROR);
    } else {
      // Agent is valid, check if they have ars tables.
      foreach ($agents as $agent) {
        if (! $agentDao->arsTableExists($agent)) {
          $error = new Info(412, "Agent $agent not scheduled for the upload. " .
            "Please POST to /jobs", InfoType::ERROR);
          break;
        }
      }
    }
    if ($error !== null) {
      return $response->withJson($error->getArray(), $error->getCode());
    }

    $scanProx = new ScanJobProxy($agentDao, $uploadId);
    $agentList = $scanProx->createAgentStatus($agents);

    foreach ($agentList as $agent) {
      if (! array_key_exists('currentAgentId', $agent)) {
        $error = new Info(412, "Agent " . $agent["agentName"] .
          " not scheduled for the upload. Please POST to /jobs",
          InfoType::ERROR);
        $response = $response->withJson($error->getArray(), $error->getCode());
      } else if (array_key_exists('isAgentRunning', $agent) &&
        $agent['isAgentRunning']) {
        $error = new Info(503, "Agent " . $agent["agentName"] . " is running. " .
          "Please check job status at /api/v1/jobs?upload=" . $uploadId,
          InfoType::INFO);
        $response = $response->withHeader('Retry-After', '60')
        ->withHeader('Look-at', "/api/v1/jobs?upload=" . $uploadId)
        ->withJson($error->getArray(), $error->getCode());
      }
      if ($error !== null) {
        return $response;
      }
    }
    return true;
  }

  /**
   * Set permissions for a upload in a folder for different groups
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function setUploadPermissions($request, $response, $args)
  {
    $returnVal = null;
    // checking if the scheduler is running or not
    $commu_status = fo_communicate_with_scheduler('status', $response_from_scheduler, $error_info);
    if ($commu_status) {
      // Initialising upload-permissions plugin
      global $container;
      $restHelper = $container->get('helper.restHelper');
      $uploadPermissionObj = $restHelper->getPlugin('upload_permissions');

      $dbManager = $this->dbHelper->getDbManager();
      // parsing the request body
      $reqBody = $this->getParsedBody($request);

      $folder_pk = intval($reqBody['folderId']);
      $upload_pk = intval($args['id']);
      $upload = $this->uploadAccessible($this->restHelper->getGroupId(), $upload_pk);
      if ($upload !== true) {
        return $response->withJson($upload->getArray(), $upload->getCode());
      }
      $allUploadsPerm = $reqBody['allUploadsPermission'] ? 1 : 0;
      $newgroup = intval($reqBody['groupId']);
      $newperm = intval($this->getEquivalentValueForPermission($reqBody['newPermission']));
      $public_perm = isset($reqBody['publicPermission']) ? $this->getEquivalentValueForPermission($reqBody['publicPermission']) : -1;

      $query = "SELECT perm, perm_upload_pk FROM perm_upload WHERE upload_fk=$1 and group_fk=$2;";
      $result = $dbManager->getSingleRow($query, [$upload_pk, $newgroup], __METHOD__.".getOldPerm");
      $perm_upload_pk = 0;
      $perm = 0;
      if (!empty($result)) {
        $perm_upload_pk = intVal($result['perm_upload_pk']);
        $perm = $newperm;
      }

      $uploadPermissionObj->editPermissionsForUpload($commu_status, $folder_pk, $upload_pk, $allUploadsPerm, $perm_upload_pk, $perm, $newgroup, $newperm, $public_perm);

      $returnVal = new Info(202, "Permissions updated successfully!", InfoType::INFO);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    } else {
      $returnVal = new Info(503, "Scheduler is not running!", InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }
  }

  public function getEquivalentValueForPermission($perm)
  {
    switch ($perm) {
      case 'none':
        return Auth::PERM_NONE;
      case 'read_only':
        return Auth::PERM_READ;
      case 'read_write':
        return Auth::PERM_WRITE;
      case 'clearing_admin':
        return Auth::PERM_CADMIN;
      case 'admin':
        return Auth::PERM_ADMIN;
      default:
        return Auth::PERM_NONE;
    }
  }

  /**
   * Get all the groups with their respective permissions for a upload
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getGroupsWithPermissions($request, $response, $args)
  {
    $upload_pk = intval($args['id']);
    $upload = $this->uploadAccessible($this->restHelper->getGroupId(), $upload_pk);
    if ($upload !== true) {
      return $response->withJson($upload->getArray(), $upload->getCode());
    }
    $publicPerm = $this->restHelper->getUploadPermissionDao()->getPublicPermission($upload_pk);
    $permGroups = $this->restHelper->getUploadPermissionDao()->getPermissionGroups($upload_pk);

    // Removing the perm_upload_pk parameter in response
    $finalPermGroups = array();
    foreach ($permGroups as $value) {
      unset($value["perm_upload_pk"]);
      array_push($finalPermGroups, $value);
    }

    $res = array();
    $res["publicPerm"] = $publicPerm;
    $res["permGroups"] = $finalPermGroups;
    return $response->withJson($res, 200);
  }
  /**
   * Get the main licenses for the upload
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getMainLicenses($request, $response, $args)
  {
    $uploadId = intval($args['id']);
    if (!$this->dbHelper->doesIdExist("upload", "upload_pk", $uploadId)) {
      $returnVal = new Info(404, "Upload does not exist", InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }

    $clearingDao = $this->container->get('dao.clearing');
    $licenseIds = $clearingDao->getMainLicenseIds($uploadId, $this->restHelper->getGroupId());
    $licenseDao = $this->container->get('dao.license');
    $licenses = array();

    foreach ($licenseIds as $key => $value) {
      $licenseId = intval($value);
      $obligations = $licenseDao->getLicenseObligations([$licenseId],
        false);
      $obligations = array_merge($obligations,
        $licenseDao->getLicenseObligations([$licenseId], true));
      $obligationList = [];
      foreach ($obligations as $obligation) {
        $obligationList[] = new Obligation(
          $obligation['ob_pk'],
          $obligation['ob_topic'],
          $obligation['ob_type'],
          $obligation['ob_text'],
          $obligation['ob_classification'],
          $obligation['ob_comment']
        );
      }
      $license = $licenseDao->getLicenseById($licenseId);
      $licenseObj = new License(
        $license->getId(),
        $license->getShortName(),
        $license->getFullName(),
        $license->getText(),
        $license->getUrl(),
        $obligationList,
        $license->getRisk()
      );
      $licenses[] = $licenseObj->getArray();
    }
    return $response->withJson($licenses, 200);
  }

  /**
   * Set the main license for the upload
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function setMainLicense($request, $response, $args)
  {
    $uploadId = intval($args['id']);
    $body = $this->getParsedBody($request);
    $shortName = $body['shortName'];
    $returnVal = null;
    $license = null;
    $licenseDao = $this->container->get('dao.license');
    $clearingDao = $this->container->get('dao.clearing');

    if (!$this->dbHelper->doesIdExist("upload", "upload_pk", $uploadId)) {
      $returnVal = new Info(404, "Upload does not exist", InfoType::ERROR);
    } else if (empty($shortName)) {
      $returnVal = new Info(400, "Short name missing from request.",
        InfoType::ERROR);
    } else {
      $license = $licenseDao->getLicenseByShortName($shortName,
        $this->restHelper->getGroupId());

      if ($license === null) {
        $returnVal = new Info(404, "No license with shortname '$shortName' found.",
          InfoType::ERROR);
      } else {
        $licenseIds = $clearingDao->getMainLicenseIds($uploadId, $this->restHelper->getGroupId());
        if (in_array($license->getId(), $licenseIds)) {
          $returnVal = new Info(400, "License already exists for this upload.",
            InfoType::ERROR);
        }
      }
    }
    if ($returnVal !== null) {
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }
    $clearingDao = $this->container->get('dao.clearing');
    $clearingDao->makeMainLicense($uploadId, $this->restHelper->getGroupId(), $license->getId());
    $returnVal = new Info(200, "Successfully added new main license", InfoType::INFO);
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }

  /***
   * Remove the main license from the upload
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function removeMainLicense($request, $response, $args)
  {
    $uploadId = intval($args['id']);
    $shortName = $args['shortName'];
    $returnVal = null;
    $licenseDao = $this->container->get('dao.license');
    $clearingDao = $this->container->get('dao.clearing');
    $license = $licenseDao->getLicenseByShortName($shortName, $this->restHelper->getGroupId());

    if (!$this->dbHelper->doesIdExist("upload", "upload_pk", $uploadId)) {
      $returnVal = new Info(404, "Upload does not exist", InfoType::ERROR);
    } else if ($license === null) {
      $returnVal = new Info(404, "No license with shortname '$shortName' found.",
        InfoType::ERROR);
    } else {
      $licenseIds = $clearingDao->getMainLicenseIds($uploadId, $this->restHelper->getGroupId());
      if (!in_array($license->getId(), $licenseIds)) {
        $returnVal = new Info(400, "License '$shortName' is not a main license for this upload.",
          InfoType::ERROR);
      }
    }
    if ($returnVal !== null) {
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }
    $clearingDao = $this->container->get('dao.clearing');
    $clearingDao->removeMainLicense($uploadId, $this->restHelper->getGroupId(), $license->getId());
    $returnVal = new Info(200, "Main license removed successfully.", InfoType::INFO);

    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }

  /**
   * Get the clearing progress info of the upload
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getClearingProgressInfo($request, $response, $args)
  {
    $uploadId = intval($args['id']);
    $uploadDao = $this->restHelper->getUploadDao();

    if (!$this->dbHelper->doesIdExist("upload", "upload_pk", $uploadId)) {
      $returnVal = new Info(404, "Upload does not exist", InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }

    $uploadTreeTableName = $uploadDao->getUploadtreeTableName($uploadId);

    $noLicenseUploadTreeView = new UploadTreeProxy($uploadId,
      array(UploadTreeProxy::OPT_SKIP_THESE => "noLicense",
        UploadTreeProxy::OPT_GROUP_ID => $this->restHelper->getGroupId()),
      $uploadTreeTableName,
      'no_license_uploadtree' . $uploadId);

    $filesOfInterest = $noLicenseUploadTreeView->count();

    $nonClearedUploadTreeView = new UploadTreeProxy($uploadId,
      array(UploadTreeProxy::OPT_SKIP_THESE => "alreadyCleared",
        UploadTreeProxy::OPT_GROUP_ID =>  $this->restHelper->getGroupId()),
      $uploadTreeTableName,
      'already_cleared_uploadtree' . $uploadId);
    $filesToBeCleared = $nonClearedUploadTreeView->count();

    $filesAlreadyCleared = $filesOfInterest - $filesToBeCleared;

    $res = [
      "totalFilesOfInterest" => intval($filesOfInterest),
      "totalFilesCleared" => intval($filesAlreadyCleared),
    ];
    return $response->withJson($res, 200);
  }

  /**
   * Get all licenses histogram for the entire upload
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getLicensesHistogram($request, $response, $args)
  {
    $agentDao = $this->container->get('dao.agent');
    $clearingDao = $this->container->get('dao.clearing');
    $licenseDao = $this->container->get('dao.license');

    $uploadId = intval($args['id']);
    $uploadDao = $this->restHelper->getUploadDao();
    $query = $request->getQueryParams();
    $selectedAgentId = $query['agentId'];

    if (!$this->dbHelper->doesIdExist("upload", "upload_pk", $uploadId)) {
      $returnVal = new Info(404, "Upload does not exist.", InfoType::ERROR);
    } else if ($selectedAgentId !== null && !$this->dbHelper->doesIdExist("agent", "agent_pk", $selectedAgentId)) {
      $returnVal = new Info(404, "Agent does not exist.", InfoType::ERROR);
    }
    if (isset($returnVal)) {
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }

    $scannerAgents = array_keys($this->agentNames);
    $scanJobProxy = new ScanJobProxy($agentDao, $uploadId);
    $scanJobProxy->createAgentStatus($scannerAgents);
    $uploadTreeTableName = $uploadDao->getUploadtreeTableName($uploadId);
    $itemTreeBounds = $uploadDao->getParentItemBounds($uploadId, $uploadTreeTableName);
    $editedLicenses = $clearingDao->getClearedLicenseIdAndMultiplicities($itemTreeBounds, $this->restHelper->getGroupId());
    $selectedAgentIds = empty($selectedAgentId) ? $scanJobProxy->getLatestSuccessfulAgentIds() : $selectedAgentId;
    $scannedLicenses = $licenseDao->getLicenseHistogram($itemTreeBounds, $selectedAgentIds);
    $allScannerLicenseNames = array_keys($scannedLicenses);
    $allEditedLicenseNames = array_keys($editedLicenses);
    $allLicNames = array_unique(array_merge($allScannerLicenseNames, $allEditedLicenseNames));
    $realLicNames = array_diff($allLicNames, array(LicenseDao::NO_LICENSE_FOUND));
    $totalScannerLicenseCount = 0;
    $editedTotalLicenseCount = 0;

    $res = array();
    foreach ($realLicNames as $licenseShortName) {
      $count = 0;
      if (array_key_exists($licenseShortName, $scannedLicenses)) {
        $count = $scannedLicenses[$licenseShortName]['unique'];
        $rfId = $scannedLicenses[$licenseShortName]['rf_pk'];
      } else {
        $rfId = $editedLicenses[$licenseShortName]['rf_pk'];
      }
      $editedCount = array_key_exists($licenseShortName, $editedLicenses) ? $editedLicenses[$licenseShortName]['count'] : 0;
      $totalScannerLicenseCount += $count;
      $editedTotalLicenseCount += $editedCount;
      $scannerCountLink = $count;
      $editedLink = $editedCount;

      $res[] = array($scannerCountLink, $editedLink, array($licenseShortName, $rfId));
    }

    $outputArray = [];

    foreach ($res as $item) {
      $outputArray[] = [
        "id" => intval($item[2][1]),
        "name" => $item[2][0],
        "scannerCount" => intval($item[0]),
        "concludedCount" => intval($item[1]),
      ];
    }
    return $response->withJson($outputArray, 200);
  }
  /**
   * Get all the groups with their respective permissions for a upload
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getAllAgents($request, $response, $args)
  {
    $uploadId = intval($args['id']);

    if (!$this->dbHelper->doesIdExist("upload", "upload_pk", $uploadId)) {
      $returnVal = new Info(404, "Upload does not exist", InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }

    $scannerAgents = array_keys($this->agentNames);
    $agentDao = $this->container->get('dao.agent');
    $scanJobProxy = new ScanJobProxy($agentDao, $uploadId);
    $res = $scanJobProxy->createAgentStatus($scannerAgents);

    foreach ($res as &$item) {
      if (count($item['successfulAgents']) > 0) {
        $item['isAgentRunning'] = false;
      } else {
        $item['currentAgentId'] = $agentDao->getCurrentAgentRef($item["agentName"])->getAgentId();
        $item['currentAgentRev'] = "";
      }
      foreach ($item['successfulAgents'] as &$agent) {
        $agent['agent_id'] = intval($agent['agent_id']);
      }
    }
    return $response->withJson($res, 200);
  }

  /**
   * Get all edited licenses for the entire upload
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getEditedLicenses($request, $response, $args)
  {
    $clearingDao = $this->container->get('dao.clearing');
    $licenseDao = $this->container->get('dao.license');

    $uploadId = intval($args['id']);
    if (!$this->dbHelper->doesIdExist("upload", "upload_pk", $uploadId)) {
      $returnVal = new Info(404, "Upload does not exist", InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }
    $uploadDao = $this->restHelper->getUploadDao();
    $uploadTreeTableName = $uploadDao->getUploadtreeTableName($uploadId);
    $itemTreeBounds = $uploadDao->getParentItemBounds($uploadId, $uploadTreeTableName);
    $res = $clearingDao->getClearedLicenseIdAndMultiplicities($itemTreeBounds, $this->restHelper->getGroupId());
    $outputArray = [];

    foreach ($res as $key => $value) {
      $outputArray[] = [
        "id" => intval($value["rf_pk"]),
        "shortName" => $key,
        "count" =>intval($value["count"]),
        "spdx_id" => $value["spdx_id"],
      ];
    }
    return $response->withJson($outputArray, 200);
  }

  /**
   * Get Reuse report summary for the upload
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getReuseReportSummary($request, $response, $args)
  {
    $uploadId = intval($args['id']);

    if (!$this->dbHelper->doesIdExist("upload", "upload_pk", $uploadId)) {
      $returnVal = new Info(404, "Upload does not exist", InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }

    $reuseReportProcess = $this->container->get('businessrules.reusereportprocessor');
    $res = $reuseReportProcess->getReuseSummary($uploadId);
    return $response->withJson($res, 200);
  }

  /**
   * Get all scanned licenses for the entire upload
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getScannedLicenses($request, $response, $args)
  {
    $uploadId = intval($args['id']);
    $query = $request->getQueryParams();
    $selectedAgentId = $query['agentId'];
    $licenseDao = $this->container->get('dao.license');

    if (!$this->dbHelper->doesIdExist("upload", "upload_pk", $uploadId)) {
      $returnVal = new Info(404, "Upload does not exist", InfoType::ERROR);
    } else if ($selectedAgentId !== null && !$this->dbHelper->doesIdExist("agent", "agent_pk", $selectedAgentId)) {
      $returnVal = new Info(404, "Agent does not exist", InfoType::ERROR);
    }
    if (isset($returnVal)) {
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }
    $scannerAgents = array_keys(AgentRef::AGENT_LIST);
    $scanJobProxy = new ScanJobProxy($this->agentDao, $uploadId);
    $scanJobProxy->createAgentStatus($scannerAgents);
    $uploadDao = $this->restHelper->getUploadDao();
    $uploadTreeTableName = $uploadDao->getUploadtreeTableName($uploadId);
    $itemTreeBounds = $uploadDao->getParentItemBounds($uploadId, $uploadTreeTableName);
    $selectedAgentIds = empty($selectedAgentId) ? $scanJobProxy->getLatestSuccessfulAgentIds() : $selectedAgentId;
    $res = $licenseDao->getLicenseHistogram($itemTreeBounds, $selectedAgentIds);
    $outputArray = [];

    foreach ($res as $key => $value) {
      $scannedLicense = new ScannedLicense($licenseDao->getLicenseByShortName($key)->getId(), $key, $value['count'], $value['unique'], $value['spdx_id']);
      $outputArray[] = $scannedLicense->getArray();
    }
    return $response->withJson($outputArray, 200);
  }
  /**
   * Get all the revisions for the successful agents of an upload
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getAgentsRevision($request, $response, $args)
  {
    $agentDao = $this->container->get('dao.agent');
    $uploadId = intval($args['id']);

    if (!$this->dbHelper->doesIdExist("upload", "upload_pk", $uploadId)) {
      $returnVal = new Info(404, "Upload does not exist", InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }
    $scannerAgents = array_keys($this->agentNames);
    $scanJobProxy = new ScanJobProxy($agentDao, $uploadId);
    $scanJobProxy->createAgentStatus($scannerAgents);

    $res = array();
    foreach ($scanJobProxy->getSuccessfulAgents() as $agent) {
      $res[] = array(
        "id" => $agent->getAgentId(),
        "name" => $agent->getAgentName(),
        "revision" => $agent->getAgentRevision(),
      );
    }
    return $response->withJson($res, 200);
  }

  /**
   * Get the top level item ID for an upload.
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws Exception
   */
  public function getTopItem($request, $response, $args)
  {
    $uploadId = intval($args['id']);
    if (!$this->dbHelper->doesIdExist("upload", "upload_pk", $uploadId)) {
      $returnVal = new Info(404, "Upload does not exist", InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }
    $uploadDao = $this->restHelper->getUploadDao();
    $itemTreeBounds = $uploadDao->getParentItemBounds($uploadId,
      $uploadDao->getUploadtreeTableName($uploadId));
    if ($itemTreeBounds === false) {
      $error = new Info(500, "Unable to get top item.", InfoType::ERROR);
      return $response->withJson($error->getArray(), $error->getCode());
    }
    $info = new Info(200, $itemTreeBounds->getItemId(), InfoType::INFO);
    return $response->withJson($info->getArray(), $info->getCode());
  }
}
