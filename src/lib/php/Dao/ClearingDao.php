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
use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\ClearingDecisionBuilder;
use Fossology\Lib\Data\DatabaseEnum;
use Fossology\Lib\Data\FileTreeBounds;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\Object;
use Monolog\Logger;

class ClearingDao extends Object
{
  /**
   * @var DbManager
   */
  private $dbManager;

  /**
   * @var Logger
   */
  private $logger;

  /**
   * @var NewestEditedLicenseSelector
   */
  public $newestEditedLicenseSelector;

  /**
   * @var UploadDao
   */
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
    $fileTreeBounds = $this->uploadDao->getFileTreeBounds($uploadTreeId);
    return $this->getFileClearingsFolder($fileTreeBounds);
  }

  function booleanFromPG($in)
  {
    return $in == 't';
  }


  /**
   * \brief get all the licenses for a single file or uploadtree
   *
   * @param FileTreeBounds $fileTreeBounds
   * @return ClearingDecision[]
   */
  function getFileClearingsFolder(FileTreeBounds $fileTreeBounds)
  {
    $statementName = __METHOD__;

    $this->dbManager->prepare($statementName,
        "SELECT
           CD.clearing_decision_pk AS id,
           CD.uploadtree_fk AS uploadtree_id,
           CD.pfile_fk AS pfile_id,
           users.user_name AS user_name,
           CD.user_fk AS user_id,
           CD_types.meaning AS type,
           CD_scopes.meaning AS scope,
           CD.comment AS comment,
           CD.reportinfo AS reportinfo,
           CD.date_added AS date_added,
           ut2.upload_fk = $1 AS same_upload,
           ut2.upload_fk = $1 and ut2.lft BETWEEN $2 and $3 AS is_local
         FROM clearing_decision CD
         LEFT JOIN clearing_decision_types CD_types ON CD.type_fk=CD_types.type_pk
         LEFT JOIN clearing_decision_scopes CD_scopes ON CD.scope_fk=CD_scopes.scope_pk
         LEFT JOIN users ON CD.user_fk=users.user_pk
         INNER JOIN uploadtree ut2 ON CD.uploadtree_fk = ut2.uploadtree_pk
         INNER JOIN uploadtree ut ON CD.pfile_fk = ut.pfile_fk
           WHERE ut.upload_fk=$1 and ut.lft BETWEEN $2 and $3
         ORDER by CD.pfile_fk, CD.clearing_decision_pk desc");
// the array needs to be sorted with the newest clearingDecision first.
    $result = $this->dbManager->execute($statementName, array($fileTreeBounds->getUploadId(), $fileTreeBounds->getLeft(), $fileTreeBounds->getRight()));
    $clearingsWithLicensesArray = array();

    while ($row = pg_fetch_assoc($result))
    {
      $clearingDec = ClearingDecisionBuilder::create()
          ->setSameUpload($this->booleanFromPG($row['same_upload']))
          ->setSameFolder($this->booleanFromPG($row['is_local']))
          ->setLicenses($this->getFileClearingLicenses($row['id']))
          ->setClearingId($row['id'])
          ->setUploadTreeId($row['uploadtree_id'])
          ->setPfileId($row['pfile_id'])
          ->setUserName($row['user_name'])
          ->setUserId($row['user_id'])
          ->setType($row['type'])
          ->setComment($row['comment'])
          ->setReportinfo($row['reportinfo'])
          ->setScope($row['scope'])
          ->setDateAdded($row['date_added'])
          ->build();

      $clearingsWithLicensesArray[] = $clearingDec;
    }

    pg_free_result($result);
    return $clearingsWithLicensesArray;
  }

  /**
   * @param $id
   * @return LicenseRef[]
   */
  public function getFileClearingLicenses($id)
  {
    $licenses = array();
    $statementN = __METHOD__;
    $this->dbManager->prepare($statementN,
        "select
               license_ref.rf_pk as rf,
               license_ref.rf_shortname as shortname,
               license_ref.rf_fullname  as fullname,
               clearing_licenses.removed  as removed
           from clearing_licenses
           left join license_ref on clearing_licenses.rf_fk=license_ref.rf_pk
               where clearing_fk=$1");

    $res = $this->dbManager->execute($statementN, array($id));

    while ($rw = $this->dbManager->fetchArray($res))
    {
      $licenses[] = new LicenseRef($rw['rf'], $rw['shortname'], $rw['fullname'], $rw ['removed'] == 't');
    }
    pg_free_result($res);
    return $licenses;
  }

  /**
   * @return DatabaseEnum[]
   */
  public function getClearingTypes()
  {
    $clearingTypes = array();
    $statementN = __METHOD__;

    $this->dbManager->prepare($statementN, "select * from clearing_decision_types");
    $res = $this->dbManager->execute($statementN);
    while ($rw = pg_fetch_assoc($res))
    {
      $clearingTypes[] = new DatabaseEnum($rw['type_pk'], $rw['meaning']);
    }
    pg_free_result($res);
    return $clearingTypes;
  }


  /**
   * @return array
   */
  public function getClearingDecisionTypeMap($selectableOnly = false)
  {
    $map = $this->dbManager->createMap('clearing_decision_types', 'type_pk', 'meaning');
    if ($selectableOnly)
    {
      $map = array(1 => $map[1], 2 => $map[2]);
    }
    return $map;
  }

  /**
   * @return DatabaseEnum[]
   */
  public function getClearingScopes()
  {
    $clearingScopes = array();
    $statementN = __METHOD__;

    $this->dbManager->prepare($statementN, "select * from clearing_decision_scopes");
    $res = $this->dbManager->execute($statementN);

    while ($rw = pg_fetch_assoc($res))
    {
      $clearingScopes[] = new DatabaseEnum($rw['scope_pk'], $rw['meaning']);
    }

    return $clearingScopes;
  }

  /**
   * @return array
   */
  public function getLicenseDecisionScopeMap()
  {
    return $this->dbManager->createMap('license_decision_scopes', 'scope_pk', 'meaning');
  }

  /**
   * @param $licenseId
   * @param $removed
   * @param $uploadTreeId
   * @param $userid
   * @param $type
   * @param $scope
   * @param $comment
   * @param $remark
   * @internal param array $licenses
   */
  public function insertClearingDecision($licenseId, $removed, $uploadTreeId, $userid, $type, $scope, $comment, $remark)
  {
    $this->dbManager->begin();

    $statementName = __METHOD__ . ".s";
    $this->dbManager->prepare($statementName,
        "with thisItem AS (select * from uploadtree where uploadtree_pk = $1)
         select uploadtree.* from uploadtree, thisItem where uploadtree.lft BETWEEN thisItem.lft AND thisItem.rgt AND ((uploadtree.ufile_mode & (3<<28))=0) AND uploadtree.pfile_fk != 0",
        array($uploadTreeId),
        $statementName);
    $items = $this->dbManager->execute($statementName, array($uploadTreeId));

    while ($item = $this->dbManager->fetchArray($items))
    {
      $currentUploadTreeId = $item['uploadtree_pk'];
      $pfileId = $item['pfile_fk'];

      $tbdColumnStatementName = __METHOD__ . "_TBD_column";
      $tbdDecisionTypeValue = $this->dbManager->getSingleRow("select type_pk from clearing_decision_types where meaning = $1", array(ClearingDecision::TO_BE_DISCUSSED), $tbdColumnStatementName);

      $tbdColumnStatementName = __METHOD__ . ".d";
      $this->dbManager->prepare($tbdColumnStatementName,
          "delete from clearing_decision_events where uploadtree_fk = $1 and rf_fk = $2 and type_fk = $3");
      $res = $this->dbManager->execute($tbdColumnStatementName, array($currentUploadTreeId, $licenseId, $tbdDecisionTypeValue));
      $this->dbManager->freeResult($res);

      $statementName = __METHOD__;
      $this->dbManager->prepare($statementName,
          "insert into clearing_decision_events (uploadtree_fk,pfile_fk,user_fk, rf_fk, removed, type_fk,scope_fk,comment,reportinfo) VALUES ($1,$2,$3,$4,$5,$6,$7, $8, $9) RETURNING clearing_decision_events_pk");
      $res = $this->dbManager->execute($statementName, array($currentUploadTreeId, $pfileId, $licenseId, $removed, $userid, $type, $scope, $comment, $remark));
      $this->dbManager->freeResult($res);
    }
    $this->dbManager->freeResult($items);

    $this->dbManager->commit();

  }


  /**
   * @param int $licenseId
   * @param $uploadTreeId
   * @param $userid
   */
  public function deleteClearingDecision($licenseId, $uploadTreeId, $userid)
  {
    //$tbd = array_search(ClearingDecision::TO_BE_DETERMINED, $this->getClearingTypeMap());
    // TODO delete license
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
   * @param FileTreeBounds $fileTreeBounds
   * @return ClearingDecision[]
   */
  public function getGoodClearingDecPerFileId(FileTreeBounds $fileTreeBounds)
  {
    $licenseCandidates = $this->getFileClearingsFolder($fileTreeBounds);
    $filteredLicenses = $this->newestEditedLicenseSelector->extractGoodClearingDecisionsPerFileID($licenseCandidates);
    return $filteredLicenses;
  }

  /**
   * @param FileTreeBounds $fileTreeBounds
   * @return array
   */
  public function getEditedLicenseShortNamesFullList(FileTreeBounds $fileTreeBounds)
  {

    $licenseCandidates = $this->getFileClearingsFolder($fileTreeBounds);
    $licenses = $this->newestEditedLicenseSelector->extractGoodLicenses($licenseCandidates);
    return $licenses;
  }


  /**
   * @param FileTreeBounds $fileTreeBounds
   * @return string[]
   */
  public function getEditedLicenseShortnamesContained(FileTreeBounds $fileTreeBounds)
  {
    $licenses = $this->getEditedLicenseShortNamesFullList($fileTreeBounds);

    return array_unique($licenses);
  }

  /**
   * @param FileTreeBounds $fileTreeBounds
   * @return array
   */
  public function getEditedLicenseShortnamesContainedWithCount(FileTreeBounds $fileTreeBounds)
  {
    $licenses = $this->getEditedLicenseShortNamesFullList($fileTreeBounds);
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

  public function getRelevantClearingDecisionEvents($userId, $uploadTreeId)
  {
    $statementName = __METHOD__;
    $this->dbManager->prepare($statementName,
        "
SELECT
  CD.pfile_fk,
  CD.uploadtree_fk,
  CD.date_added,
  CD.user_fk,
  GU.group_fk,
  CDT.meaning AS type,
  CD.rf_fk,
  LR.rf_shortname,
  CD.is_global,
  CD.is_removed
FROM clearing_decision_events CD
INNER JOIN clearing_decision_types CDT ON CD.type_fk = CDT.type_pk
INNER JOIN group_user_member GU ON CD.user_fk = GU.user_fk
INNER JOIN group_user_member GU2 ON GU.group_fk = GU2.group_fk
WHERE
  CD.uploadtree_fk = $1 AND
  GU2.user_fk=$2
GROUP BY CD.clearing_decision_pk
ORDER BY CD.date_added ASC, CD.rf_fk ASC, CD.is_removed ASC
        ");
    $res = $this->dbManager->execute(
        $statementName,
        array($uploadTreeId, $userId)
    );
    $result = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    return $result;
  }

  public function getRelevantLicenseDecisionEvents($userId, $uploadTreeId)
  {
    $statementName = __METHOD__;
    $this->dbManager->prepare($statementName,
        "
SELECT
  LD.pfile_fk,
  LD.uploadtree_fk,
  LD.date_added,
  LD.user_fk,
  GU.group_fk,
  LDT.meaning AS type,
  LD.rf_fk,
  LR.rf_shortname,
  LD.is_global,
  LD.is_removed
FROM license_decision_events LD
INNER JOIN license_decision_events LD2 ON LD.pfile_fk = LD2.pfile_fk
INNER JOIN license_decision_types LDT ON LD.type_fk = LDT.type_pk
INNER JOIN license_ref LR ON LR.rf_pk = LD.rf_fk
INNER JOIN group_user_member GU ON LD.user_fk = GU.user_fk
INNER JOIN group_user_member GU2 ON GU.group_fk = GU2.group_fk
WHERE
  LD2.uploadtree_fk=$1 AND
  (LD.is_global OR LD.uploadtree_fk = $1) AND
  GU2.user_fk=$2
GROUP BY LD.license_decision_pk
ORDER BY LD.date_added ASC, LD.rf_fk ASC, LD.is_removed ASC
        ");
    $res = $this->dbManager->execute(
        $statementName,
        array($uploadTreeId, $userId)
    );
    $result = $this->dbManager->fetchAll($res);
    $this->dbManager->freeResult($res);
    return $result;
  }


  public function getCurrentLicenseDecisions(FileTreeBounds $fileTreeBounds)
  {

  }

  public function getCurrentLicenseDecision($userId, $uploadId)
  {
    $events = $this->getRelevantLicenseDecisionEvents($userId, $uploadId);

    $licenseDecision = array();

    foreach ($events as $event)
    {
      if ($event[5] != ClearingDecision::TO_BE_DISCUSSED)
      {
        $licenseId = $event[6];
        $licenseShortName = $event[7];
        $isRemoved = $event[9] != 0;

        if ($isRemoved)
        {
          unset($licenseDecision[$licenseShortName]);
        } else
        {
          $licenseDecision[$licenseShortName] = $licenseId;
        }
      }
    }

    return $licenseDecision;
  }
}
