<?php
/*
 SPDX-FileCopyrightText: © 2014-2018, 2020-2022 Siemens AG
 Author: Johannes Najjar

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\BusinessRules\ClearingDecisionProcessor;
use Fossology\Lib\Data\Clearing\ClearingEvent;
use Fossology\Lib\Data\Clearing\ClearingEventBuilder;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\ClearingDecisionBuilder;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Proxy\UploadTreeProxy;
use Fossology\Lib\Util\StringOperation;
use Monolog\Logger;

class ClearingDao
{
  /** @var DbManager */
  private $dbManager;
  /** @var Logger */
  private $logger;
  /** @var UploadDao */
  private $uploadDao;
  /** @var CopyrightDao */
  private $copyrightDao;
  /** @var LicenseRef[] */
  private $licenseRefCache;

  /**
   * @param DbManager $dbManager
   * @param UploadDao $uploadDao
   */
  function __construct(DbManager $dbManager, UploadDao $uploadDao)
  {
    $this->dbManager = $dbManager;
    $this->logger = new Logger(self::class);
    $this->uploadDao = $uploadDao;
    $this->licenseRefCache = array();
    global $container;
    $this->copyrightDao = $container->get('dao.copyright');
  }

  private function getRelevantDecisionsCte(ItemTreeBounds $itemTreeBounds, $groupId, $onlyCurrent, &$statementName, &$params, $condition="")
  {
    $uploadTreeTable = $itemTreeBounds->getUploadTreeTableName();
    $uploadId = $itemTreeBounds->getUploadId();

    $params[] = DecisionTypes::WIP; $p1 = "$". count($params);
    $params[] = $groupId; $p2 = "$". count($params);

    $sql_upload = "";
    if ('uploadtree' === $uploadTreeTable || 'uploadtree_a' === $uploadTreeTable) {
      $params[] = $uploadId; $p = "$". count($params);
      $sql_upload = " AND ut.upload_fk=$p";
    }
    if (!empty($condition)) {
      $statementName .= ".(".$condition.")";
      $condition = " AND $condition";
    }

    $filterClause = $onlyCurrent ? "DISTINCT ON(itemid)" : "";
    $sortClause = $onlyCurrent ? "ORDER BY itemid, scope, id DESC" : "";

    $statementName .= "." . $uploadTreeTable . ($onlyCurrent ? ".current": "");

    $globalScope = DecisionScopes::REPO;
    $localScope = DecisionScopes::ITEM;

    $applyGlobal = $this->uploadDao->getGlobalDecisionSettingsFromInfo($uploadId);
    if (!empty($applyGlobal)) {
      $applyGlobal = "(ut.pfile_fk = cd.pfile_fk AND cd.scope = $globalScope) OR
                      (ut.uploadtree_pk = cd.uploadtree_fk
                      AND cd.scope = $localScope AND cd.group_fk = $p2)";
      $statementName .= "WithGlobal";
    } else {
      $applyGlobal = "(ut.uploadtree_pk = cd.uploadtree_fk
                      AND cd.group_fk = $p2)";
      $statementName .= "WithoutGlobal";
    }

    return "WITH decision AS (
              SELECT
                $filterClause
                cd.clearing_decision_pk AS id,
                cd.pfile_fk AS pfile_id,
                ut.uploadtree_pk AS itemid,
                cd.user_fk AS user_id,
                cd.decision_type AS type_id,
                cd.scope AS scope,
                EXTRACT(EPOCH FROM cd.date_added) AS ts_added
              FROM clearing_decision cd
                INNER JOIN $uploadTreeTable ut
                  ON ( $applyGlobal )
                  $sql_upload $condition
              WHERE cd.decision_type != $p1
              $sortClause
            )";
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $groupId
   * @return LicenseRef[]
   */
  function getClearedLicenses(ItemTreeBounds $itemTreeBounds, $groupId)
  {
    $statementName = __METHOD__;

    $params = array($itemTreeBounds->getLeft(), $itemTreeBounds->getRight());
    $condition = "ut.lft BETWEEN $1 AND $2";

    $decisionsCte = $this->getRelevantDecisionsCte($itemTreeBounds, $groupId, $onlyCurrent=true, $statementName, $params, $condition);
    $params[] = DecisionTypes::IRRELEVANT;
    $sql = "$decisionsCte
            SELECT
              lr.rf_pk AS license_id,
              lr.rf_shortname AS shortname,
              lr.rf_spdx_id AS spdx_id,
              lr.rf_fullname AS fullname
            FROM decision
              INNER JOIN clearing_decision_event cde ON cde.clearing_decision_fk = decision.id
              INNER JOIN clearing_event ce ON
                (ce.clearing_event_pk = cde.clearing_event_fk AND NOT ce.removed)
              INNER JOIN license_ref lr ON lr.rf_pk = ce.rf_fk
            WHERE type_id != $".count($params)."
            GROUP BY license_id,shortname,fullname,spdx_id";

    $this->dbManager->prepare($statementName, $sql);

    $res = $this->dbManager->execute($statementName, $params);

    $licenses = array();
    while ($row = $this->dbManager->fetchArray($res)) {
      $licenses[] = new LicenseRef($row['license_id'], $row['shortname'], $row['fullname'], $row['spdx_id']);
    }
    $this->dbManager->freeResult($res);

    return $licenses;
  }


  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $groupId
   * @param bool $onlyCurrent
   * @param bool $forClearingHistory
   * @return ClearingDecision[]
   */
  function getFileClearings(ItemTreeBounds $itemTreeBounds, $groupId, $onlyCurrent=true, $forClearingHistory=false)
  {
    $this->dbManager->begin();

    $statementName = __METHOD__;

    $params = array($itemTreeBounds->getItemId());
    $condition = "ut.uploadtree_pk = $1";

    $decisionsCte = $this->getRelevantDecisionsCte($itemTreeBounds, $groupId, $onlyCurrent, $statementName, $params, $condition);

    $clearingsWithLicensesArray = $this->getDecisionsFromCte($decisionsCte, $statementName, $params, $forClearingHistory);

    $this->dbManager->commit();
    return $clearingsWithLicensesArray;
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $groupId
   * @param bool $includeSubFolders
   * @param bool $onlyCurrent
   * @return ClearingDecision[]
   */
  function getFileClearingsFolder(ItemTreeBounds $itemTreeBounds, $groupId, $includeSubFolders=true, $onlyCurrent=true)
  {
    $this->dbManager->begin();

    $statementName = __METHOD__;

    if (!$includeSubFolders) {
      $params = array($itemTreeBounds->getItemId());
      $condition = "ut.realparent = $1";
    } else {
      $params = array($itemTreeBounds->getLeft(), $itemTreeBounds->getRight());
      $condition = "ut.lft BETWEEN $1 AND $2";
    }

    $decisionsCte = $this->getRelevantDecisionsCte($itemTreeBounds, $groupId, $onlyCurrent, $statementName, $params, $condition);

    $clearingsWithLicensesArray = $this->getDecisionsFromCte($decisionsCte, $statementName, $params);

    $this->dbManager->commit();
    return $clearingsWithLicensesArray;
  }

  /**
   * @param string $decisionsCte
   * @param string $statementName
   * @param array $params
   * @return ClearingDecision[]
   */
  private function getDecisionsFromCte($decisionsCte, $statementName, $params, $forClearingHistory=false)
  {
    $sql = "$decisionsCte
            SELECT
              decision.*,
              users.user_name AS user_name,
              ce.clearing_event_pk as event_id,
              ce.user_fk as event_user_id,
              ce.group_fk as event_group_id,
              lr.rf_pk AS license_id,
              lr.rf_spdx_id AS spdx_id,
              lr.rf_shortname AS shortname,
              lr.rf_fullname AS fullname,
              ce.removed AS removed,
              ce.type_fk AS event_type_id,
              ce.reportinfo AS reportinfo,
              ce.comment AS comment,
              ce.acknowledgement AS acknowledgement
            FROM decision
            LEFT JOIN users ON decision.user_id = users.user_pk
            LEFT JOIN clearing_decision_event cde ON cde.clearing_decision_fk = decision.id
            LEFT JOIN clearing_event ce ON ce.clearing_event_pk = cde.clearing_event_fk
            LEFT JOIN license_ref lr ON lr.rf_pk = ce.rf_fk
            ORDER BY decision.id DESC, event_id ASC";

    $this->dbManager->prepare($statementName, $sql);

    $result = $this->dbManager->execute($statementName, $params);
    $clearingsWithLicensesArray = array();

    $previousClearingId = -1;
    $previousItemId = -1;
    $clearingEvents = array();
    $clearingEventCache = array();
    $clearingDecisionBuilder = ClearingDecisionBuilder::create();
    $firstMatch = true;
    while ($row = $this->dbManager->fetchArray($result)) {
      $clearingId = $row['id'];
      $itemId = $row['itemid'];
      $licenseId = $row['license_id'];
      $eventId = $row['event_id'];
      $licenseSpdxId = $row['spdx_id'];
      $licenseShortName = $row['shortname'];
      $licenseName = $row['fullname'];
      $licenseIsRemoved = $row['removed'];

      $eventType = $row['event_type_id'];
      $eventUserId = $row['event_user_id'];
      $eventGroupId = $row['event_group_id'];
      $comment = $row['comment'];
      $reportInfo = $row['reportinfo'];
      $acknowledgement = $row['acknowledgement'];

      if ($clearingId !== $previousClearingId && $itemId !== $previousItemId) {
        //store the old one
        if (!$firstMatch) {
          $clearingsWithLicensesArray[] = $clearingDecisionBuilder->setClearingEvents($clearingEvents)->build();
        }

        $firstMatch = false;
        //prepare the new one
        if ($forClearingHistory) {
          $previousClearingId = $clearingId;
        } else {
          $previousItemId = $itemId;
        }
        $clearingEvents = array();
        $clearingDecisionBuilder = ClearingDecisionBuilder::create()
            ->setClearingId($row['id'])
            ->setUploadTreeId($itemId)
            ->setPfileId($row['pfile_id'])
            ->setUserName($row['user_name'])
            ->setUserId($row['user_id'])
            ->setType(intval($row['type_id']))
            ->setScope(intval($row['scope']))
            ->setTimeStamp($row['ts_added']);
      }

      if ($licenseId !== null) {
        if (!array_key_exists($eventId, $clearingEventCache)) {
          if (!array_key_exists($licenseId, $this->licenseRefCache)) {
            $this->licenseRefCache[$licenseId] = new LicenseRef($licenseId, $licenseShortName, $licenseName, $licenseSpdxId);
          }
          $licenseRef = $this->licenseRefCache[$licenseId];
          $clearingEventCache[$eventId] = $this->buildClearingEvent($eventId, $eventUserId, $eventGroupId, $licenseRef, $licenseIsRemoved, $eventType, $reportInfo, $comment, $acknowledgement);
        }
        $clearingEvents[] = $clearingEventCache[$eventId];
      }
    }

    //! Add the last match
    if (!$firstMatch) {
      $clearingsWithLicensesArray[] = $clearingDecisionBuilder->setClearingEvents($clearingEvents)->build();
    }
    $this->dbManager->freeResult($result);

    return $clearingsWithLicensesArray;
  }
  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $groupId
   * @return ClearingDecision|null
   */
  public function getRelevantClearingDecision(ItemTreeBounds $itemTreeBounds, $groupId)
  {
    $clearingDecisions = $this->getFileClearings($itemTreeBounds, $groupId);
    if (count($clearingDecisions) > 0) {
      return $clearingDecisions[0];
    }
    return null;
  }

  /**
   * @param int $uploadTreeId
   * @param int $groupId
   */
  public function removeWipClearingDecision($uploadTreeId, $groupId)
  {
    $sql = "DELETE FROM clearing_decision WHERE uploadtree_fk=$1 AND group_fk=$2 AND decision_type=$3";
    $this->dbManager->prepare($stmt = __METHOD__, $sql);
    $this->dbManager->freeResult($this->dbManager->execute($stmt, array($uploadTreeId, $groupId, DecisionTypes::WIP)));
  }

  /**
   * @param int $uploadTreeId
   * @param int $userId
   * @param int $groupId
   * @param int $decType
   * @param int $scope
   */
  public function createDecisionFromEvents($uploadTreeId, $userId, $groupId, $decType, $scope, $eventIds)
  {
    if ( ($scope == DecisionScopes::REPO) &&
         !empty($this->getCandidateLicenseCountForCurrentDecisions($uploadTreeId))) {
      throw new \Exception( _("Cannot add candidate license as global decision\n") );
    }

    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($uploadTreeId);
    $uploadId = $itemTreeBounds->getUploadId();
    $uploadTreeTable = $this->uploadDao->getUploadtreeTableName($uploadId);
    $itemTreeBounds = $this->uploadDao->getItemTreeBounds($uploadTreeId, $uploadTreeTable);

    if ($this->isDecisionCheck($uploadTreeId, $groupId, DecisionTypes::IRRELEVANT)) {
      $this->copyrightDao->updateTable($itemTreeBounds, '', '', $userId, 'copyright', 'rollback');
    } else if ($decType == DecisionTypes::IRRELEVANT) {
      $this->copyrightDao->updateTable($itemTreeBounds, '', '', $userId, 'copyright', 'delete', '2');
    }

    $this->dbManager->begin();

    $this->removeWipClearingDecision($uploadTreeId, $groupId);

    $statementName = __METHOD__;
    $this->dbManager->prepare($statementName,
        "
INSERT INTO clearing_decision (
  uploadtree_fk,
  pfile_fk,
  user_fk,
  group_fk,
  decision_type,
  scope
) VALUES (
  $1,
  (SELECT pfile_fk FROM uploadtree WHERE uploadtree_pk=$1),
  $2,
  $3,
  $4,
  $5) RETURNING clearing_decision_pk
  ");
    $res = $this->dbManager->execute($statementName,
        array($uploadTreeId, $userId,  $groupId, $decType, $scope));
    $result = $this->dbManager->fetchArray($res);
    $clearingDecisionId = $result['clearing_decision_pk'];
    $this->dbManager->freeResult($res);

    $statementNameClearingDecisionEventInsert = __METHOD__ . ".insertClearingDecisionEvent";
    $this->dbManager->prepare($statementNameClearingDecisionEventInsert,
      "INSERT INTO clearing_decision_event (clearing_decision_fk, clearing_event_fk) VALUES($1, $2)"
    );

    foreach ($eventIds as $eventId) {
      $this->dbManager->freeResult($this->dbManager->execute($statementNameClearingDecisionEventInsert, array($clearingDecisionId, $eventId)));
    }

    $this->dbManager->commit();
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $groupId
   * @return ClearingEvent[] sorted by ts_added
   */
  public function getRelevantClearingEvents($itemTreeBounds, $groupId, $includeSubFolders=true)
  {
    $decision = $this->getFileClearingsFolder($itemTreeBounds, $groupId, $includeSubFolders, $onlyCurrent=true);
    $events = array();
    $date = 0;

    if (count($decision)) {
      foreach ($decision[0]->getClearingEvents() as $event) {
        $events[$event->getLicenseId()] = $event;
      }
      $date = $decision[0]->getTimeStamp();
    }

    $stmt = __METHOD__;
    $sql = 'SELECT rf_fk,rf_shortname,rf_spdx_id,rf_fullname,clearing_event_pk,comment,type_fk,removed,reportinfo,acknowledgement, EXTRACT(EPOCH FROM date_added) AS ts_added
             FROM clearing_event LEFT JOIN license_ref ON rf_fk=rf_pk
             WHERE uploadtree_fk=$1 AND group_fk=$2 AND date_added>to_timestamp($3)
             ORDER BY clearing_event_pk ASC';
    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt,array($itemTreeBounds->getItemId(),$groupId,$date));

    while ($row = $this->dbManager->fetchArray($res)) {
      $licenseRef = new LicenseRef($row['rf_fk'],$row['rf_shortname'],$row['rf_fullname'],$row['rf_spdx_id']);
      $events[$row['rf_fk']] = ClearingEventBuilder::create()
              ->setEventId($row['clearing_event_pk'])
              ->setComment($row['comment'])
              ->setTimeStamp($row['ts_added'])
              ->setEventType($row['type_fk'])
              ->setLicenseRef($licenseRef)
              ->setRemoved($this->dbManager->booleanFromDb($row['removed']))
              ->setReportinfo($row['reportinfo'])
              ->setAcknowledgement($row['acknowledgement'])
              ->setUploadTreeId($itemTreeBounds->getItemId())
              ->build();
    }
    $this->dbManager->freeResult($res);
    return $events;
  }

  /**
   * @param int $uploadTreeId
   * @param int $userId
   * @param int $groupId
   * @param int $licenseId
   * @param string $what
   * @param string $changeTo
   */
  public function updateClearingEvent($uploadTreeId, $userId, $groupId, $licenseId, $what, $changeTo)
  {
    $this->dbManager->begin();

    $statementGetOldata = "SELECT * FROM clearing_event WHERE uploadtree_fk=$1 AND rf_fk=$2 AND group_fk=$3  ORDER BY clearing_event_pk DESC LIMIT 1";
    $statementName = __METHOD__ . 'getOld';
    $params = array($uploadTreeId, $licenseId, $groupId);
    $row = $this->dbManager->getSingleRow($statementGetOldata, $params, $statementName);

    if (!$row) {  //The license was not added as user decision yet -> we promote it here
      $type = ClearingEventTypes::USER;
      $row['type_fk'] = $type;
      $row['comment'] = "";
      $row['reportinfo'] = "";
      $row['acknowledgement'] = "";
    }

    $changeTo = StringOperation::replaceUnicodeControlChar($changeTo, false);
    if ($what == 'reportinfo') {
      $reportInfo = $changeTo;
      $comment = $row['comment'];
      $acknowledgement = $row['acknowledgement'];
    } elseif ($what == 'comment') {
      $reportInfo = $row['reportinfo'];
      $comment = $changeTo;
      $acknowledgement = $row['acknowledgement'];
    } else {
      $reportInfo = $row['reportinfo'];
      $comment = $row['comment'];
      $acknowledgement = $changeTo;
    }
    $this->insertClearingEvent($uploadTreeId, $userId, $groupId, $licenseId, false, $row['type_fk'], $reportInfo, $comment, $acknowledgement);

    $this->dbManager->commit();

  }

  public function copyEventIdTo($eventId, $itemId, $userId, $groupId)
  {
    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt,
      "INSERT INTO clearing_event(uploadtree_fk, user_fk, group_fk, type_fk, rf_fk, removed, reportinfo, comment, acknowledgement)
        SELECT $2, $3, $4, type_fk, rf_fk, removed, reportinfo, comment, acknowledgement FROM clearing_event WHERE clearing_event_pk = $1"
      );

    $this->dbManager->freeResult($this->dbManager->execute($stmt, array($eventId, $itemId, $userId, $groupId)));
  }

  /**
   * @param int $uploadTreeId
   * @param int $userId
   * @param int $groupId
   * @param int $licenseId
   * @param bool $isRemoved
   * @param int $type ClearingEventTypes
   * @param string $reportInfo
   * @param string $comment
   * @param int $jobId
   * @return int $clearing_event_pk
   */
  public function insertClearingEvent($uploadTreeId, $userId, $groupId, $licenseId, $isRemoved, $type = ClearingEventTypes::USER, $reportInfo = '', $comment = '', $acknowledgement = '', $jobId=0)
  {
    $insertIsRemoved = $this->dbManager->booleanToDb($isRemoved);

    $reportInfo = StringOperation::replaceUnicodeControlChar($reportInfo);
    $comment = StringOperation::replaceUnicodeControlChar($comment);
    $acknowledgement = StringOperation::replaceUnicodeControlChar($acknowledgement);

    $stmt = __METHOD__;
    $params = array($uploadTreeId, $userId, $groupId, $type, $licenseId, $insertIsRemoved, $reportInfo, $comment, $acknowledgement);
    $columns = "uploadtree_fk, user_fk, group_fk, type_fk, rf_fk, removed, reportinfo, comment, acknowledgement";
    $values = "$1,$2,$3,$4,$5,$6,$7,$8,$9";

    if ($jobId > 0) {
      $stmt.= ".jobId";
      $params[] = $jobId;
      $columns .= ", job_fk";
      $values .= ",$".count($params);
    } else {
      $this->markDecisionAsWip($uploadTreeId, $userId, $groupId);
    }

    $this->dbManager->prepare($stmt, "INSERT INTO clearing_event ($columns) VALUES($values) RETURNING clearing_event_pk");
    $res = $this->dbManager->execute($stmt, $params);

    $row = $this->dbManager->fetchArray($res);
    $this->dbManager->freeResult($res);

    return intval($row['clearing_event_pk']);
  }

  /**
   * @param int $jobId
   * @return int[][] eventIds indexed by itemId and licenseId
   */
  public function getEventIdsOfJob($jobId)
  {
    $statementName = __METHOD__;
    $this->dbManager->prepare(
        $statementName,
        "SELECT uploadtree_fk, clearing_event_pk, rf_fk FROM clearing_event WHERE job_fk = $1"
    );

    $res = $this->dbManager->execute($statementName, array($jobId));

    $events = array();
    while ($row = $this->dbManager->fetchArray($res)) {
      $itemId = intval($row['uploadtree_fk']);
      $eventId = intval($row['clearing_event_pk']);
      $licenseId = intval($row['rf_fk']);

      $events[$itemId][$licenseId] = $eventId;
    }
    $this->dbManager->freeResult($res);

    return $events;
  }

  /**
   * @param int $eventId
   * @param int $userId
   * @param int $groupId
   * @param LicenseRef $licenseRef
   * @param $licenseIsRemoved
   * @param $type
   * @param $reportInfo
   * @param string $comment
   * @param $acknowledgement
   * @return ClearingEvent
   */
  protected function buildClearingEvent($eventId, $userId, $groupId, $licenseRef, $licenseIsRemoved, $type, $reportInfo, $comment, $acknowledgement)
  {
    $removed = $this->dbManager->booleanFromDb($licenseIsRemoved);

    return ClearingEventBuilder::create()
      ->setEventId($eventId)
      ->setUserId($userId)
      ->setGroupId($groupId)
      ->setEventType($type)
      ->setLicenseRef($licenseRef)
      ->setRemoved($removed)
      ->setReportInfo($reportInfo)
      ->setAcknowledgement($acknowledgement)
      ->setComment($comment)
      ->build();
  }

  /**
   * @param int $uploadTreeId
   * @param int $userId
   * @param int $groupId
   */
  public function markDecisionAsWip($uploadTreeId, $userId, $groupId)
  {
    $statementName = __METHOD__;

    $this->dbManager->prepare($statementName,
        "INSERT INTO clearing_decision (uploadtree_fk,pfile_fk,user_fk,group_fk,decision_type,scope) VALUES (
            $1, (SELECT pfile_fk FROM uploadtree WHERE uploadtree_pk=$1), $2, $3, $4, $5)");
    $res = $this->dbManager->execute($statementName,
        array($uploadTreeId, $userId, $groupId, DecisionTypes::WIP, DecisionScopes::ITEM));
    $this->dbManager->freeResult($res);
  }

  /**
   * @param int $uploadTreeId
   * @param int $groupId
   * @param int $decisionType
   */
  public function isDecisionCheck($uploadTreeId, $groupId, $decisionType)
  {
    $columns = "decision_type";
    if (!in_array($decisionType,
         [DecisionTypes::WIP, DecisionTypes::TO_BE_DISCUSSED,
          DecisionTypes::DO_NOT_USE, DecisionTypes::IRRELEVANT,
          DecisionTypes::NON_FUNCTIONAL])
       ) {
      $columns = "decision_type, scope";
    }
    $sql = "SELECT $columns FROM clearing_decision
              WHERE uploadtree_fk=$1 AND group_fk = $2
            ORDER BY clearing_decision_pk DESC LIMIT 1";
    $latestDec = $this->dbManager->getSingleRow($sql,
                 array($uploadTreeId, $groupId), $sqlLog = __METHOD__);

    if ($latestDec === false) {
      return false;
    } else if ($decisionType !== "") {
      return ($latestDec['decision_type'] == $decisionType);
    } else {
      return $latestDec;
    }
  }

  /**
   * @param ItemTreeBounds $itemTreeBound
   * @param int $groupId
   * @param boolean $onlyTried
   * @return array[] where array has keys ("bulkId","id","text","matched","tried","removedLicenses","addedLicenses")
   */
  public function getBulkHistory(ItemTreeBounds $itemTreeBound, $groupId, $onlyTried = true)
  {
    $uploadTreeTableName = $itemTreeBound->getUploadTreeTableName();
    $itemId = $itemTreeBound->getItemId();
    $uploadId = $itemTreeBound->getUploadId();
    $left = $itemTreeBound->getLeft();

    $params = array($uploadId, $itemId, $left, $groupId);
    $stmt = __METHOD__ . "." . $uploadTreeTableName;

    $triedExpr = "$3 between ut2.lft and ut2.rgt";
    $triedFilter = "";
    if ($onlyTried) {
      $triedFilter = "and " . $triedExpr;
      $stmt .= ".tried";
    }

    $sql = "WITH alltried AS (
            SELECT lr.lrb_pk, ce.clearing_event_pk ce_pk, lr.rf_text, ce.uploadtree_fk,
              $triedExpr AS tried
            FROM license_ref_bulk lr
              LEFT JOIN highlight_bulk h ON lrb_fk = lrb_pk
              LEFT JOIN clearing_event ce ON ce.clearing_event_pk = h.clearing_event_fk
              LEFT JOIN $uploadTreeTableName ut ON ut.uploadtree_pk = ce.uploadtree_fk
              INNER JOIN $uploadTreeTableName ut2 ON ut2.uploadtree_pk = lr.uploadtree_fk
            WHERE ut2.upload_fk = $1 AND lr.group_fk = $4
              $triedFilter
              ORDER BY lr.lrb_pk
            ), aggregated_tried AS (
            SELECT DISTINCT ON(lrb_pk) lrb_pk, ce_pk, rf_text AS text, tried, matched
            FROM (
              SELECT DISTINCT ON(lrb_pk) lrb_pk, ce_pk, rf_text, tried, true AS matched FROM alltried WHERE uploadtree_fk = $2
              UNION ALL
              SELECT DISTINCT ON(lrb_pk) lrb_pk, ce_pk, rf_text, tried, false AS matched FROM alltried WHERE uploadtree_fk != $2 OR uploadtree_fk IS NULL
            ) AS result ORDER BY lrb_pk, matched DESC)
            SELECT lrb_pk, text, rf_shortname, removing, tried, ce_pk, matched
            FROM aggregated_tried
              INNER JOIN license_set_bulk lsb ON lsb.lrb_fk = lrb_pk
              INNER JOIN license_ref lrf ON lsb.rf_fk = lrf.rf_pk
            ORDER BY lrb_pk";

    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt, $params);

    $bulks = array();
    while ($row = $this->dbManager->fetchArray($res)) {
      $bulkRun = $row['lrb_pk'];
      if (!array_key_exists($bulkRun, $bulks)) {
        $bulks[$bulkRun] = array(
            "bulkId" => $row['lrb_pk'],
            "id" => $row['ce_pk'],
            "text" => $row['text'],
            "matched" => $this->dbManager->booleanFromDb($row['matched']),
            "tried" => $this->dbManager->booleanFromDb($row['tried']),
            "removedLicenses" => array(),
            "addedLicenses" => array());
      }
      $key = $this->dbManager->booleanFromDb($row['removing']) ? 'removedLicenses' : 'addedLicenses';
      $bulks[$bulkRun][$key][] = $row['rf_shortname'];
    }

    $this->dbManager->freeResult($res);
    return $bulks;
  }


  public function getBulkMatches($bulkId, $groupId)
  {
    $stmt = __METHOD__;
    $sql = "SELECT uploadtree_fk AS itemid
            FROM clearing_event ce
            INNER JOIN highlight_bulk h
            ON ce.clearing_event_pk = h.clearing_event_fk
            WHERE lrb_fk = $1 AND group_fk = $2";

    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt, array($bulkId, $groupId));

    $result = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    return $result;
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $groupId
   * @return array mapping 'shortname'=>'count'
   */
  function getClearedLicenseIdAndMultiplicities(ItemTreeBounds $itemTreeBounds, $groupId)
  {
    $statementName = __METHOD__;

    $params = array($itemTreeBounds->getLeft(), $itemTreeBounds->getRight());
    $condition = "ut.lft BETWEEN $1 AND $2";

    $decisionsCte = $this->getRelevantDecisionsCte($itemTreeBounds, $groupId, $onlyCurrent=true, $statementName, $params, $condition);
    $params[] = DecisionTypes::IRRELEVANT;
    $sql = "$decisionsCte
            SELECT
              COUNT(DISTINCT itemid) AS count,
              lr.rf_shortname AS shortname,
              lr.rf_spdx_id AS spdx_id,
              rf_pk
            FROM decision
              LEFT JOIN clearing_decision_event cde ON cde.clearing_decision_fk = decision.id
              LEFT JOIN clearing_event ce ON ce.clearing_event_pk = cde.clearing_event_fk
              LEFT JOIN license_ref lr ON lr.rf_pk = ce.rf_fk
            WHERE (NOT ce.removed OR clearing_event_pk IS NULL) AND type_id!=$".count($params)."
            GROUP BY shortname,rf_pk,spdx_id";

    $this->dbManager->prepare($statementName, $sql);
    $res = $this->dbManager->execute($statementName, $params);
    $multiplicity = array();
    while ($row = $this->dbManager->fetchArray($res)) {
      $shortname = empty($row['rf_pk']) ? LicenseDao::NO_LICENSE_FOUND : $row['shortname'];
      $row['spdx_id'] = LicenseRef::convertToSpdxId($shortname, $row['spdx_id']);
      $multiplicity[$shortname] = $row;
    }
    $this->dbManager->freeResult($res);

    return $multiplicity;
  }

  /**
   * @param int $decisionType
   * @return int actual DecisionTypes
   */
  public function getDecisionType($decisionType)
  {
    if ($decisionType == "doNotUse" || $decisionType == "deleteDoNotUse") {
      return DecisionTypes::DO_NOT_USE;
    } else if ($decisionType == "irrelevant" || $decisionType == "deleteIrrelevant") {
      return DecisionTypes::IRRELEVANT;
    } else {
      return DecisionTypes::NON_FUNCTIONAL;
    }
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $groupId
   * @param int $userId
   * @param $decisionMark
   */
  public function markDirectoryAsDecisionType(ItemTreeBounds $itemTreeBounds, $groupId, $userId, $decisionMark)
  {
    $decisionMark = $this->getDecisionType($decisionMark);
    $this->markDirectoryAsDecisionTypeRec($itemTreeBounds, $groupId, $userId, false, $decisionMark);
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $groupId
   * @param int $userId
   * @param $decisionMark
   */
  public function deleteDecisionTypeFromDirectory(ItemTreeBounds $itemTreeBounds, $groupId, $userId, $decisionMark)
  {
    $decisionMark = $this->getDecisionType($decisionMark);
    $this->markDirectoryAsDecisionTypeRec($itemTreeBounds, $groupId, $userId, true, $decisionMark);
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $groupId
   * @param int $userId
   * @param bool $removeDecision
   * @param int $decisionMark
   */
  protected function markDirectoryAsDecisionTypeRec(ItemTreeBounds $itemTreeBounds, $groupId, $userId, $removeDecision=false, $decisionMark=DecisionTypes::IRRELEVANT)
  {
    $params = array($itemTreeBounds->getLeft(), $itemTreeBounds->getRight());
    $params[] = $groupId;
    $a = count($params);
    $options = array(UploadTreeProxy::OPT_SKIP_THESE=>'noLicense',
                     UploadTreeProxy::OPT_ITEM_FILTER=>' AND (lft BETWEEN $1 AND $2)',
                     UploadTreeProxy::OPT_GROUP_ID=>'$'.$a);
    $uploadTreeProxy = new UploadTreeProxy($itemTreeBounds->getUploadId(), $options, $itemTreeBounds->getUploadTreeTableName());
    if (!$removeDecision) {
      $sql = $uploadTreeProxy->asCTE() .
        ' SELECT uploadtree_pk FROM UploadTreeView;';
      $itemRows = $this->dbManager->getRows($sql, $params,
        __METHOD__ . ".getRevelantItems");
      $uploadTreeTableName = $itemTreeBounds->getUploadTreeTableName();
      /** @var ClearingDecisionProcessor $clearingDecisionEventProcessor */
      $clearingDecisionEventProcessor = $GLOBALS['container']->get(
        'businessrules.clearing_decision_processor');
      foreach ($itemRows as $itemRow) {
        $itemBounds = $this->uploadDao->getItemTreeBounds(
          $itemRow['uploadtree_pk'], $uploadTreeTableName);
        $clearingDecisionEventProcessor->makeDecisionFromLastEvents(
          $itemBounds, $userId, $groupId, $decisionMark, DecisionScopes::ITEM);
      }
    } else {
      $this->dbManager->begin();
      $params[] = $decisionMark;
      $sql = $uploadTreeProxy->asCTE() .
        ' DELETE FROM clearing_decision WHERE clearing_decision_pk IN (
            SELECT clearing_decision_pk FROM clearing_decision cd
            INNER JOIN (
              SELECT MAX(date_added) AS date_added, uploadtree_fk
                FROM clearing_decision WHERE uploadtree_fk IN (
                  SELECT uploadtree_pk FROM UploadTreeView)
              GROUP BY uploadtree_fk) cd2
            ON cd.uploadtree_fk = cd2.uploadtree_fk
            AND cd.date_added = cd2.date_added
            AND decision_type = $' . ($a + 1) . ')
         RETURNING clearing_decision_pk;';
      $clearingDecisionRows = $this->dbManager->getRows($sql, $params,
        __METHOD__ . ".getRelevantDecisions");
      $clearingDecisions = array_map(function($x) {
        return $x['clearing_decision_pk'];
      }, $clearingDecisionRows);
      $clearingDecisions = "{" . join(",", $clearingDecisions) . "}";

      $delEventSql = "DELETE FROM clearing_event WHERE clearing_event_pk IN (" .
        "SELECT clearing_event_fk FROM clearing_decision_event " .
        "WHERE clearing_decision_fk = ANY($1::int[]));";
      $this->dbManager->getSingleRow($delEventSql, array($clearingDecisions),
        __METHOD__ . ".deleteEvent");

      $delCdEventSql = "DELETE FROM clearing_decision_event WHERE " .
        "clearing_decision_fk = ANY($1::int[]);";
      $this->dbManager->getSingleRow($delCdEventSql, array($clearingDecisions),
        __METHOD__ . ".deleteCdEvent");
      $this->dbManager->commit();
      $this->copyrightDao->updateTable($itemTreeBounds, '', '', $userId,
        'copyright', 'rollback');
    }
  }

  /**
   * @param int $uploadId
   * @param int $groupId
   * @return array $ids
   */
  public function getMainLicenseIds($uploadId, $groupId)
  {
    $stmt = __METHOD__;
    $sql = "SELECT rf_fk FROM upload_clearing_license WHERE upload_fk=$1 AND group_fk=$2";
    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt,array($uploadId,$groupId));
    $ids = array();
    while ($row = $this->dbManager->fetchArray($res)) {
      $ids[$row['rf_fk']] = $row['rf_fk'];
    }
    $this->dbManager->freeResult($res);
    return $ids;
  }

  /**
   * @param $uploadId
   * @param int $groupId
   * @param int $licenseId
   */
  public function makeMainLicense($uploadId, $groupId, $licenseId)
  {
    $this->dbManager->insertTableRow('upload_clearing_license',
            array('upload_fk'=>$uploadId,'group_fk'=>$groupId,'rf_fk'=>$licenseId));
  }

  /**
   * @param int $uploadId
   * @param int $groupId
   * @param int $licenseId
   */
  public function removeMainLicense($uploadId, $groupId, $licenseId)
  {
    $this->dbManager->getSingleRow('DELETE FROM upload_clearing_license WHERE upload_fk=$1 AND group_fk=$2 AND rf_fk=$3',
            array($uploadId,$groupId,$licenseId));
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $groupId
   * @param bool $onlyCurrent
   * @param string $decisionMark
   * @return ClearingDecision[]
   */
  function getFilesForDecisionTypeFolderLevel(ItemTreeBounds $itemTreeBounds, $groupId, $onlyCurrent=true, $decisionMark="")
  {
    $decisionMark = $this->getDecisionType($decisionMark);
    $statementName = __METHOD__;
    $params = array();
    $decisionsCte = $this->getRelevantDecisionsCte($itemTreeBounds, $groupId, $onlyCurrent, $statementName, $params);
    $params[] = $decisionMark;
    $sql = "$decisionsCte
            SELECT
            itemid as uploadtree_pk,
            lr.rf_shortname AS shortname,
            lr.rf_spdx_id AS spdx_id,
            comment
            FROM decision
            LEFT JOIN clearing_decision_event cde ON cde.clearing_decision_fk = decision.id
            LEFT JOIN clearing_event ce ON ce.clearing_event_pk = cde.clearing_event_fk
            LEFT JOIN license_ref lr ON lr.rf_pk = ce.rf_fk
            WHERE type_id=$".count($params);
    $this->dbManager->prepare($statementName, $sql);
    $res = $this->dbManager->execute($statementName, $params);
    $irrelevantFiles = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    return $irrelevantFiles;
  }

  /**
   * @param int $uploadId
   * @param int $groupId
   * @param int $userId
   */
  public function getPreviousBulkIds($uploadId, $groupId, $userId, $onlyCount=0)
  {
    $stmt = __METHOD__;
    $bulkIds = array();
    $sql = "SELECT jq_args FROM upload_reuse, jobqueue, job
            WHERE upload_fk=$1 AND group_fk=$2
             AND EXISTS(SELECT * FROM group_user_member gum WHERE gum.group_fk=upload_reuse.group_fk AND gum.user_fk=$3)
             AND jq_type=$4 AND jq_job_fk=job_pk
             AND job_upload_fk=reused_upload_fk AND job_group_fk=reused_group_fk";
    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt,array($uploadId, $groupId, $userId,'monkbulk'));
    while ($row=  $this->dbManager->fetchArray($res)) {
      $bulkIds = array_merge($bulkIds,explode("\n", $row['jq_args']));
    }
    $this->dbManager->freeResult($res);
    if (empty($onlyCount)) {
      return array_unique($bulkIds);
    } else {
      return count(array_unique($bulkIds));
    }
  }

  /**
   * @param int $uploadTreeId
   * @return int count
   */
  public function getCandidateLicenseCountForCurrentDecisions($uploadTreeId, $uploadId=0)
  {
    $params = array();
    if (!empty($uploadId)) {
      $uploadTreeTableName = $this->uploadDao->getUploadtreeTableName($uploadId);
      $itemTreeBounds = $this->uploadDao->getParentItemBounds($uploadId, $uploadTreeTableName);
      $params[] = $itemTreeBounds->getLeft();
      $params[] = $itemTreeBounds->getRight();
      $condition = "UT.lft BETWEEN $1 AND $2";
      $uploadtreeStatement = " uploadtree_fk IN (SELECT uploadtree_pk FROM $uploadTreeTableName UT WHERE $condition)";
    } else {
      $params = array($uploadTreeId);
      $uploadtreeStatement = " uploadtree_fk = $1";
    }

    $sql = "WITH latestEvents AS (
      SELECT rf_fk, date_added, removed FROM (
        SELECT rf_fk, date_added, removed, row_number()
          OVER (PARTITION BY rf_fk ORDER BY date_added DESC) AS ROWNUM
        FROM clearing_event WHERE $uploadtreeStatement) SORTABLE
          WHERE ROWNUM = 1 ORDER BY rf_fk)
      SELECT count(*) FROM license_candidate WHERE license_candidate.rf_pk IN
        (SELECT rf_fk FROM latestEvents WHERE removed=false);";
    $countCandidate = $this->dbManager->getSingleRow($sql,
                 $params, $sqlLog = __METHOD__);

    return $countCandidate['count'];
  }

  /**
   * @param int $uploadId
   * @return int count
   */
  public function marklocalDecisionsAsGlobal($uploadId)
  {
    $statementName = __METHOD__ . $uploadId;

    $sql = "WITH latestDecisions AS (
              SELECT clearing_decision_pk FROM (
                SELECT clearing_decision_pk, uploadtree_fk, date_added, row_number()
                  OVER (PARTITION BY uploadtree_fk ORDER BY date_added DESC) AS ROWNUM
              FROM clearing_decision WHERE uploadtree_fk IN
                   (SELECT uploadtree_pk FROM uploadtree WHERE upload_fk = $1)) SORTABLE
                WHERE ROWNUM = $2 ORDER BY uploadtree_fk)
             UPDATE clearing_decision SET scope = $2 WHERE clearing_decision_pk IN (
               SELECT clearing_decision_pk FROM latestDecisions) RETURNING clearing_decision_pk";

    $countUpdated = $this->dbManager->getSingleRow($sql,
                 array($uploadId, DecisionScopes::REPO), $statementName);

    return count($countUpdated);
  }
}
