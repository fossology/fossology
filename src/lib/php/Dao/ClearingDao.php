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

use Fossology\Lib\BusinessRules\NewestEditedLicenseSelector;
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
use Fossology\Lib\Util\Object;
use Monolog\Logger;

class ClearingDao extends Object
{
  /** @var DbManager */
  private $dbManager;
  /** @var Logger */
  private $logger;
  /** @var NewestEditedLicenseSelector */
  protected $newestEditedLicenseSelector;
  /** @var UploadDao */
  private $uploadDao;

  /**
   * @param DbManager $dbManager
   */
  function __construct(DbManager $dbManager, NewestEditedLicenseSelector $newestEditedLicenseSelector, UploadDao $uploadDao)
  {
    $this->dbManager = $dbManager;
    $this->logger = new Logger(self::className()); //$container->get("logger");
    $this->newestEditedLicenseSelector = $newestEditedLicenseSelector;
    $this->uploadDao = $uploadDao;
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @return ClearingDecision[]
   */
  function getFileClearingsFolder(ItemTreeBounds $itemTreeBounds)
  {
    //The first join to uploadtree is to find out if this is the same upload <= this needs to be uploadtree
    //The second gives all the clearing decisions which correspond to a filehash in the folder <= we can use the special upload table
    $uploadTreeTable = $itemTreeBounds->getUploadTreeTableName();

    $joinType = $itemTreeBounds->containsFiles() ? "INNER" : "LEFT";

    $sql_upload="";
    if ('uploadtree_a' == $uploadTreeTable) {
      $sql_upload = "ut.upload_fk=$1  and ";
    }

    $statementName = __METHOD__.".".$uploadTreeTable.".".$joinType;

    $sql="SELECT
           CD.clearing_decision_pk AS id,
           CD.uploadtree_fk AS uploadtree_id,
           CD.pfile_fk AS pfile_id,
           users.user_name AS user_name,
           CD.user_fk AS user_id,
           CD.decision_type AS type_id,
           CD.scope as scope,
           EXTRACT(EPOCH FROM CD.date_added) AS date_added,
           ut2.upload_fk = $1 AS same_upload,
           ut2.upload_fk = $1 and ut2.lft BETWEEN $2 and $3 AS is_local,
           LR.rf_pk as license_id,
           LR.rf_shortname as shortname,
           LR.rf_fullname as fullname,
           CL.removed as removed
         FROM clearing_decision CD
           LEFT JOIN users ON CD.user_fk=users.user_pk
           INNER JOIN uploadtree ut2 ON CD.uploadtree_fk = ut2.uploadtree_pk
           INNER JOIN ".$uploadTreeTable." ut ON CD.pfile_fk = ut.pfile_fk
           $joinType JOIN clearing_licenses CL on CL.clearing_fk = CD.clearing_decision_pk
           $joinType JOIN license_ref LR on CL.rf_fk=LR.rf_pk
         WHERE ".$sql_upload." ut.lft BETWEEN $2 and $3
           AND CD.decision_type!=$4
         GROUP BY id, uploadtree_id, pfile_id, user_name, user_id, type_id, scope, date_added, same_upload, is_local,
           license_id, shortname, fullname, removed
         ORDER by CD.pfile_fk, CD.clearing_decision_pk desc";

    $this->dbManager->prepare($statementName, $sql);

    // the array needs to be sorted with the newest clearingDecision first.
    $params = array($itemTreeBounds->getUploadId(), $itemTreeBounds->getLeft(), $itemTreeBounds->getRight(), DecisionTypes::WIP);
    $result = $this->dbManager->execute($statementName, $params);
    $clearingsWithLicensesArray = array();

    $previousClearingId  = -1;
    $added=array();
    $removed=array();
    $clearingDecisionBuilder = ClearingDecisionBuilder::create();
    $firstMatch = true;
    while ($row = $this->dbManager->fetchArray($result))
    {
      $clearingId = $row['id'];
      $licenseId = $row['license_id'];
      $licenseShortName = $row['shortname'];
      $licenseName = $row['fullname'];
      $licenseIsRemoved = $row['removed'];

      if($clearingId !== $previousClearingId) {
        //store the old one
        if(!$firstMatch) {
          $clearingDec =$clearingDecisionBuilder->setPositiveLicenses($added)
                                                ->setNegativeLicenses($removed)
                                                ->build();
          $clearingsWithLicensesArray[] = $clearingDec;
        }

        $firstMatch = false;
        //prepare the new one
        $previousClearingId  = $clearingId;
        $added=array();
        $removed=array();
        $clearingDecisionBuilder = ClearingDecisionBuilder::create()
                                    ->setSameUpload($this->dbManager->booleanFromDb($row['same_upload']))
                                    ->setSameFolder($this->dbManager->booleanFromDb($row['is_local']))
                                    ->setClearingId($row['id'])
                                    ->setUploadTreeId($row['uploadtree_id'])
                                    ->setPfileId($row['pfile_id'])
                                    ->setUserName($row['user_name'])
                                    ->setUserId($row['user_id'])
                                    ->setType($row['type_id'])
                                    ->setScope($row['scope'])
                                    ->setDateAdded($row['date_added']);
      }

      if ($licenseId !== null) {
        $this->appendToRemovedAdded($licenseId, $licenseShortName, $licenseName, $licenseIsRemoved, $removed, $added);
      }
    }

    //! Add the last match
    if(!$firstMatch)
    {
      $clearingDec = $clearingDecisionBuilder->setPositiveLicenses($added)
          ->setNegativeLicenses($removed)
          ->build();
      $clearingsWithLicensesArray[] = $clearingDec;
    }

    $this->dbManager->freeResult($result);
    return $clearingsWithLicensesArray;
  }

  /**
   * @param int $clearingId
   * @return array pair of LicenseRef[]
   */
  private function getFileClearingLicenses($clearingId)
  {
    $statementN = __METHOD__;
    $this->dbManager->prepare($statementN,
        "select
               LR.rf_pk as id,
               LR.rf_shortname as shortname,
               LR.rf_fullname as fullname,
               CL.removed as removed
           from clearing_licenses CL
           left join license_ref LR on CL.rf_fk=LR.rf_pk
               where CL.clearing_fk=$1");

    $res = $this->dbManager->execute($statementN, array($clearingId));
    $added = array();
    $removed = array();
    while ($row = $this->dbManager->fetchArray($res))
    {
      $licenseRef = new LicenseRef($row['id'], $row['shortname'], $row['fullname']);
      if ($this->dbManager->booleanFromDb($row['removed']))
      {
        $removed[] = $licenseRef;
      }
      else
      {
        $added[] = $licenseRef;
      }
    }
    $this->dbManager->freeResult($res);
    return array($added,$removed);
  }

  /**
   * @param array $licenses
   * @param bool $removed
   * @param int $uploadTreeId
   * @param int $userid
   * @param string $comment
   * @param string $remark
   */
  public function insertMultipleClearingEvents($licenses, $removed, $uploadTreeId, $userid,$jobfk, $comment="", $remark="")
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

    $tbdColumnStatementName = __METHOD__ . ".d";
    $this->dbManager->prepare($tbdColumnStatementName,
        "delete from clearing_event where uploadtree_fk = $1 and rf_fk = $2 and type_fk = $3");

    while ($item = $this->dbManager->fetchArray($items))
    {
      $currentUploadTreeId = $item['uploadtree_pk'];
      foreach ($licenses as $license)
      {
        $res = $this->dbManager->execute($tbdColumnStatementName, array($currentUploadTreeId, $license, $type));
        $this->dbManager->freeResult($res);
        $aDecEvent =  array('uploadtree_fk'=>$currentUploadTreeId, 'user_fk'=>$userid,
            'rf_fk'=>$license, 'is_removed'=>$removed, 'job_fk' =>$jobfk,
            'type_fk'=>$type, 'comment'=>$comment, 'reportinfo'=>$remark);
        $this->dbManager->insertTableRow('clearing_event', $aDecEvent, $sqlLog=__METHOD__);
      }
    }
    $this->dbManager->freeResult($items);

    $this->dbManager->commit();
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @return array
   */
  public function getEditedLicenseShortNamesFullList(ItemTreeBounds $itemTreeBounds)
  {
    $licenseCandidates = $this->getFileClearingsFolder($itemTreeBounds);
    $licenses = $this->newestEditedLicenseSelector->extractGoodLicenses($licenseCandidates);
    return $licenses;
  }


  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @return string[]
   */
  public function getEditedLicenseShortnamesContained(ItemTreeBounds $itemTreeBounds)
  {
    $licenses = $this->getEditedLicenseShortNamesFullList($itemTreeBounds);

    return array_unique($licenses);
  }


  /**
   * @param int $userId
   * @param int $uploadTreeId
   * @return ClearingDecision|null
   */
  public function getRelevantClearingDecision($userId, $uploadTreeId)
  {
    $statementName = __METHOD__;
    $this->dbManager->prepare($statementName,
        "
SELECT
  CD.clearing_decision_pk AS id,
  CD.pfile_fk AS file_id,
  CD.uploadtree_fk AS uploadtree_id,
  EXTRACT(EPOCH FROM CD.date_added) AS date_added,
  CD.user_fk AS user_id,
  GU.group_fk,
  CD.decision_type AS type_id,
  CD.scope
FROM clearing_decision CD
INNER JOIN clearing_decision CD2 ON CD.pfile_fk = CD2.pfile_fk
INNER JOIN group_user_member GU ON CD.user_fk = GU.user_fk
INNER JOIN group_user_member GU2 ON GU.group_fk = GU2.group_fk
WHERE CD2.uploadtree_fk=$1
  AND (CD.scope=".DecisionScopes::REPO. " OR CD.uploadtree_fk = $1)
  AND GU2.user_fk=$2
  AND CD.decision_type!=$3
GROUP BY CD.clearing_decision_pk, CD.pfile_fk, CD.uploadtree_fk, CD.user_fk, GU.group_fk, CD.decision_type, CD.scope
ORDER BY CD.date_added DESC LIMIT 1
        ");
    $res = $this->dbManager->execute(
        $statementName,
        array($uploadTreeId, $userId, DecisionTypes::WIP)
    );

    $row = $this->dbManager->fetchArray($res);
    $result = null;
    if($row!==false && count($row)!=0)
    {
      list($added,$removed) = $this->getFileClearingLicenses($row['id']);
      $result = ClearingDecisionBuilder::create()
        ->setPositiveLicenses($added)
        ->setNegativeLicenses($removed)
        ->setClearingId($row['id'])
        ->setUploadTreeId($row['uploadtree_id'])
        ->setPfileId($row['file_id'])
        ->setUserId($row['user_id'])
        ->setType(intval($row['type_id']))
        ->setScope(intval($row['scope']))
        ->setDateAdded($row['date_added'])
        ->build();
    }
    $this->dbManager->freeResult($res);
    return $result;
  }

  /**
   * @param int $uploadTreeId
   * @param int $userId
   */
  public function removeWipClearingDecision($uploadTreeId, $userId){
    $sql = "DELETE FROM clearing_decision WHERE uploadtree_fk=$1 AND user_fk=$2 AND decision_type=$3";
    $this->dbManager->prepare($stmt=__METHOD__,$sql);
    $this->dbManager->freeResult($this->dbManager->execute($stmt,array($uploadTreeId, $userId, DecisionTypes::WIP)));
  }
  
  /**
   * @param $uploadTreeId
   * @param $userId
   * @param $decType
   * @param $isGlobal
   * @param LicenseRef[] $licenses
   * @param LicenseRef[] $removedLicenses
   */
  public function insertClearingDecision($uploadTreeId, $userId, $decType, $isGlobal, $licenses, $removedLicenses)
  {
    $this->dbManager->begin();

    $statementName = __METHOD__;
    $this->dbManager->prepare($statementName,
        "
insert into clearing_decision (
  uploadtree_fk,
  pfile_fk,
  user_fk,
  decision_type,
  scope
) VALUES (
  $1,
  (select pfile_fk from uploadtree where uploadtree_pk=$1),
  $2,
  $3,
  $4) RETURNING clearing_decision_pk
  ");
    $res = $this->dbManager->execute($statementName,
            array($uploadTreeId, $userId, $decType, $isGlobal ? DecisionScopes::REPO : DecisionScopes::ITEM ));
    $result = $this->dbManager->fetchArray($res);
    $clearingDecisionId = $result['clearing_decision_pk'];
    $this->dbManager->freeResult($res);

    $statementNameLicenseInsert = __METHOD__ . ".insertLicense";
    $this->dbManager->prepare($statementNameLicenseInsert, "INSERT INTO clearing_licenses (clearing_fk, rf_fk, removed) VALUES($1, $2, $3)");
    foreach ($licenses as $license) {
      $res = $this->dbManager->execute($statementNameLicenseInsert, array($clearingDecisionId, $license->getId(), $this->dbManager->booleanToDb(false)));
      $this->dbManager->freeResult($res);
    }
    foreach ($removedLicenses as $license) {
      $res = $this->dbManager->execute($statementNameLicenseInsert, array($clearingDecisionId, $license->getId(), $this->dbManager->booleanToDb(true)));
      $this->dbManager->freeResult($res);
    }

    $this->dbManager->commit();
  }

  /**
   * @param int $userId
   * @param int $uploadTreeId
   * @return ClearingEvent[] sorted by date_added
   */
  public function getRelevantClearingEvents($userId, $uploadTreeId)
  {
    $options = array('columns'=>array('rf_pk','rf_shortname','rf_fullname'),'candidatePrefix'=>'*');
    $licenseViewDao = new LicenseViewDao($_SESSION['GroupId'], $options, 'LR');
    $withCte = $licenseViewDao->asCTE();
    
    $statementName = __METHOD__;
    $this->dbManager->prepare($statementName,
        $sql = $withCte."
  SELECT
    LD.clearing_event_pk,
    LD.uploadtree_fk,
    EXTRACT(EPOCH FROM LD.date_added) as date_added,
    LD.user_fk,
    GU.group_fk,
    LD.rf_fk,
    LR.rf_shortname,
    LR.rf_fullname,
    LD.type_fk event_type,
    LD.is_removed,
    LD.reportinfo,
    LD.comment
  FROM clearing_event LD
  INNER JOIN LR ON LR.rf_pk = LD.rf_fk
  INNER JOIN group_user_member GU ON LD.user_fk = GU.user_fk
  INNER JOIN group_user_member GU2 ON GU.group_fk = GU2.group_fk
  WHERE LD.uploadtree_fk = $1
    AND GU2.user_fk=$2
  GROUP BY LD.clearing_event_pk, LD.uploadtree_fk, LD.date_added, LD.user_fk, LD.job_fk, 
      GU.group_fk,LD.rf_fk, LR.rf_shortname, LR.rf_fullname, LD.type_fk, LD.is_removed, LD.reportinfo, LD.comment
  ORDER BY LD.date_added ASC, LD.rf_fk ASC, LD.is_removed ASC
        ");
    $res = $this->dbManager->execute(
        $statementName,
        array($uploadTreeId, $userId)
    );
    $orderedEvents = array();
    while ($row = $this->dbManager->fetchArray($res)) {
      $row['is_removed'] = $this->dbManager->booleanFromDb($row['is_removed']);
      $licenseRef = new LicenseRef(intval($row['rf_fk']), $row['rf_shortname'], $row['rf_fullname']);
      $licenseDecisionEventBuilder = new ClearingEventBuilder();
      $licenseDecisionEventBuilder->setEventId($row['clearing_event_pk'])
                                  ->setUploadTreeId($row['uploadtree_fk'])
                                  ->setDateFromTimeStamp($row['date_added'])
                                  ->setUserId($row['user_fk'])
                                  ->setGroupId($row['group_fk'])
                                  ->setEventType($row['event_type'])
                                  ->setLicenseRef($licenseRef)
                                  ->setRemoved($row['is_removed'])
                                  ->setReportinfo($row['reportinfo'])
                                  ->setComment($row['comment']);

      $orderedEvents[] =$licenseDecisionEventBuilder->build();
    }

    $this->dbManager->freeResult($res);
    return $orderedEvents;
  }

  /**
   * @param $uploadTreeId
   * @param $userId
   * @param int $licenseId
   * @param $type
   */
  public function addClearing($uploadTreeId, $userId, $licenseId, $type)
  {
    $this->insertClearingEvent($uploadTreeId, $userId, $licenseId, $type, false);
  }

  /**
   * @param int $uploadTreeId
   * @param int $userId
   * @param int $licenseId
   * @param int $type
   */
  public function removeClearing($uploadTreeId, $userId, $licenseId, $type)
  {
    $this->insertClearingEvent($uploadTreeId, $userId, $licenseId, $type, true);
  }

  public function updateClearing($uploadTreeId, $userId, $licenseId, $what, $changeTo)
  {
    $this->dbManager->begin();

    $statementGetOldata = "SELECT * from clearing_event where uploadtree_fk=$1 and rf_fk=$2  order by clearing_event_pk desc limit 1";
    $statementName = __METHOD__.'getOld';
    $params = array($uploadTreeId, $licenseId); //, $this->dbManager->booleanToDb(true)
    $row = $this->dbManager->getSingleRow($statementGetOldata,$params,$statementName);

    if(!$row) {  //The license was not added as user decision yet -> we promote it here
      $type = ClearingEventTypes::USER;
      $this->addClearing($uploadTreeId, $userId, $licenseId, $type);
      $row['type_fk'] = $type;
      $row['comment'] = "";
      $row['reportinfo'] = "";
    }

    if($what=='Text') {
        $reportInfo=$changeTo;
        $comment =$row['comment'];
    }
    else {
      $reportInfo =$row['reportinfo'];
      $comment=$changeTo;

    }
    $this->insertClearingEvent($uploadTreeId, $userId, $licenseId, $row['type_fk'], null, $reportInfo, $comment);

    $this->dbManager->commit();

  }

  private function insertClearingEvent($uploadTreeId, $userId, $licenseId, $type, $isRemoved, $reportInfo = '', $comment = '')
  {
    $this->markDecisionAsWip($uploadTreeId, $userId);
    if($isRemoved!=null)
    {
      $insertIsRemoved = $this->dbManager->booleanToDb($isRemoved);
    }
    else
    {
      $insertIsRemoved = null;
    }
    $this->dbManager->insertTableRow('clearing_event', array(
        'uploadtree_fk'=>$uploadTreeId, 'user_fk'=>$userId, 'rf_fk'=>$licenseId, 'type_fk'=>$type,
        'is_removed'=>$insertIsRemoved, 'reportinfo'=>$reportInfo, 'comment'=>$comment));
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
    while ($row = $this->dbManager->fetchArray($res)) {
      $items[] = $row['uploadtree_fk'];
    }
    $this->dbManager->freeResult($res);

    return $items;
  }
  
  /**
   * @param ClearingDecision[] $decisions
   * @return LicenseRef[][]
   */
  public function extractGoodLicensesPerFileID($decisions){
    return $this->newestEditedLicenseSelector->extractGoodLicensesPerItem($decisions);
  }
  
  /**
   * @param LicenseRef[][] $editedLicensesArray
   * @return string[]
   */
  public function extractGoodLicenses($editedLicensesArray){
    return $this->newestEditedLicenseSelector->extractGoodLicenses($editedLicensesArray);
  }

  /**
   * @param $licenseId
   * @param $licenseShortName
   * @param $licenseName
   * @param $licenseIsRemoved
   * @param $removed
   * @param $added
   */
  protected function appendToRemovedAdded($licenseId, $licenseShortName, $licenseName, $licenseIsRemoved, &$removed, &$added)
  {
    $licenseRef = new LicenseRef($licenseId, $licenseShortName, $licenseName);
    if ($this->dbManager->booleanFromDb($licenseIsRemoved))
    {
      $removed[] = $licenseRef;
    } else
    {
      $added[] = $licenseRef;
    }
  }

  /**
   * @param int $uploadTreeId
   * @param int $userId
   */
  public function markDecisionAsWip($uploadTreeId, $userId)
  {
    $statementName = __METHOD__;
    $this->dbManager->prepare($statementName,
        "INSERT INTO clearing_decision (uploadtree_fk,pfile_fk,user_fk,decision_type,scope) VALUES (
            $1, (select pfile_fk from uploadtree where uploadtree_pk=$1),  $2,  $3,  $4)");
    $res = $this->dbManager->execute($statementName,
            array($uploadTreeId, $userId, DecisionTypes::WIP, DecisionScopes::ITEM ));
    $this->dbManager->freeResult($res);
  }

  public function isDecisionWip($uploadTreeId, $userId)
  {
    $sql = "SELECT decision_type FROM clearing_decision WHERE uploadtree_fk=$1 AND user_fk=$2 ORDER BY date_added DESC LIMIT 1";
    $latestDec = $this->dbManager->getSingleRow($sql, array($uploadTreeId, $userId), $sqlLog=__METHOD__);
    if($latestDec===false)
    {
      return false;
    }
    return ($latestDec['decision_type'] == DecisionTypes::WIP);
  }

  public function getBulkHistory(ItemTreeBounds $itemTreeBound, $onlyTried = true)
  {
    $uploadTreeTableName = $itemTreeBound->getUploadTreeTableName();
    $itemId = $itemTreeBound->getItemId();
    $uploadId = $itemTreeBound->getUploadId();
    $left = $itemTreeBound->getLeft();

    $params = array($uploadId, $itemId, $left);
    $stmt = __METHOD__.".".$uploadTreeTableName;

    $triedExpr = "$3 between ut2.lft and ut2.rgt";
    $triedFilter = "";
    if ($onlyTried)
    {
      $triedFilter = "and ".$triedExpr;
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
            SELECT distinct on(lrb_pk) ce_pk, rf_text as text, rf_shortname as lic, removing, tried, matched
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
}
