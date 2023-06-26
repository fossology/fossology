<?php
/*
 SPDX-FileCopyrightText: © 2023 Samuel Dushimimana <dushsam100@gmail.com>

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Controller for uploadtree queries
 */

namespace Fossology\UI\Api\Controllers;

use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\BulkHistory;
use Fossology\UI\Api\Models\ClearingHistory;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Psr\Http\Message\ServerRequestInterface;


/**
 * @class UploadTreeController
 * @brief Controller for UploadTree model
 */
class UploadTreeController extends RestController
{

  /**
   * @var DecisionTypes $decisionTypes
   * Decision types object
   */
  private $decisionTypes;


  public function __construct($container)
  {
    parent::__construct($container);
    $this->decisionTypes = $this->container->get('decision.types');
  }

  /**
   * Get the contents of a specific file
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function viewLicenseFile($request, $response, $args)
  {
    $uploadId = intval($args['id']);
    $itemId = intval($args['itemId']);

    $uploadDao = $this->restHelper->getUploadDao();
    $returnVal = null;

    if (!$this->dbHelper->doesIdExist("upload", "upload_pk", $uploadId)) {
      $returnVal = new Info(404, "Upload does not exist", InfoType::ERROR);
    } else if (!$this->dbHelper->doesIdExist($uploadDao->getUploadtreeTableName($uploadId), "uploadtree_pk", $itemId)) {
      $returnVal = new Info(404, "Item does not exist", InfoType::ERROR);
    }

    if ($returnVal !== null) {
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }

    $view = $this->restHelper->getPlugin('view');

    $inputFile = @fopen(RepPathItem($itemId), "rb");
    if (empty($inputFile)) {
      global $Plugins;
      $reunpackPlugin = &$Plugins[plugin_find_id("ui_reunpack")];
      $state = $reunpackPlugin->CheckStatus($uploadId, "reunpack", "ununpack");
      if ($state != 0 && $state != 2) {
        $errorMess = _("Reunpack job is running: you can see it in");
      } else {
        $errorMess = _("File contents are not available in the repository.");
      }
      $info = new Info(500, $errorMess, InfoType::ERROR);
      return $response->withJson($info->getArray(), $info->getCode());
    }
    rewind($inputFile);

    $res = $view->getText($inputFile, 0, 0, -1, null, false, true);
    $response->getBody()->write($res);
    return $response->withHeader("Content-Type", "text/plain")
      ->withHeader("Cache-Control", "max-age=1296000, must-revalidate")
      ->withHeader("Etag", md5($response->getBody()));
  }

  /**
   * Set the clearing decision for a particular upload-tree
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function setClearingDecision($request, $response, $args)
  {
    $body = $this->getParsedBody($request);
    $decisionType = $body['decisionType'];
    $global = $body['globalDecision'];

    // check if the given globalDecision value is a boolean
    if ($global !== null && !is_bool($global)) {
      $returnVal = new Info(400, "GlobalDecision should be a boolean", InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }

    $uploadTreeId = intval($args['itemId']);

    $returnVal = null;
    $uploadDao = $this->restHelper->getUploadDao();

    // check if the given key exists in the known decision types
    if (!array_key_exists($decisionType, $this->decisionTypes->getMap())) {
      $returnVal = new Info(400, "Decision Type should be one of the following keys: " . implode(", ", array_keys($this->decisionTypes->getMap())), InfoType::ERROR);
    } else if (!$this->dbHelper->doesIdExist($uploadDao->getUploadtreeTableName($uploadTreeId), "uploadtree_pk", $uploadTreeId)) {
      $returnVal = new Info(404, "Item does not exist", InfoType::ERROR);
    }

    if ($returnVal !== null) {
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }

    try {
      $viewLicensePlugin = $this->restHelper->getPlugin('view-license');
      $_GET['clearingTypes'] = $decisionType;
      $_GET['globalDecision'] = $global ? 1 : 0;
      $viewLicensePlugin->updateLastItem($this->restHelper->getUserId(), $this->restHelper->getGroupId(), $uploadTreeId, $uploadTreeId);
      $returnVal = new Info(200, "Successfully set decision", InfoType::INFO);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    } catch (\Exception $e) {
      $returnVal = new Info(500, $e->getMessage(), InfoType::ERROR);
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }
  }
  /**
   * Get the next and previous item for a given upload and itemId
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getNextPreviousItem($request, $response, $args)
  {
    $uploadTreeId = intval($args['itemId']);
    $uploadId = intval($args['id']);
    $query = $request->getQueryParams();
    $uploadDao = $this->restHelper->getUploadDao();
    $returnVal = null;
    $selection = "";

    if (!$this->dbHelper->doesIdExist("upload", "upload_pk", $uploadId)) {
      $returnVal = new Info(404, "Upload does not exist", InfoType::ERROR);
    } else if (!$this->dbHelper->doesIdExist($uploadDao->getUploadtreeTableName($uploadId), "uploadtree_pk", $uploadTreeId)) {
      $returnVal = new Info(404, "Item does not exist", InfoType::ERROR);
    } else if ($query['selection'] !== null) {
      $selection = $query['selection'];
      if ($selection != "withLicenses" && $selection != "noClearing") {
        $returnVal = new Info(400, "selection should be either 'withLicenses' or 'noClearing'", InfoType::ERROR);
      }
    }

    if ($returnVal != null) {
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }

    $options = array('skipThese' => $selection == "withLicenses" ? "noLicense" : ($selection == "noClearing" ? "alreadyCleared" : ""), 'groupId' => $this->restHelper->getGroupId());

    $prevItem = $uploadDao->getPreviousItem($uploadId, $uploadTreeId, $options);
    $prevItemId = $prevItem ? $prevItem->getId() : null;

    $nextItem = $uploadDao->getNextItem($uploadId, $uploadTreeId, $options);
    $nextItemId = $nextItem ? $nextItem->getId() : null;

    $res = [
      "prevItemId" => $prevItemId,
      "nextItemId" => $nextItemId
    ];
    return $response->withJson($res, 200);
  }

  /**
   * Get the bulk history of an item
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getBulkHistory($request, $response, $args)
  {
    $uploadTreeId = intval($args['itemId']);
    $uploadId = intval($args['id']);
    $uploadDao = $this->restHelper->getUploadDao();
    $clearingDao = $this->container->get('dao.clearing');
    $returnVal = null;

    if (!$this->dbHelper->doesIdExist("upload", "upload_pk", $uploadId)) {
      $returnVal = new Info(404, "Upload does not exist", InfoType::ERROR);
    } else if (!$this->dbHelper->doesIdExist($uploadDao->getUploadtreeTableName($uploadId), "uploadtree_pk", $uploadTreeId)) {
      $returnVal = new Info(404, "Item does not exist", InfoType::ERROR);
    }

    if ($returnVal != null) {
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }
    $uploadTreeTableName = $uploadDao->getUploadtreeTableName($uploadId);
    $itemTreeBounds = $uploadDao->getItemTreeBounds($uploadTreeId, $uploadTreeTableName);

    $res = $clearingDao->getBulkHistory($itemTreeBounds, $this->restHelper->getGroupId());
    $updatedRes = [];

    foreach ($res as $value) {
      $obj = new BulkHistory(
        intval($value['bulkId']),
        intval($value['id']),
        $value['text'],
        $value['matched'],
        $value['tried'],
        $value['addedLicenses'],
        $value['removedLicenses']);
      $updatedRes[] = $obj->getArray();
    }
    return $response->withJson($updatedRes, 200);
  }

  /**
   * Get the clearing history for a particular upload-tree
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getClearingHistory($request, $response, $args)
  {
    $itemId = intval($args['itemId']);
    $uploadId = intval($args['id']);
    $uploadDao = $this->restHelper->getUploadDao();
    $clearingDao = $this->container->get('dao.clearing');

    $returnVal = null;

    if (!$this->dbHelper->doesIdExist("upload", "upload_pk", $uploadId)) {
      $returnVal = new Info(404, "Upload does not exist", InfoType::ERROR);
    } else if (!$this->dbHelper->doesIdExist($uploadDao->getUploadtreeTableName($uploadId), "uploadtree_pk", $itemId)) {
      $returnVal = new Info(404, "Item does not exist", InfoType::ERROR);
    }

    if ($returnVal !== null) {
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }

    $itemTreeBounds = $uploadDao->getItemTreeBoundsFromUploadId($itemId, $uploadId);
    $clearingDecWithLicenses = $clearingDao->getFileClearings($itemTreeBounds, $this->restHelper->getGroupId(), false, true);

    $data = [];
    $scope = new DecisionScopes();

    foreach ($clearingDecWithLicenses as $clearingDecision) {
      $removedLicenses = [];
      $addedLicenses = [];

      foreach ($clearingDecision->getClearingLicenses() as $lic) {
        $shortName = $lic->getShortName();
        $lic->isRemoved() ? $removedLicenses[] = $shortName : $addedLicenses[] = $shortName;
      }
      ksort($removedLicenses, SORT_STRING);
      ksort($addedLicenses, SORT_STRING);
      $obj =  new ClearingHistory(date('Y-m-d', $clearingDecision->getTimeStamp()), $clearingDecision->getUserName(), $scope->getTypeName($clearingDecision->getScope()), $this->decisionTypes->getConstantNameFromKey($clearingDecision->getType()), $addedLicenses, $removedLicenses);
      $data[] = $obj->getArray();
    }
    return $response->withJson($data, 200);
  }

  /**
   * Get highlight entries for the contents of the item
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getHighlightEntries($request, $response, $args)
  {
    $uploadTreeId = intval($args['itemId']);
    $uploadId = intval($args['id']);
    $query = $request->getQueryParams();
    $uploadDao = $this->restHelper->getUploadDao();
    $returnVal = null;

    if (!$this->dbHelper->doesIdExist("upload", "upload_pk", $uploadId)) {
      $returnVal = new Info(404, "Upload does not exist", InfoType::ERROR);
    } else if (!$this->dbHelper->doesIdExist($uploadDao->getUploadtreeTableName($uploadId), "uploadtree_pk", $uploadTreeId)) {
      $returnVal = new Info(404, "Item does not exist", InfoType::ERROR);
    }
    $agentId = $query['agentId'] ?? null;
    $highlightId = $query['highlightId'] ?? null;
    $licenseId = $query['licenseId'] ?? null;
    $clearingId = $query['clearingId'] ?? null;

    if ($licenseId !== null && !$this->dbHelper->doesIdExist("license_ref", "rf_pk", $licenseId)) {
      $returnVal = new Info(404, "License does not exist", InfoType::ERROR);
    } else if ($highlightId !== null && !$this->dbHelper->doesIdExist("highlight", "fl_fk", $highlightId)) {
      $returnVal = new Info(404, "Highlight does not exist", InfoType::ERROR);
    } else if ($agentId !== null && !$this->dbHelper->doesIdExist("agent", "agent_pk", $agentId)) {
      $returnVal = new Info(404, "Agent does not exist", InfoType::ERROR);
    } else if ($clearingId !== null && !$this->dbHelper->doesIdExist("clearing_event", "clearing_event_pk", $clearingId)) {
      $returnVal = new Info(404, "Clearing does not exist", InfoType::ERROR);
    }

    if ($returnVal != null) {
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }

    $uploadTreeTableName = $uploadDao->getUploadtreeTableName($uploadId);
    $itemTreeBounds = $uploadDao->getItemTreeBounds($uploadTreeId, $uploadTreeTableName);
    $viewLicensePlugin = $this->restHelper->getPlugin('view-license');
    $res = $viewLicensePlugin->getSelectedHighlighting($itemTreeBounds, $licenseId,
      $agentId, $highlightId, $clearingId, $uploadId);

    $transformedRes = [];
    foreach ($res as $value) {
      $transformedRes[] = $value->getArray();
    }
    return $response->withJson($transformedRes, 200);
  }

    /**
   * Get the tree view of the upload
   *
   * @param Request $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   */
  public function getTreeView($request, $response, $args)
  {
    $uploadTreeId = intval($args['itemId']);
    $uploadId = intval($args['id']);
    $query = $request->getQueryParams();
    $agentId = $query['agentId'] ?? null;
    $flatten = $query['flatten'] ?? null;
    $scanFilter = $query['scanLicenseFilter'] ?? null;
    $editedFilter = $query['editedLicenseFilter'] ?? null;
    $sortDir = $query['sort'] ?? null;
    $page = $request->getHeaderLine('page');
    $limit = $request->getHeaderLine('limit');
    $tagId = $query['tagId'] ?? null;
    $sSearch = $query['search'] ?? null;
    $openCBoxFilter = $query['filterOpen'] ?? null;
    $show = ($query['showQuick'] !== null && $query['showQuick'] !== 'false') ? true : null;
    $licenseDao = $this->container->get('dao.license');

    $uploadDao = $this->restHelper->getUploadDao();
    $returnVal = null;

    if (!$this->dbHelper->doesIdExist("upload", "upload_pk", $uploadId)) {
      $returnVal = new Info(404, "Upload does not exist", InfoType::ERROR);
    } else if (!$this->dbHelper->doesIdExist($uploadDao->getUploadtreeTableName($uploadId), "uploadtree_pk", $uploadTreeId)) {
      $returnVal = new Info(404, "Item does not exist", InfoType::ERROR);
    } else if ($agentId !== null && !$this->dbHelper->doesIdExist("agent", "agent_pk", $agentId)) {
      $returnVal = new Info(404, "Agent does not exist", InfoType::ERROR);
    } else if ($tagId !== null && !$this->dbHelper->doesIdExist("tag", "tag_pk", $tagId)) {
      $returnVal = new Info(404, "Given Tag does not exist", InfoType::ERROR);
    } else if ($openCBoxFilter !== null && ($openCBoxFilter !== 'true' && $openCBoxFilter !== 'false')) {
      $returnVal = new Info(400, "openCBoxFilter must be a boolean value", InfoType::ERROR);
    } else if ($flatten !== null && ($flatten !== 'true' && $flatten !== 'false')) {
      $returnVal = new Info(400, "flatten must be a boolean value", InfoType::ERROR);
    } else if ($sortDir != null && !($sortDir == "asc" || $sortDir == "desc")) {
      $returnVal = new Info(400, "sortDirection must be asc or desc", InfoType::ERROR);
    } else if ($page != null && (!is_numeric($page) || intval($page) < 1)) {
      $returnVal = new Info(400, "page should be positive integer Greater or Equal to 1", InfoType::ERROR);
    } else if ($show != null && $show != 'true' && $show != 'false') {
      $returnVal = new Info(400, "show must be a boolean value", InfoType::ERROR);
    } else if ($limit != null && (!is_numeric($limit) || intval($limit) < 1)) {
      $returnVal = new Info(400, "limit must be a positive integer Greater Or Equal to 1", InfoType::ERROR);
    } else {
      $queryKeys = array_keys($query);
      $allowedKeys = ['showQuick', 'agentId', 'flatten', 'scanLicenseFilter', 'editedLicenseFilter', 'sort', 'tagId', 'search', 'filterOpen'];
      $diff = array_diff($queryKeys, $allowedKeys);
      if (count($diff) > 0) {
        $returnVal = new Info(400, "Invalid query parameter(s) : " . implode(",", $diff), InfoType::ERROR);
      }
    }

    if ($returnVal != null) {
      return $response->withJson($returnVal->getArray(), $returnVal->getCode());
    }

    if ($editedFilter !== null) {
      $license = $licenseDao->getLicenseByShortName($editedFilter, $this->restHelper->getGroupId());
      if ($license === null) {
        $returnVal = new Info(404, "Edited License filter $editedFilter does not exist", InfoType::ERROR);
        return $response->withJson($returnVal->getArray(), $returnVal->getCode());
      } else {
        $editedFilter = $license->getId();
      }
    }

    if ($scanFilter !== null) {
      $license = $licenseDao->getLicenseByShortName($scanFilter, $this->restHelper->getGroupId());
      if ($license === null) {
        $returnVal = new Info(404, "Scan License filter $scanFilter does not exist", InfoType::ERROR);
        return $response->withJson($returnVal->getArray(), $returnVal->getCode());
      } else {
        $scanFilter = $license->getId();
      }
    }

    if ($page == null) {
      $page = 1;
    }
    if ($limit == null) {
      $limit = 50;
    }

    if ($show) {
      $uploadTreeId = $uploadDao->getFatItemId($uploadTreeId, $uploadId, $uploadDao->getUploadtreeTableName($uploadId));
    }

    $ajaxExplorerPlugin = $this->restHelper->getPlugin('ajax_explorer');
    $symfonyRequest = new \Symfony\Component\HttpFoundation\Request();
    $symfonyRequest->request->set('agentId', $agentId);
    $symfonyRequest->request->set('tag', $tagId);
    $symfonyRequest->request->set('item', $uploadTreeId);
    $symfonyRequest->request->set('upload', $uploadId);
    $symfonyRequest->request->set('fromRest', true);
    $symfonyRequest->request->set('flatten', ($flatten !== null && $flatten !== 'false') ? true : null);
    $symfonyRequest->request->set('openCBoxFilter', $openCBoxFilter);
    $symfonyRequest->request->set('show', $show ? "quick" : null);
    $symfonyRequest->request->set('iSortingCols', "1");
    $symfonyRequest->request->set('bSortable_0', "true");
    $symfonyRequest->request->set('iSortCol_0', "0");
    $symfonyRequest->request->set('sSortDir_0', $sortDir != null ? $sortDir : 'asc');
    $symfonyRequest->request->set('iDisplayStart', (intval($page) - 1) * intval($limit));
    $symfonyRequest->request->set('iDisplayLength', intval($limit));
    $symfonyRequest->request->set("sSearch", $sSearch);
    $symfonyRequest->request->set("conFilter", $editedFilter);
    $symfonyRequest->request->set("scanFilter", $scanFilter);

    $res = $ajaxExplorerPlugin->handle($symfonyRequest);

    return $response->withJson(json_decode($res->getContent(), true)["aaData"], 200);
  }
}
