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
use Fossology\Lib\Data\Clearing\ClearingLicense;
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\ClearingDecisionBuilder;
use Fossology\Lib\Data\DecisionTypes;
use Fossology\Lib\Data\LicenseDecision;
use Fossology\Lib\Data\LicenseDecision\LicenseDecisionEvent;
use Fossology\Lib\Data\LicenseDecision\LicenseDecisionEventBuilder;
use Fossology\Lib\Data\LicenseDecision\LicenseDecisionResult;
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
  public $newestEditedLicenseSelector;
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
   * \brief get all the licenses for a single file or uploadtree
   *
   * @param $uploadTreeId
   * @return ClearingDecision[]
   */
  function getFileClearings($uploadTreeId)
  {
    $itemTreeBounds = $this->uploadDao->getFileTreeBounds($uploadTreeId);
    return $this->getFileClearingsFolder($itemTreeBounds);
  }
  
  /**
   * \brief get all the licenses for a single file or uploadtree
   *
   * @param ItemTreeBounds $itemTreeBounds
   * @return ClearingDecision[]
   */
  function getFileClearingsFolder(ItemTreeBounds $itemTreeBounds)
  {
    //The first join to uploadtree is to find out if this is the same upload <= this needs to be uploadtree
    //The second gives all the clearing decisions which correspond to a filehash in the folder <= we can use the special upload table
    $uploadTreeTable = $itemTreeBounds->getUploadTreeTableName();

    $sql_upload="";
    if ('uploadtree_a' == $uploadTreeTable) {
      $sql_upload = "ut.upload_fk=$1  and ";
    }

    $secondJoin = " INNER JOIN ".$uploadTreeTable." ut ON CD.pfile_fk = ut.pfile_fk
           WHERE $sql_upload ut.lft BETWEEN $2 and $3 ";

    $statementName = __METHOD__.$uploadTreeTable;

    $sql="SELECT
           CD.clearing_decision_pk AS id,
           CD.uploadtree_fk AS uploadtree_id,
           CD.pfile_fk AS pfile_id,
           users.user_name AS user_name,
           CD.user_fk AS user_id,
           CD.type_fk AS type_id,
           CD.is_global AS is_global,
           EXTRACT(EPOCH FROM CD.date_added) AS date_added,
           ut2.upload_fk = $1 AS same_upload,
           ut2.upload_fk = $1 and ut2.lft BETWEEN $2 and $3 AS is_local
         FROM clearing_decision CD
         LEFT JOIN users ON CD.user_fk=users.user_pk
         INNER JOIN uploadtree ut2 ON CD.uploadtree_fk = ut2.uploadtree_pk
         $secondJoin
         GROUP BY id,uploadtree_id,pfile_id,user_name,user_id,type_id,is_global,date_added,same_upload,is_local
         ORDER by CD.pfile_fk, CD.clearing_decision_pk desc";

    $this->dbManager->prepare($statementName, $sql);

    // the array needs to be sorted with the newest clearingDecision first.
    $result = $this->dbManager->execute($statementName, array($itemTreeBounds->getUploadId(), $itemTreeBounds->getLeft(), $itemTreeBounds->getRight()));
    $clearingsWithLicensesArray = array();

    while ($row = $this->dbManager->fetchArray($result))
    {
      $clearingDec = ClearingDecisionBuilder::create()
          ->setSameUpload($this->dbManager->booleanFromDb($row['same_upload']))
          ->setSameFolder($this->dbManager->booleanFromDb($row['is_local']))
          ->setLicenses($this->getFileClearingLicenses($row['id']))
          ->setClearingId($row['id'])
          ->setUploadTreeId($row['uploadtree_id'])
          ->setPfileId($row['pfile_id'])
          ->setUserName($row['user_name'])
          ->setUserId($row['user_id'])
          ->setType($row['type_id'])
          ->setScope($this->dbManager->booleanFromDb($row['is_global']) ? "global" : "upload")
          ->setDateAdded($row['date_added'])
          ->build();

      $clearingsWithLicensesArray[] = $clearingDec;
    }
    $this->dbManager->freeResult($result);
    return $clearingsWithLicensesArray;
  }

  /**
   * @param $clearingId
   * @return ClearingLicense[]
   */
  public function getFileClearingLicenses($clearingId)
  {
    $clearingLicenses = array();
    $statementN = __METHOD__;
    $this->dbManager->prepare($statementN,
        "select
               license_ref.rf_pk as id,
               license_ref.rf_shortname as shortname,
               license_ref.rf_fullname  as fullname,
               clearing_licenses.removed  as removed
           from clearing_licenses
           left join license_ref on clearing_licenses.rf_fk=license_ref.rf_pk
               where clearing_fk=$1");

    $res = $this->dbManager->execute($statementN, array($clearingId));

    while ($row = $this->dbManager->fetchArray($res))
    {
      $licenseRef = new LicenseRef($row['id'], $row['shortname'], $row['fullname']);
      $clearingLicenses[] = new ClearingLicense($licenseRef, $this->dbManager->booleanFromDb($row['removed']));
    }
    $this->dbManager->freeResult($res);
    return $clearingLicenses;
  }

  /**
   * @param array $licenses
   * @param bool $removed
   * @param int $uploadTreeId
   * @param int $userid
   * @param string $comment
   * @param string $remark
   * @throws \Exception
   */
  public function insertClearingDecisionTest($licenses, $removed, $uploadTreeId, $userid, $comment="", $remark="")
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

    $tbdColumnStatementName = __METHOD__ . "_TBD_column";
    $tbdDecisionTypeValue = $this->dbManager->getSingleRow("select type_pk from license_decision_type where meaning = $1",
            array(LicenseDecision::USER_DECISION), $tbdColumnStatementName);
    $type = $tbdDecisionTypeValue['type_pk'];

    $tbdColumnStatementName = __METHOD__ . ".d";
    $this->dbManager->prepare($tbdColumnStatementName,
        "delete from license_decision_event where uploadtree_fk = $1 and rf_fk = $2 and type_fk = $3");

    while ($item = $this->dbManager->fetchArray($items))
    {
      $currentUploadTreeId = $item['uploadtree_pk'];
      $pfileId = $item['pfile_fk'];

      foreach ($licenses as $license)
      {
        $res = $this->dbManager->execute($tbdColumnStatementName, array($currentUploadTreeId, $license, $type));
        $this->dbManager->freeResult($res);
        $aDecEvent =  array('uploadtree_fk'=>$currentUploadTreeId,'pfile_fk'=>$pfileId, 'user_fk'=>$userid,
            'rf_fk'=>$license, 'is_removed'=>$removed,'is_global'=>false,
            'type_fk'=>$type, 'comment'=>$comment, 'reportinfo'=>$remark);
        $this->dbManager->insertTableRow('license_decision_event', $aDecEvent, $sqlLog=__METHOD__);
      }
    }
    $this->dbManager->freeResult($items);

    $this->dbManager->commit();
  }

  /**
   * @param int $licenseId
   * @param $uploadTreeId
   * @param $userid
   */
  public function commentClearingDecision($licenseId, $uploadTreeId, $userid)
  {
    // TODO comment license item pair
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @return ClearingDecision[]
   */
  public function getGoodClearingDecPerFileId(ItemTreeBounds $itemTreeBounds)
  {
    $licenseCandidates = $this->getFileClearingsFolder($itemTreeBounds);
    $filteredLicenses = $this->newestEditedLicenseSelector->extractGoodClearingDecisionsPerFileID($licenseCandidates);
    return $filteredLicenses;
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
   * @param ItemTreeBounds $itemTreeBounds
   * @return array
   */
  public function getEditedLicenseShortnamesContainedWithCount(ItemTreeBounds $itemTreeBounds, $licenses=null)
  {
    if(empty($licenses))
      $licenses = $this->getEditedLicenseShortNamesFullList($itemTreeBounds);
    $uniqueLicenses = array_unique($licenses);
    $licensesWithCount = array();

    foreach ($uniqueLicenses as $licN)
    {
      $count = 0;
      foreach ($licenses as $candidate)
      {
        if ($licN == $candidate)
        {
          $count++;
        }
      }
      $licensesWithCount[$licN] = $count;
    }

    return $licensesWithCount;
  }

  /**
   * @param int $userId
   * @param int $uploadTreeId
   * @return ClearingDecision|null
   * @throws \Fossology\Lib\Exception
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
  CD.type_fk AS type_id,
  CD.is_global
FROM clearing_decision CD
INNER JOIN clearing_decision CD2 ON CD.pfile_fk = CD2.pfile_fk
INNER JOIN group_user_member GU ON CD.user_fk = GU.user_fk
INNER JOIN group_user_member GU2 ON GU.group_fk = GU2.group_fk
WHERE
  CD2.uploadtree_fk=$1 AND
  (CD.is_global OR CD.uploadtree_fk = $1) AND
  GU2.user_fk=$2
GROUP BY CD.clearing_decision_pk, CD.pfile_fk, CD.uploadtree_fk, CD.user_fk, GU.group_fk, CD.type_fk, CD.is_global
ORDER BY CD.date_added DESC LIMIT 1
        ");
    $res = $this->dbManager->execute(
        $statementName,
        array($uploadTreeId, $userId)
    );

    $row = $this->dbManager->fetchAll($res);
    $result = count($row) > 0 ?
        ClearingDecisionBuilder::create()
        ->setLicenses($this->getFileClearingLicenses($row['id']))
        ->setClearingId($row['id'])
        ->setUploadTreeId($row['uploadtree_id'])
        ->setPfileId($row['file_id'])
        ->setUserId($row['user_id'])
        ->setType($row['type_id'])
        ->setScope($this->dbManager->booleanFromDb($row['is_global']) ? "global" : "upload")
        ->setDateAdded($row['date_added'])
        ->build() :
        null;
    $this->dbManager->freeResult($res);
    return $result;
  }

  /**
   * @param $uploadTreeId
   * @param $userId
   * @param $type
   * @param $isGlobal
   * @param LicenseDecisionResult[] $licenses
   * @param LicenseDecisionResult[] $removedLicenses
   */
  public function insertClearingDecision($uploadTreeId, $userId, $type, $isGlobal, $licenses, $removedLicenses)
  {
    $this->dbManager->begin();

    $statementName = __METHOD__;
    $this->dbManager->prepare($statementName,
        "
insert into clearing_decision (
  uploadtree_fk,
  pfile_fk,
  user_fk,
  type_fk,
  is_global
) VALUES (
  $1,
  (select pfile_fk from uploadtree where uploadtree_pk=$1),
  $2,
  $3,
  $4) RETURNING clearing_decision_pk
  ");
    $res = $this->dbManager->execute($statementName, array(
        $uploadTreeId, $userId, $type,
        $this->dbManager->booleanToDb($isGlobal)));
    $result = $this->dbManager->fetchArray($res);
    $clearingDecisionId = $result['clearing_decision_pk'];
    $this->dbManager->freeResult($res);

    $statementNameLicenseInsert = __METHOD__ . ".insertLicense";
    $this->dbManager->prepare($statementNameLicenseInsert, "INSERT INTO  clearing_licenses (clearing_fk, rf_fk, removed) VALUES($1, $2, $3)");
    foreach ($licenses as $license) {
      $res = $this->dbManager->execute($statementNameLicenseInsert, array($clearingDecisionId, $license->getLicenseId(), $this->dbManager->booleanToDb(false)));
      $this->dbManager->freeResult($res);
    }
    foreach ($removedLicenses as $license) {
      $res = $this->dbManager->execute($statementNameLicenseInsert, array($clearingDecisionId, $license->getLicenseId(), $this->dbManager->booleanToDb(true)));
      $this->dbManager->freeResult($res);
    }

    $this->dbManager->commit();
  }

  /**
   * @param int $userId
   * @param int $uploadTreeId
   * @return LicenseDecisionEvent[]
   */
  public function getRelevantLicenseDecisionEvents($userId, $uploadTreeId)
  {
    $statementName = __METHOD__;
    $this->dbManager->prepare($statementName,
        $sql = "
  SELECT
    LD.license_decision_event_pk,
    LD.pfile_fk,
    LD.uploadtree_fk,
    EXTRACT(EPOCH FROM LD.date_added) as date_added,
    LD.user_fk,
    GU.group_fk,
    LDT.meaning AS event_type,
    LD.rf_fk,
    LR.rf_shortname,
    LR.rf_fullname,
    LD.is_global,
    LD.is_removed,
    LD.reportinfo,
    LD.comment
  FROM license_decision_event LD
  INNER JOIN license_decision_event LD2 ON LD.pfile_fk = LD2.pfile_fk
  INNER JOIN license_decision_type LDT ON LD.type_fk = LDT.type_pk
  INNER JOIN license_ref LR ON LR.rf_pk = LD.rf_fk
  INNER JOIN group_user_member GU ON LD.user_fk = GU.user_fk
  INNER JOIN group_user_member GU2 ON GU.group_fk = GU2.group_fk
  WHERE
    LD2.uploadtree_fk=$1 AND
    (LD.is_global OR LD.uploadtree_fk = $1) AND
    GU2.user_fk=$2
  GROUP BY LD.license_decision_event_pk, LD.pfile_fk, LD.uploadtree_fk, LD.date_added, LD.user_fk, LD.job_fk, 
      GU.group_fk, LDT.meaning, LD.rf_fk, LR.rf_shortname, LR.rf_fullname, LD.is_removed, LD.is_global, LD.reportinfo, LD.comment
  ORDER BY LD.date_added ASC, LD.rf_fk ASC, LD.is_removed ASC
        ");
    $res = $this->dbManager->execute(
        $statementName,
        array($uploadTreeId, $userId)
    );

    $events = array();

    while ($row = $this->dbManager->fetchArray($res)) {
      foreach (array('is_global', 'is_removed') as $columnName) {
        $row[$columnName] = $this->dbManager->booleanFromDb($row[$columnName]);
      }
      $licenseRef = new LicenseRef(intval($row['rf_fk']), $row['rf_shortname'], $row['rf_fullname']);
      $licenseDecisionEventBuilder = new LicenseDecisionEventBuilder();
      $licenseDecisionEventBuilder->setEventId($row['license_decision_event_pk'])
                                  ->setPfileId( $row['pfile_fk'])
                                  ->setUploadTreeId($row['uploadtree_fk'])
                                  ->setDateFromTimeStamp($row['date_added'])
                                  ->setUserId($row['user_fk'])
                                  ->setGroupId($row['group_fk'])
                                  ->setEventType($row['event_type'])
                                  ->setLicenseRef($licenseRef)
                                  ->setGlobal($row['is_global'])
                                  ->setRemoved($row['is_removed'])
                                  ->setReportinfo($row['reportinfo'])
                                  ->setComment($row['comment']);

      $events[] =$licenseDecisionEventBuilder->build();
    }

    $this->dbManager->freeResult($res);
    return $events;
  }

  
  /**
   * @return LicenseDecisionEvent[][]
   */
  public function getCurrentLicenseDecisions($userId, $itemId)
  {
    /** @var LicenseDecisionEvent[] $events */
    $events = $this->getRelevantLicenseDecisionEvents($userId, $itemId);
    $latestLicDec = array();
    foreach ($events as $event)
    {
      if ($event->getEventType() == DecisionTypes::TO_BE_DISCUSSED)
      {
        continue;
      }
      $licenseShortName = $event->getLicenseShortName();
      $latestLicDec[$licenseShortName] = $event;
    }

    $addedLicenses = array();
    $removedLicenses = array();
    foreach ($latestLicDec as $licenseShortName=>$event)
    {
      if ($event->isRemoved())
      {
        $removedLicenses[$licenseShortName] = $event;
      }
      else
      {
        $addedLicenses[$licenseShortName] = $event;
      }
    }

    return array($addedLicenses, $removedLicenses);
  }

  /**
   * @param $uploadTreeId
   * @param $userId
   * @param int $licenseId
   * @param $type
   * @param $isGlobal
   */
  public function addLicenseDecision($uploadTreeId, $userId, $licenseId, $type, $isGlobal)
  {
    $this->insertLicenseDecisionEvent($uploadTreeId, $userId, $licenseId, $type, $isGlobal, false);
  }

  /**
   * @param $uploadTreeId
   * @param $userId
   * @param int $licenseId
   * @param $type
   * @param $isGlobal
   */
  public function removeLicenseDecision($uploadTreeId, $userId, $licenseId, $type, $isGlobal)
  {
    $this->insertLicenseDecisionEvent($uploadTreeId, $userId, $licenseId, $type, $isGlobal, true);
  }

  public function updateLicenseDecision($uploadTreeId, $userId, $licenseId, $what, $changeTo)
  {
    $this->dbManager->begin();

    $statementGetOldata = "SELECT * from license_decision_event where uploadtree_fk=$1 and rf_fk=$2  order by license_decision_event_pk desc limit 1 ";
    $statementName = __METHOD__.'getOld';
    $params = array($uploadTreeId, $licenseId); //, $this->dbManager->booleanToDb(true)
    $row = $this->dbManager->getSingleRow($statementGetOldata,$params,$statementName);

    if(!$row) {  //The license was not added as user decision yet -> we promote it here
      $type=1;
      $isGlobal = false;
      $this->addLicenseDecision($uploadTreeId, $userId, $licenseId, $type, $isGlobal);
      $row['type_fk']=$type;
      $row['is_global']=$isGlobal;
      $row['comment']="";
      $row['reportinfo']="";
    }

    if($what=='Text') {
        $reportInfo=$changeTo;
        $comment =$row['comment'];
    }
    else {
      $reportInfo =$row['reportinfo'];
      $comment=$changeTo;

    }
    $this->insertLicenseDecisionEvent($uploadTreeId, $userId, $licenseId, $row['type_fk'], $row['is_global'], null, $reportInfo , $comment);

    $this->dbManager->commit();

  }

  public function insertLicenseDecisionEvent($uploadTreeId, $userId, $licenseId, $type, $isGlobal, $isRemoved, $reportInfo = '', $comment = '')
  {
    $insertisGlobal =$this->dbManager->booleanToDb($isGlobal);
    if($isRemoved!=null)
    {
      $insertIsRemoved = $this->dbManager->booleanToDb($isRemoved);
    }
    else
    {
      $insertIsRemoved =null;
    }
    $statementName = __METHOD__;
    $this->dbManager->prepare($statementName,
        "
insert into license_decision_event (
  uploadtree_fk,
  pfile_fk,
  user_fk,
  rf_fk,
  type_fk,
  is_global,
  is_removed,
  reportinfo,
  comment
) VALUES (
  $1,
  (select pfile_fk from uploadtree where uploadtree_pk=$1),
  $2,
  $3,
  $4,
  $5,
  $6,
  $7,
  $8)");
    $res = $this->dbManager->execute($statementName, array(
        $uploadTreeId, $userId, $licenseId, $type,
        $insertisGlobal,
        $insertIsRemoved, $reportInfo, $comment));
    $this->dbManager->freeResult($res);
  }

  public function getItemsChangedBy($jobId)
  {
    $statementName = __METHOD__;
    $this->dbManager->prepare(
      $statementName,
      "SELECT uploadtree_fk FROM license_decision_event WHERE job_fk = $1"
    );

    $res = $this->dbManager->execute($statementName, array($jobId));

    $items = array();
    while ($row = $this->dbManager->fetchArray($res)) {
      $items[] = $row['uploadtree_fk'];
    }
    $this->dbManager->freeResult($res);

    return $items;
  }

}
