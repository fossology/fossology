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

use Fossology\Lib\BusinessRules\ClearingDecisionProcessor;
use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Dao\HighlightDao;
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\Clearing\ClearingResult;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Exceptions\HttpErrorException;
use Fossology\UI\Api\Exceptions\HttpInternalServerErrorException;
use Fossology\UI\Api\Exceptions\HttpNotFoundException;
use Fossology\UI\Api\Helper\ResponseHelper;
use Fossology\UI\Api\Models\BulkHistory;
use Fossology\UI\Api\Models\ClearingHistory;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;
use Fossology\UI\Api\Models\LicenseDecision;
use Fossology\UI\Api\Models\Obligation;
use Fossology\UI\Api\Models\ApiVersion;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @class UploadTreeController
 * @brief Controller for UploadTree model
 */
class UploadTreeController extends RestController
{
  /** @var ClearingDao $clearingDao */
  private $clearingDao;

  /**
   * @var LicenseDao $licenseDao
   * License Dao object
   */
  private $licenseDao;

  /**
   * @var HighlightDao $highlightDao
   * HighlightDao object
   */
  private $highlightDao;

  /** @var ClearingDecisionProcessor $clearingDecisionEventProcessor */
  private $clearingDecisionEventProcessor;

  /**
   * @var DecisionTypes $decisionTypes
   * Decision types object
   */
  private $decisionTypes;


  public function __construct($container)
  {
    parent::__construct($container);
    $this->clearingDao = $this->container->get('dao.clearing');
    $this->licenseDao = $this->container->get('dao.license');
    $this->highlightDao = $this->container->get("dao.highlight");
    $this->clearingDecisionEventProcessor = $this->container->get('businessrules.clearing_decision_processor');
    $this->decisionTypes = $this->container->get('decision.types');
  }

  /**
   * Get the contents of a specific file
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function viewLicenseFile($request, $response, $args)
  {
    $uploadId = intval($args['id']);
    $itemId = intval($args['itemId']);

    $this->uploadAccessible($uploadId);
    $this->isItemExists($uploadId, $itemId);

    /** @var \ui_view $view */
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
      throw new HttpInternalServerErrorException($errorMess);
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
   * @throws HttpErrorException
   */
  public function setClearingDecision($request, $response, $args)
  {
    $uploadId = intval($args['id']);
    $this->uploadAccessible($uploadId);

    $body = $this->getParsedBody($request);
    $decisionType = $body['decisionType'];
    $global = $body['globalDecision'];

    // check if the given globalDecision value is a boolean
    if ($global !== null && !is_bool($global)) {
      throw new HttpBadRequestException("GlobalDecision should be a boolean");
    }

    $uploadTreeId = intval($args['itemId']);

    // check if the given key exists in the known decision types
    if (!array_key_exists($decisionType, $this->decisionTypes->getMap())) {
      throw new HttpBadRequestException(
        "Decision Type should be one of the following keys: " .
        implode(", ", array_keys($this->decisionTypes->getMap())));
    }
    $this->isItemExists($uploadId, $uploadTreeId);

    /** @var \ClearingView $viewLicensePlugin */
    $viewLicensePlugin = $this->restHelper->getPlugin('view-license');
    $_GET['clearingTypes'] = $decisionType;
    $_GET['globalDecision'] = $global ? 1 : 0;
    $viewLicensePlugin->updateLastItem($this->restHelper->getUserId(), $this->restHelper->getGroupId(), $uploadTreeId, $uploadTreeId);
    $returnVal = new Info(200, "Successfully set decision", InfoType::INFO);
    return $response->withJson($returnVal->getArray(), $returnVal->getCode());
  }

