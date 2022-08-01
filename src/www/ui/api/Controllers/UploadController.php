<?php
/*
 SPDX-FileCopyrightText: © 2018, 2020 Siemens AG
 Author: Gaurav Mishra <mishra.gaurav@siemens.com>

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * @file
 * @brief Controller for upload queries
 */

namespace Fossology\UI\Api\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use Fossology\DelAgent\UI\DeleteMessages;
use Fossology\UI\Page\UploadPageBase;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Helper\UploadHelper;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Dao\AgentDao;
use Fossology\Lib\Data\UploadStatus;
use Fossology\Lib\Proxy\ScanJobProxy;
use Fossology\Lib\Proxy\UploadBrowseProxy;
use Fossology\UI\Api\Helper\ResponseHelper;

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

  public function __construct($container)
  {
    parent::__construct($container);
    $groupId = $this->restHelper->getGroupId();
    $dbManager = $this->dbHelper->getDbManager();
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
    $upload = $this->uploadAccessible($this->restHelper->getGroupId(), $id);
    if ($upload !== true) {
      return $response->withJson($upload->getArray(), $upload->getCode());
    }
    $temp = $this->isAdj2nestDone($id, $response);
    if ($temp !== true) {
      return $temp;
    }
    $uploadHelper = new UploadHelper();
    $uploadSummary = $uploadHelper->generateUploadSummary($id,
      $this->restHelper->getGroupId());
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
    $uploadHelper = new UploadHelper();
    if ($request->hasHeader('folderId') &&
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
      $uploadType = $request->getHeaderLine('uploadType');
      if (empty($uploadType)) {
        $uploadType = "vcs";
      }
      $reqBody = $this->getParsedBody($request);
      $uploadResponse = $uploadHelper->createNewUpload($reqBody, $folderId,
        $description, $public, $ignoreScm, $uploadType, $applyGlobal);
      $status = $uploadResponse[0];
      $message = $uploadResponse[1];
      $statusDescription = $uploadResponse[2];
      if (! $status) {
        $info = new Info(500, $message . "\n" . $statusDescription,
          InfoType::ERROR);
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

    $uploadHelper = new UploadHelper();
    $licenseList = $uploadHelper->getUploadLicenseList($id, $agents, $containers, $license, $copyright);
    return $response->withJson($licenseList, 200);
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
   * @param integer $id      Upload ID
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
}
