<?php
/*
Copyright (C) 2014, Siemens AG
Author: Johannes Najjar

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
*/

namespace Fossology\Lib\Dao;

use DateTime;
use Fossology\Lib\Data\Clearing\ClearingEvent;
use Fossology\Lib\Data\Clearing\ClearingEventBuilder;
use Fossology\Lib\Data\Clearing\ClearingLicense;
use Fossology\Lib\Data\Clearing\ClearingEventTypes;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\ClearingDecisionBuilder;
use Fossology\Lib\Data\DecisionScopes;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\Object;
use Monolog\Logger;
use Fossology\Lib\Proxy\LicenseViewProxy;

class ClearingDao extends Object
{
  /** @var DbManager */
  private $dbManager;
  /** @var Logger */
  private $logger;
  /** @var UploadDao */
  private $uploadDao;

  /**
   * @param DbManager $dbManager
   * @param UploadDao $uploadDao
   */
  function __construct(DbManager $dbManager, UploadDao $uploadDao)
  {
    $this->dbManager = $dbManager;
    $this->logger = new Logger(self::className()); //$container->get("logger");
    $this->uploadDao = $uploadDao;
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $groupId
   * @param bool 
   * @return ClearingDecision[]
   */
  function getFileClearingsFolder(ItemTreeBounds $itemTreeBounds, $groupId, $onlyCurrent=true)
  {
    //The first join to uploadtree is to find out if this is the same upload <= this needs to be uploadtree
    //The second gives all the clearing decisions which correspond to a filehash in the folder <= we can use the special upload table
    $uploadTreeTable = $itemTreeBounds->getUploadTreeTableName();

    $joinType = $itemTreeBounds->containsFiles() ? "INNER" : "LEFT";

    $sql_upload = "";
    if ('uploadtree' === $uploadTreeTable || 'uploadtree_a' === $uploadTreeTable)
    {
      $sql_upload = "ut.upload_fk=$1  and ";
    }

    $filterClause = $onlyCurrent ? "distinct on(itemid)" : "";

    $options = array('columns' => array('rf_pk', 'rf_shortname', 'rf_fullname'), 'candidatePrefix' => '*');
    $licenseViewDao = new LicenseViewProxy($groupId, $options, 'lr');
    $withCte = $licenseViewDao->asCTE();

    $statementName = __METHOD__ . "." . $groupId . "." . $uploadTreeTable . ($onlyCurrent ? ".current": "");

    $globalScope = DecisionScopes::REPO;

    $sql = "$withCte,
            allDecs AS (
              SELECT
                cd.clearing_decision_pk AS id,
                cd.pfile_fk AS pfile_id,
                users.user_name AS user_name,
                cd.user_fk AS user_id,
                cd.decision_type AS type_id,
                cd.scope AS scope,
                EXTRACT(EPOCH FROM cd.date_added) AS date_added,
                ut2.upload_fk = $1 AND ut2.lft BETWEEN $2 AND $3 AS is_local,
                ut.uploadtree_pk AS itemid
              FROM clearing_decision cd
                LEFT JOIN users ON cd.user_fk=users.user_pk
                INNER JOIN uploadtree ut2 ON cd.uploadtree_fk = ut2.uploadtree_pk
                INNER JOIN " . $uploadTreeTable . " ut ON cd.pfile_fk = ut.pfile_fk AND scope = $globalScope OR ut.uploadtree_pk = ut2.uploadtree_pk
              WHERE " . $sql_upload . " ut.lft BETWEEN $2 AND $3
                AND CD.decision_type!=$4 AND cd.group_fk = $5
              GROUP BY id, itemid, pfile_id, user_name, user_id, type_id, scope, date_added, is_local
              ORDER by cd.clearing_decision_pk DESC),
            relevant AS (
              SELECT $filterClause *
              FROM allDecs
              ORDER BY itemid, is_local DESC, id DESC
            )
            SELECT
              r.*,
              ce.clearing_event_pk as event_id,
              ce.user_fk as event_user_id,
              ce.group_fk as event_group_id,
              lr.rf_pk AS license_id,
              lr.rf_shortname AS shortname,
              lr.rf_fullname AS fullname,
              ce.removed AS removed,
              ce.type_fk AS event_type_id,
              ce.reportinfo AS reportinfo,
              ce.comment AS comment
            FROM relevant r
            LEFT JOIN clearing_decision_event cde ON cde.clearing_decision_fk = r.id
            LEFT JOIN clearing_event ce ON ce.clearing_event_pk = cde.clearing_event_fk
            LEFT JOIN lr ON lr.rf_pk = ce.rf_fk
            ORDER BY r.id DESC";

    $this->dbManager->prepare($statementName, $sql);

    // the array needs to be sorted with the newest clearingDecision first.
    $params = array($itemTreeBounds->getUploadId(), $itemTreeBounds->getLeft(), $itemTreeBounds->getRight(), DecisionTypes::WIP, $groupId);
    $result = $this->dbManager->execute($statementName, $params);
    $clearingsWithLicensesArray = array();

    $previousClearingId = -1;
    $clearingEvents = array();
    $clearingDecisionBuilder = ClearingDecisionBuilder::create();
    $firstMatch = true;
    while ($row = $this->dbManager->fetchArray($result))
    {
      $clearingId = $row['id'];
      $licenseId = $row['license_id'];
      $eventId = $row['event_id'];
      $licenseShortName = $row['shortname'];
      $licenseName = $row['fullname'];
      $licenseIsRemoved = $row['removed'];

      $eventType = $row['event_type_id'];
      $eventUserId = $row['event_user_id'];
      $eventGroupId = $row['event_group_id'];
      $comment = $row['comment'];
      $reportInfo = $row['reportinfo'];

      if ($clearingId !== $previousClearingId)
      {
        //store the old one
        if (!$firstMatch)
        {
          $clearingsWithLicensesArray[] = $clearingDecisionBuilder->setClearingEvents($clearingEvents)->build();
        }

        $firstMatch = false;
        //prepare the new one
        $previousClearingId = $clearingId;
        $clearingEvents = array();
        $clearingDecisionBuilder = ClearingDecisionBuilder::create()
            ->setSameFolder($this->dbManager->booleanFromDb($row['is_local']))
            ->setClearingId($row['id'])
            ->setUploadTreeId($row['itemid'])
            ->setPfileId($row['pfile_id'])
            ->setUserName($row['user_name'])
            ->setUserId($row['user_id'])
            ->setType(intval($row['type_id']))
            ->setScope(intval($row['scope']))
            ->setDateAdded($row['date_added']);
      }

      if ($licenseId !== null)
      {
        $this->appendToClearingEvents($eventId, $eventUserId, $eventGroupId, $licenseId, $licenseShortName, $licenseName, $licenseIsRemoved, $eventType, $reportInfo, $comment, $clearingEvents);
      }
    }

    //! Add the last match
    if (!$firstMatch)
    {
      $clearingsWithLicensesArray[] = $clearingDecisionBuilder->setClearingEvents($clearingEvents)->build();
    }

    $this->dbManager->freeResult($result);
    return $clearingsWithLicensesArray;
  }

  /**
   * @param int[] $licenseIds
   * @param bool $removed
   * @param int $uploadTreeId
   * @param int $userId
   * @param int $groupId
   * @param int $jobId
   * @param string $comment
   * @param string $remark
   */
  public function insertMultipleClearingEvents($licenseIds, $removed, $uploadTreeId, $userId, $groupId, $jobId, $comment = "", $remark = "")
  {
    $this->dbManager->begin();

    $statementName = __METHOD__ . ".s";
    $this->dbManager->prepare($statementName,
        "with thisItem AS (select * from uploadtree where uploadtree_pk = $1)
         SELECT uploadtree.* from uploadtree, thisItem
         WHERE uploadtree.lft BETWEEN thisItem.lft AND thisItem.rgt AND ((uploadtree.ufile_mode & (3<<28))=0) AND uploadtree.pfile_fk != 0",
        array($uploadTreeId),
        $statementName);
    $items = $this->dbManager->execute($statementName, array($uploadTreeId));

    $type = ClearingEventTypes::USER;

    while ($item = $this->dbManager->fetchArray($items))
    {
      $currentUploadTreeId = $item['uploadtree_pk'];
      foreach ($licenseIds as $license)
      {
        $aDecEvent = array('uploadtree_fk' => $currentUploadTreeId, 'user_fk' => $userId,
            'group_fk' => $groupId,
            'rf_fk' => $license, 'removed' => $removed, 'job_fk' => $jobId,
            'type_fk' => $type, 'comment' => $comment, 'reportinfo' => $remark);
        $this->dbManager->insertTableRow('clearing_event', $aDecEvent, $sqlLog = __METHOD__);
      }
    }
    $this->dbManager->freeResult($items);

    $this->dbManager->commit();
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $groupId
   * @return ClearingDecision|null
   */
  public function getRelevantClearingDecision(ItemTreeBounds $itemTreeBounds, $groupId)
  {
    $clearingDecisions = $this->getFileClearingsFolder($itemTreeBounds, $groupId);
    if (count($clearingDecisions) > 0)
    {
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
   * @param $uploadTreeId
   * @param $userId
   * @param $decType
   * @param $scope
   * @param ClearingLicense[] $clearingDecisions
   * @param ClearingLicense[] $agentClearingDecisions
   *
   * @deprecated
   */
  public function insertClearingDecision($uploadTreeId, $userId, $groupId, $decType, $scope, $clearingLicenses, $agentClearingDecisions = array())
  {
    $needTransaction = !$this->dbManager->isInTransaction();
    if ($needTransaction) $this->dbManager->begin();

    $statementNameClearingEventInsert = __METHOD__ . ".insertClearingEvent";
    $this->dbManager->prepare($statementNameClearingEventInsert,
      "INSERT INTO clearing_event (uploadtree_fk, user_fk, group_fk, rf_fk, removed, type_fk, comment, reportinfo) VALUES($1, $2, $3, $4, $5, $6, $7, $8) RETURNING clearing_event_pk AS id"
    );

    $eventIds = array();

    foreach (array_merge($clearingLicenses,$agentClearingDecisions) as $clearingLicense)
    {
      $resI = $this->dbManager->execute($statementNameClearingEventInsert,
        array(
          $uploadTreeId, $userId, $groupId,
          $clearingLicense->getLicenseId(), $this->dbManager->booleanToDb($clearingLicense->isRemoved()),
          $clearingLicense->getType(),
          $clearingLicense->getComment(), $clearingLicense->getReportInfo()
        )
      );
      $row = $this->dbManager->fetchArray($resI);
      if (false !== $row)
      {
        $eventIds[] = $row['id'];
      }
      else
      {
        throw new \Exception("cannot insert clearing_decision_event");
      }
      $this->dbManager->freeResult($resI);
    }

    $this->createDecisionFromEvents($uploadTreeId, $userId, $groupId, $decType, $scope, $eventIds);

    if ($needTransaction) $this->dbManager->commit();
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
    $needTransaction = !$this->dbManager->isInTransaction();
    if ($needTransaction) $this->dbManager->begin();

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

    foreach ($eventIds as $eventId)
    {
      $this->dbManager->freeResult($this->dbManager->execute($statementNameClearingDecisionEventInsert, array($clearingDecisionId, $eventId)));
    }
    if ($needTransaction) $this->dbManager->commit();
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $groupId
   * @return ClearingEvent[] sorted by date_added
   */
  public function getRelevantClearingEvents($itemTreeBounds, $groupId)
  {
    $decision = $this->getFileClearingsFolder($itemTreeBounds, $groupId, $onlyCurrent=true);
    $events = array();
    $date = 0;

    if(count($decision))
    {
      foreach ($decision[0]->getClearingEvents() as $event)
      {
        $events[$event->getLicenseId()] = $event;
      }
      $date = $decision[0]->getTimeStamp();
    }
    
    /* @TODO use LicenseViewProxy */
    
    $stmt = __METHOD__;
    $sql = 'SELECT rf_fk,rf_shortname,rf_fullname,clearing_event_pk,comment,type_fk,removed,reportinfo, EXTRACT(EPOCH FROM date_added) AS ts_added
             FROM clearing_event LEFT JOIN license_ref ON rf_fk=rf_pk 
             WHERE uploadtree_fk=$1 AND group_fk=$2 AND EXTRACT(EPOCH FROM date_added)>$3
             ORDER BY ts_added ASC';
    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt,array($itemTreeBounds->getItemId(),$groupId,$date));

    while($row=  $this->dbManager->fetchArray($res)){
      $licenseRef = new LicenseRef($row['rf_fk'],$row['rf_shortname'],$row['rf_fullname']);
      $events[$row['rf_fk']] = ClearingEventBuilder::create()
              ->setEventId($row['clearing_event_pk'])
              ->setComment($row['comment'])
              ->setDateFromTimeStamp($row['ts_added'])
              ->setEventType($row['type_fk'])
              ->setLicenseRef($licenseRef)
              ->setRemoved($this->dbManager->booleanFromDb($row['removed']))
              ->setReportinfo($row['reportinfo'])
              ->setUploadTreeId($itemTreeBounds->getItemId())
              ->build();
    }
    $this->dbManager->freeResult($res);
    return $events;
  }

  public function updateClearingEvent($uploadTreeId, $userId, $groupId, $licenseId, $what, $changeTo)
  {
    $this->dbManager->begin();

    $statementGetOldata = "SELECT * FROM clearing_event WHERE uploadtree_fk=$1 AND rf_fk=$2 AND group_fk=$3  ORDER BY clearing_event_pk DESC LIMIT 1";
    $statementName = __METHOD__ . 'getOld';
    $params = array($uploadTreeId, $licenseId, $groupId);
    $row = $this->dbManager->getSingleRow($statementGetOldata, $params, $statementName);

    if (!$row)
    {  //The license was not added as user decision yet -> we promote it here
      $type = ClearingEventTypes::USER;
      $this->insertClearingEvent($uploadTreeId, $userId, $groupId, $licenseId, false, $type);
      $row['type_fk'] = $type;
      $row['comment'] = "";
      $row['reportinfo'] = "";
    }

    if ($what == 'Text')
    {
      $reportInfo = $changeTo;
      $comment = $row['comment'];
    } else
    {
      $reportInfo = $row['reportinfo'];
      $comment = $changeTo;

    }
    $this->insertClearingEvent($uploadTreeId, $userId, $groupId, $licenseId, false, $row['type_fk'], $reportInfo, $comment);

    $this->dbManager->commit();

  }

  public function insertClearingEvent($uploadTreeId, $userId, $groupId, $licenseId, $isRemoved, $type = ClearingEventTypes::USER, $reportInfo = '', $comment = '')
  {
    $this->markDecisionAsWip($uploadTreeId, $userId, $groupId);
    $insertIsRemoved = $this->dbManager->booleanToDb($isRemoved);

    $stmt = __METHOD__;
    $this->dbManager->prepare($stmt,
    "INSERT INTO clearing_event (uploadtree_fk, user_fk, group_fk, type_fk, rf_fk, removed, reportinfo, comment) VALUES($1,$2,$3,$4,$5,$6,$7,$8) RETURNING clearing_event_pk"
    );
    $res = $this->dbManager->execute(
        $stmt,
        array($uploadTreeId, $userId, $groupId, $type, $licenseId, $insertIsRemoved, $reportInfo, $comment)
    );

    $row = $this->dbManager->fetchArray($res);
    $this->dbManager->freeResult($res);

    return $row['clearing_event_pk'];
  }

  public function getItemsChangedBy($jobId)
  {
    $statementName = __METHOD__;
    $this->dbManager->prepare(
        $statementName,
        "SELECT DISTINCT(uploadtree_fk) FROM clearing_event WHERE job_fk = $1"
    );

    $res = $this->dbManager->execute($statementName, array($jobId));

    $items = array();
    while ($row = $this->dbManager->fetchArray($res))
    {
      $items[] = $row['uploadtree_fk'];
    }
    $this->dbManager->freeResult($res);

    return $items;
  }

  /**
   * @param $licenseId
   * @param $licenseShortName
   * @param $licenseName
   * @param $licenseIsRemoved
   * @param $clearingLicenses
   */
  protected function appendToClearingEvents($eventId, $userId, $groupId, $licenseId, $licenseShortName, $licenseName, $licenseIsRemoved, $type, $reportInfo, $comment, &$clearingEvents)
  {
    $licenseRef = new LicenseRef($licenseId, $licenseShortName, $licenseName);
    $removed = $this->dbManager->booleanFromDb($licenseIsRemoved);

    // TODO set date itemId

    $clearingEvents[] = ClearingEventBuilder::create()
      ->setEventId($eventId)
      ->setUserId($userId)
      ->setGroupId($groupId)
      ->setEventType($type)
      ->setLicenseRef($licenseRef)
      ->setRemoved($removed)
      ->setReportInfo($reportInfo)
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

  public function isDecisionWip($uploadTreeId, $groupId)
  {
    $sql = "SELECT decision_type FROM clearing_decision WHERE uploadtree_fk=$1 AND group_fk = $2 ORDER BY date_added DESC LIMIT 1";
    $latestDec = $this->dbManager->getSingleRow($sql, array($uploadTreeId, $groupId), $sqlLog = __METHOD__);
    if ($latestDec === false)
    {
      return false;
    }
    return ($latestDec['decision_type'] == DecisionTypes::WIP);
  }

  // TODO add group
  public function getBulkHistory(ItemTreeBounds $itemTreeBound, $onlyTried = true)
  {
    $uploadTreeTableName = $itemTreeBound->getUploadTreeTableName();
    $itemId = $itemTreeBound->getItemId();
    $uploadId = $itemTreeBound->getUploadId();
    $left = $itemTreeBound->getLeft();

    $params = array($uploadId, $itemId, $left);
    $stmt = __METHOD__ . "." . $uploadTreeTableName;

    $triedExpr = "$3 between ut2.lft and ut2.rgt";
    $triedFilter = "";
    if ($onlyTried)
    {
      $triedFilter = "and " . $triedExpr;
      $stmt .= ".tried";
    }

    $sql = "with alltried as (
            select lr.lrb_pk,
              ce.clearing_event_pk ce_pk, lr.rf_text, lrf.rf_shortname, removing,
              ce.uploadtree_fk,
              $triedExpr as tried
              from license_ref_bulk lr
              left join highlight_bulk h on lrb_fk = lrb_pk
              left join clearing_event ce on ce.clearing_event_pk = h.clearing_event_fk
              left join $uploadTreeTableName ut on ut.uploadtree_pk = ce.uploadtree_fk
              inner join $uploadTreeTableName ut2 on ut2.uploadtree_pk = lr.uploadtree_fk
              inner join license_ref lrf on lr.rf_fk = lrf.rf_pk
              where ut2.upload_fk = $1
              $triedFilter
              order by lr.lrb_pk
            )
            SELECT distinct on(lrb_pk) lrb_pk, ce_pk, rf_text as text, rf_shortname as lic, removing, tried, matched
            FROM (
              SELECT distinct on(lrb_pk) lrb_pk, ce_pk, rf_text, rf_shortname, removing, tried, true as matched FROM alltried WHERE uploadtree_fk = $2
              UNION ALL
              SELECT distinct on(lrb_pk) lrb_pk, ce_pk, rf_text, rf_shortname, removing, tried, false as matched FROM alltried WHERE uploadtree_fk != $2 or uploadtree_fk is NULL
            ) AS result ORDER BY lrb_pk, matched DESC";

    $this->dbManager->prepare($stmt, $sql);

    $res = $this->dbManager->execute($stmt, $params);

    $bulks = array();

    while ($row = $this->dbManager->fetchArray($res))
    {
      $bulks[] = array(
          "bulkId" => $row['lrb_pk'],
          "id" => $row['ce_pk'],
          "text" => $row['text'],
          "lic" => $row['lic'],
          "removing" => $this->dbManager->booleanFromDb($row['removing']),
          "matched" => $this->dbManager->booleanFromDb($row['matched']),
          "tried" => $this->dbManager->booleanFromDb($row['tried'])
      );
    }

    $this->dbManager->freeResult($res);
    return $bulks;
  }

  // TODO add group
  public function getBulkMatches($bulkId, $userId)
  {
    $stmt = __METHOD__;
    $sql = "SELECT uploadtree_fk AS itemid
            FROM clearing_event ce
            INNER JOIN highlight_bulk h
            ON ce.clearing_event_pk = h.clearing_event_fk
            WHERE lrb_fk = $1 AND user_fk = $2";

    $this->dbManager->prepare($stmt, $sql);
    $res = $this->dbManager->execute($stmt, array($bulkId, $userId));

    $result = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    return $result;
  }
}