  /**
   * Get the next and previous item for a given upload and itemId
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function getNextPreviousItem($request, $response, $args)
  {
    $uploadTreeId = intval($args['itemId']);
    $uploadId = intval($args['id']);
    $query = $request->getQueryParams();
    $uploadDao = $this->restHelper->getUploadDao();
    $selection = "";

    $this->uploadAccessible($uploadId);
    $this->isItemExists($uploadId, $uploadTreeId);

    if ($query['selection'] !== null) {
      $selection = $query['selection'];
      if ($selection != "withLicenses" && $selection != "noClearing") {
        throw new HttpBadRequestException(
          "selection should be either 'withLicenses' or 'noClearing'");
      }
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
   * @throws HttpErrorException
   */
  public function getBulkHistory($request, $response, $args)
  {
    $uploadTreeId = intval($args['itemId']);
    $uploadId = intval($args['id']);
    $uploadDao = $this->restHelper->getUploadDao();
    $clearingDao = $this->container->get('dao.clearing');

    $this->uploadAccessible($uploadId);
    $this->isItemExists($uploadId, $uploadTreeId);

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
   * @throws HttpErrorException
   */
  public function getClearingHistory($request, $response, $args)
  {
    $itemId = intval($args['itemId']);
    $uploadId = intval($args['id']);
    $uploadDao = $this->restHelper->getUploadDao();
    $clearingDao = $this->container->get('dao.clearing');

    $this->uploadAccessible($uploadId);
    $this->isItemExists($uploadId, $itemId);

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
      $obj = new ClearingHistory(
        date('Y-m-d', $clearingDecision->getTimeStamp()),
        $clearingDecision->getUserName(),
        $scope->getTypeName($clearingDecision->getScope()),
        $this->decisionTypes->getConstantNameFromKey($clearingDecision->getType()),
        $addedLicenses, $removedLicenses);
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
   * @throws HttpErrorException
   */
  public function getHighlightEntries($request, $response, $args)
  {
    $uploadTreeId = intval($args['itemId']);
    $uploadId = intval($args['id']);
    $query = $request->getQueryParams();
    $uploadDao = $this->restHelper->getUploadDao();

    $this->uploadAccessible($uploadId);
    $this->isItemExists($uploadId, $uploadTreeId);

    $agentId = $query['agentId'] ?? null;
    $highlightId = $query['highlightId'] ?? null;
    $licenseId = $query['licenseId'] ?? null;
    $clearingId = $query['clearingId'] ?? null;

    if ($licenseId !== null && !$this->dbHelper->doesIdExist("license_ref", "rf_pk", $licenseId)) {
      throw new HttpNotFoundException("License does not exist");
    }
    if ($highlightId !== null && !$this->dbHelper->doesIdExist("highlight", "fl_fk", $highlightId)) {
      throw new HttpNotFoundException("Highlight does not exist");
    }
    if ($agentId !== null && !$this->dbHelper->doesIdExist("agent", "agent_pk", $agentId)) {
      throw new HttpNotFoundException("Agent does not exist");
    }
    if ($clearingId !== null && !$this->dbHelper->doesIdExist("clearing_event", "clearing_event_pk", $clearingId)) {
      throw new HttpNotFoundException("Clearing does not exist");
    }

    $uploadTreeTableName = $uploadDao->getUploadtreeTableName($uploadId);
    $itemTreeBounds = $uploadDao->getItemTreeBounds($uploadTreeId, $uploadTreeTableName);
    /** @var \ClearingView $viewLicensePlugin */
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
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
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
    if (ApiVersion::getVersion($request) == ApiVersion::V2) {
      $page = $query['page'] ?? null;
      $limit = $query['limit'] ?? null;
    } else {
      $page = $request->getHeaderLine('page');
      $limit = $request->getHeaderLine('limit');
    }
    $tagId = $query['tagId'] ?? null;
    $sSearch = $query['search'] ?? null;
    $openCBoxFilter = $query['filterOpen'] ?? null;
    $show = ($query['showQuick'] !== null && $query['showQuick'] !== 'false') ? true : null;
    $licenseDao = $this->container->get('dao.license');

    $uploadDao = $this->restHelper->getUploadDao();

    $this->uploadAccessible($uploadId);
    $this->isItemExists($uploadId, $uploadTreeId);

    if ($agentId !== null && !$this->dbHelper->doesIdExist("agent", "agent_pk", $agentId)) {
      throw new HttpNotFoundException("Agent does not exist");
    }
    if ($tagId !== null && !$this->dbHelper->doesIdExist("tag", "tag_pk", $tagId)) {
      throw new HttpNotFoundException("Tag does not exist");
    }
    if ($openCBoxFilter !== null && ($openCBoxFilter !== 'true' && $openCBoxFilter !== 'false')) {
      throw new HttpBadRequestException("openCBoxFilter must be a boolean value");
    }
    if ($flatten !== null && ($flatten !== 'true' && $flatten !== 'false')) {
      throw new HttpBadRequestException("flatten must be a boolean value");
    }
    if ($sortDir != null && !($sortDir == "asc" || $sortDir == "desc")) {
      throw new HttpBadRequestException("sortDirection must be asc or desc");
    }
    if ($page != null && (!is_numeric($page) || intval($page) < 1)) {
      throw new HttpBadRequestException("page should be positive integer Greater or Equal to 1");
    }
    if ($show != null && $show != 'true' && $show != 'false') {
      throw new HttpBadRequestException("show must be a boolean value");
    }
    if ($limit != null && (!is_numeric($limit) || intval($limit) < 1)) {
      throw new HttpBadRequestException("limit should be positive integer Greater or Equal to 1");
    }
    $queryKeys = array_keys($query);
    $allowedKeys = ['showQuick', 'agentId', 'flatten', 'scanLicenseFilter', 'editedLicenseFilter', 'sort', 'tagId', 'search', 'filterOpen'];
    $diff = array_diff($queryKeys, $allowedKeys);
    if (count($diff) > 0) {
      throw new HttpBadRequestException("Invalid query parameter(s) : " . implode(",", $diff));
    }

    if ($editedFilter !== null) {
      $license = $licenseDao->getLicenseByShortName($editedFilter, $this->restHelper->getGroupId());
      if ($license === null) {
        throw new HttpNotFoundException("Edited License filter $editedFilter does not exist");
      }
      $editedFilter = $license->getId();
    }

    if ($scanFilter !== null) {
      $license = $licenseDao->getLicenseByShortName($scanFilter, $this->restHelper->getGroupId());
      if ($license === null) {
        throw new HttpNotFoundException("Scan License filter $scanFilter does not exist");
      }
      $scanFilter = $license->getId();
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

    /** @var \Fossology\UI\Ajax\AjaxExplorer $ajaxExplorerPlugin */
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

  /**
   * Get all license decisions for a particular upload-tree
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function getLicenseDecisions($request, $response, $args)
  {
    $uploadTreeId = intval($args['itemId']);
    $uploadPk = intval($args['id']);
    $uploadDao = $this->restHelper->getUploadDao();
    $licenses = [];

    $this->uploadAccessible($uploadPk);
    $this->isItemExists($uploadPk, $uploadTreeId);

    $itemTreeBounds = $uploadDao->getItemTreeBoundsFromUploadId($uploadTreeId, $uploadPk);
    if ($itemTreeBounds->containsFiles()) {
      throw new HttpBadRequestException("Item expected to be a file, container sent.");
    }

    list ($addedClearingResults, $removedLicenses) = $this->clearingDecisionEventProcessor->getCurrentClearings(
      $itemTreeBounds, $this->restHelper->getGroupId(), LicenseMap::CONCLUSION);
    $licenseEventTypes = new ClearingEventTypes();

    $mergedArray = [];

    foreach ($addedClearingResults as $item) {
      $mergedArray[] = ['item' => $item, 'isRemoved' => false];
    }

    foreach ($removedLicenses as $item) {
      $mergedArray[] = ['item' => $item, 'isRemoved' => true];
    }

    $mainLicIds = $this->clearingDao->getMainLicenseIds($uploadPk, $this->restHelper->getGroupId());

    foreach ($mergedArray as $item) {
      $clearingResult = $item['item'];
      $licenseShortName = $clearingResult->getLicenseShortName();
      $licenseId = $clearingResult->getLicenseId();

      $types = $this->getAgentInfo($clearingResult);
      $reportInfo = "";
      $comment = "";
      $acknowledgement = "";

      if ($clearingResult->hasClearingEvent()) {
        $licenseDecisionEvent = $clearingResult->getClearingEvent();
        $types[] = $this->getEventInfo($licenseDecisionEvent, $licenseEventTypes);
        $reportInfo = $licenseDecisionEvent->getReportinfo();
        $comment = $licenseDecisionEvent->getComment();
        $acknowledgement = $licenseDecisionEvent->getAcknowledgement();
      }

      $obligations = $this->licenseDao->getLicenseObligations([$licenseId], false);
      $obligations = array_merge($obligations, $this->licenseDao->getLicenseObligations([$licenseId], true));
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
      $license = $this->licenseDao->getLicenseById($licenseId);
      $licenseObj = new LicenseDecision(
        $license->getId(),
        $licenseShortName,
        $license->getFullName(),
        $item['isRemoved'] ? '-' : (!empty($reportInfo) ? $reportInfo : $license->getText()),
        $license->getUrl(),
        $types,
        $item['isRemoved'] ? '-' : $acknowledgement,
        $item['isRemoved'] ? '-' : $comment,
        in_array($license->getId(), $mainLicIds),
        $obligationList,
        $license->getRisk(),
        $item['isRemoved']
      );
      $licenses[] = $licenseObj->getArray();
    }
    return $response->withJson($licenses, 200);
  }

  /**
   * @param ClearingResult $licenseDecisionResult
   * @return array
   */
  private function getAgentInfo(ClearingResult $licenseDecisionResult)
  {
    $agentResults = array();
    foreach ($licenseDecisionResult->getAgentDecisionEvents() as $agentDecisionEvent) {
      $agentId = $agentDecisionEvent->getAgentId();
      $matchId = $agentDecisionEvent->getMatchId();
      $highlightRegion = $this->highlightDao->getHighlightRegion($matchId);
      $page = null;
      $percentage = $agentDecisionEvent->getPercentage();
      if ($highlightRegion[0] != "" && $highlightRegion[1] != "") {
        $page = $this->highlightDao->getPageNumberOfHighlightEntry($matchId);
      }
      $result = array(
        'name' => $agentDecisionEvent->getAgentName(),
        'clearingId' => null,
        'agentId' => $agentId,
        'highlightId' => $matchId,
        'page' => intval($page),
        'percentage' => $percentage
      );
      $agentResults[] = $result;
    }
    return $agentResults;
  }

  private function getEventInfo($licenseDecisionEvent, $licenseEventTypes)
  {
    $type = $licenseEventTypes->getTypeName($licenseDecisionEvent->getEventType());
    $eventId = null;
    if ($licenseDecisionEvent->getEventType() == ClearingEventTypes::BULK) {
      $eventId = $licenseDecisionEvent->getEventId();
    }
    return array(
      'name' => $type,
      'clearingId' => $eventId,
      'agentId' => null,
      'highlightId' => null,
      'page' => null,
      'percentage' => null
    );
  }

  /**
   * Handle add, edit and delete license decision
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function handleAddEditAndDeleteLicenseDecision($request, $response, $args)
  {
    $body = $this->getParsedBody($request);
    $uploadTreeId = intval($args['itemId']);
    $uploadId = intval($args['id']);
    $uploadDao = $this->restHelper->getUploadDao();
    $errors = [];
    $success = [];

    $this->uploadAccessible($uploadId);
    $this->isItemExists($uploadId, $uploadTreeId);

    if (empty($body)) {
      throw new HttpBadRequestException("Request body is missing or empty.");
    }
    if (!is_array($body)) {
      throw new HttpBadRequestException("Request body should be an array.");
    }
    /** @var \AjaxClearingView $concludeLicensePlugin */
    $concludeLicensePlugin = $this->restHelper->getPlugin('conclude-license');
    $res = $concludeLicensePlugin->getCurrentSelectedLicensesTableData($uploadDao->getItemTreeBoundsFromUploadId($uploadTreeId, $uploadId), $this->restHelper->getGroupId(), true);

    $existingLicenseIds = [];
    foreach ($res as $license) {
      $currId = $license['DT_RowId'];
      $currId = explode(',', $currId)[1];
      $existingLicenseIds[] = intval($currId);
    }

    foreach (array_keys($body) as $index) {
      $licenseReq = $body[$index];

      $shortName = $licenseReq['shortName'];
      if (empty($shortName)) {
        $error = new Info(400, "Short name missing from the request #" . ($index + 1), InfoType::ERROR);
        $errors[] = $error->getArray();
        continue;
      }

      $existingLicense = $this->licenseDao->getLicenseByShortName($shortName, $this->restHelper->getGroupId());
      if ($existingLicense === null) {
        $error = new Info(404, "License file with short name '$shortName' not found.",
          InfoType::ERROR);
        $errors[] = $error->getArray();
        continue;
      }

      if (!isset($licenseReq['add'])) {
        $error = new Info(400, "'add' property missing from the request #" . ($index + 1), InfoType::ERROR);
        $errors[] = $error->getArray();
        continue;
      }

      if ($licenseReq['add']) {
        $columnsToUpdate = [];
        if (isset($licenseReq['text'])) {
          $columnsToUpdate[] = [
            'columnId' => 'reportinfo',
            'value' => $licenseReq['text']
          ];
        }
        if (isset($licenseReq['ack'])) {
          $columnsToUpdate[] = [
            'columnId' => 'acknowledgement',
            'value' => $licenseReq['ack']
          ];
        }
        if (isset($licenseReq['comment'])) {
          $columnsToUpdate[] = [
            'columnId' => 'comment',
            'value' => $licenseReq['comment']
          ];
        }

        if (in_array($existingLicense->getId(), $existingLicenseIds)) {
          $valText = "";
          foreach (array_keys($columnsToUpdate) as $colIdx) {
            $this->clearingDao->updateClearingEvent($uploadTreeId, $this->restHelper->getUserId(), $this->restHelper->getGroupId(), $existingLicense->getId(), $columnsToUpdate[$colIdx]['columnId'], $columnsToUpdate[$colIdx]['value']);
            if ($colIdx == count($columnsToUpdate) - 1 && count($columnsToUpdate) > 1) {
              $valText .= " and ";
            } else if ($colIdx > 0 && count($columnsToUpdate) > 1) {
              $valText .= ", ";
            }
            $valText .= $columnsToUpdate[$colIdx]['columnId'];
          }
          $val = new Info(200, "Successfully updated " . $shortName . "'s license " . $valText, InfoType::INFO);
        } else {
          $this->clearingDao->insertClearingEvent($uploadTreeId, $this->restHelper->getUserId(), $this->restHelper->getGroupId(), $existingLicense->getId(), false);
          foreach (array_keys($columnsToUpdate) as $colIdx) {
            $this->clearingDao->updateClearingEvent($uploadTreeId, $this->restHelper->getUserId(), $this->restHelper->getGroupId(), $existingLicense->getId(), $columnsToUpdate[$colIdx]['columnId'], $columnsToUpdate[$colIdx]['value']);
          }
          $val = new Info(200, "Successfully added " . $shortName . " as a new license decision.", InfoType::INFO);
        }
      } else {
        if (!in_array($existingLicense->getId(), $existingLicenseIds)) {
          $error = new Info(404, $shortName . " license does not exist on this item", InfoType::ERROR);
          $errors[] = $error->getArray();
          continue;
        }
        $this->clearingDao->insertClearingEvent($uploadTreeId, $this->restHelper->getUserId(), $this->restHelper->getGroupId(), $existingLicense->getId(), true);
        $val = new Info(200, "Successfully deleted " . $shortName . " from license decision list.", InfoType::INFO);
      }
      $success[] = $val->getArray();
    }

    return $response->withJson([
      'success' => $success,
      'errors' => $errors
    ], 200);
  }

  /**
   * Schedule a bulk scan for an uploadtree item
   *
   * @param ServerRequestInterface $request
   * @param ResponseHelper $response
   * @param array $args
   * @return ResponseHelper
   * @throws HttpErrorException
   */
  public function scheduleBulkScan($request, $response, $args)
  {
    $body = $this->getParsedBody($request);
    $uploadTreeId = intval($args['itemId']);
    $uploadId = intval($args['id']);
    $licenseDao = $this->container->get('dao.license');

    $this->uploadAccessible($uploadId);
    $this->isItemExists($uploadId, $uploadTreeId);

    $isValid = true;

    // Validation errors
    $errors = [];

    // Verify if each element from $body was given in the request
    $requiredFields = ['bulkActions', 'refText', 'bulkScope', 'forceDecision', 'ignoreIrre', 'delimiters', 'scanOnlyFindings'];

    foreach ($requiredFields as $field) {
      if (!array_key_exists($field, $body)) {
        $isValid = false;
        $errors[] = "$field should be given";
      }
    }
    // Additional validations for specific fields
    if ($isValid) {
      // Check if refText is not equal to ""
      if ($body['refText'] === "") {
        $isValid = false;
        $errors[] = "refText should not be empty";
      }
      // Check if forceDecision and ignoreIrre are true or false
      if (!in_array($body['forceDecision'], [true, false]) || !in_array($body['ignoreIrre'], [true, false]) || !in_array($body['scanOnlyFindings'], [true, false])) {
        $isValid = false;
        $errors[] = "forceDecision, ignoreIrre and scanOnlyFindings should be either true or false";
      }
      // Check if delimiters is a string
      if (!is_string($body['delimiters'])) {
        $isValid = false;
        $errors[] = "delimiters should be a string";
      }
      // check if bulkScope value is either folder or upload
      if (!in_array($body['bulkScope'], ["folder", "upload"])) {
        $isValid = false;
        $errors[] = "bulkScope should be either folder or upload";
      }
      // check if the bulkActions property is an array and if each element has a valid license id
      if (!is_array($body['bulkActions'])) {
        $isValid = false;
        $errors[] = "bulkActions should be an array";
      } else {
        foreach ($body['bulkActions'] as &$license) {
          $existingLicense = $licenseDao->getLicenseByShortName($license['licenseShortName'],
            $this->restHelper->getGroupId());
          if ($existingLicense == null) {
            $isValid = false;
            $errors[] = "License with short name " . $license['licenseShortName'] . " does not exist";
          } else if ($license['licenseAction'] != null && !in_array($license['licenseAction'], ["ADD", "REMOVE"])) {
            $isValid = false;
            $errors[] = "License action should be either ADD or REMOVE";
          }

          $license['licenseId'] = $existingLicense->getId();
          $license['reportinfo'] = $license['licenseText'];
          $license['action'] = $license['licenseAction'] == 'REMOVE' ? 'Remove' : 'Add';
        }
      }
    }

    $errorMess = "";
    if (!$isValid) {
      foreach ($errors as $error) {
        $errorMess = $error . "\n";
      }
      throw new HttpBadRequestException($errorMess);
    }

    $symfonyRequest = new \Symfony\Component\HttpFoundation\Request();
    $symfonyRequest->request->set('bulkAction', $body['bulkActions']);
    $symfonyRequest->request->set('refText', $body['refText']);
    $symfonyRequest->request->set('bulkScope', $body['bulkScope'] == "folder" ? 'f' : 'u');
    $symfonyRequest->request->set('uploadTreeId', $uploadTreeId);
    $symfonyRequest->request->set('forceDecision', $body['forceDecision'] ? 1 : 0);
    $symfonyRequest->request->set('ignoreIrre', $body['ignoreIrre'] ? 1 : 0);
    $symfonyRequest->request->set('delimiters', $body['delimiters']);
    $symfonyRequest->request->set('scanOnlyFindings', $body['scanOnlyFindings'] ? 1 : 0);

    /** @var \ChangeLicenseBulk $changeLicenseBulk */
    $changeLicenseBulk = $this->restHelper->getPlugin('change-license-bulk');
    $res = $changeLicenseBulk->handle($symfonyRequest);
    $status = $res->getStatusCode();

    if ($status != 200) {
      if ($status == Response::HTTP_BAD_REQUEST) {
        throw new HttpBadRequestException(
          json_decode($res->getContent(), true)["error"]);
      }
      throw new HttpInternalServerErrorException(
        json_decode($res->getContent(), true)["error"]);
    }

    $info = new Info(201, json_decode($res->getContent(), true)["jqid"], InfoType::INFO);
    return $response->withJson($info->getArray(), $info->getCode());
  }
}
