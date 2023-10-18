<?php
/*
 SPDX-FileCopyrightText: © 2014-2018 Siemens AG
 Author: Andreas Würl

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\Dao;

use Fossology\Lib\BusinessRules\LicenseMap;
use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\License;
use Fossology\Lib\Data\LicenseMatch;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Proxy\LicenseViewProxy;
use Fossology\Lib\Util\StringOperation;
use Monolog\Logger;

class LicenseDao
{
  const NO_LICENSE_FOUND = 'No_license_found';
  const VOID_LICENSE = 'Void';

  /** @var DbManager */
  private $dbManager;
  /** @var Logger */
  private $logger;
  /** @var string */
  private $candidatePrefix = '*';

  function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
    $this->logger = new Logger(self::class);
  }

  /**
   * \brief get all the licenses for a single file or uploadtree
   *
   * @param ItemTreeBounds $itemTreeBounds
   * @param int
   * @return LicenseMatch[]
   */
  function getAgentFileLicenseMatches(ItemTreeBounds $itemTreeBounds, $usageId=LicenseMap::TRIVIAL)
  {
    $uploadTreeTableName = $itemTreeBounds->getUploadTreeTableName();
    $statementName = __METHOD__ . ".$uploadTreeTableName.$usageId";
    $params = array($itemTreeBounds->getUploadId(), $itemTreeBounds->getLeft(), $itemTreeBounds->getRight());
    if ($usageId==LicenseMap::TRIVIAL) {
      $licenseJoin = "license_ref mlr ON license_file.rf_fk = mlr.rf_pk";
    } else {
      $params[] = $usageId;
      $licenseMapCte = LicenseMap::getMappedLicenseRefView('$4');
      $licenseJoin = "($licenseMapCte) AS mlr ON license_file.rf_fk = mlr.rf_origin";
    }

    $this->dbManager->prepare($statementName,
        "SELECT   LFR.rf_shortname AS license_shortname,
                  LFR.rf_spdx_id AS spdx_id,
                  LFR.rf_fullname AS license_fullname,
                  LFR.rf_pk AS license_id,
                  LFR.fl_pk AS license_file_id,
                  LFR.pfile_fk as file_id,
                  LFR.rf_match_pct AS percent_match,
                  AG.agent_name AS agent_name,
                  AG.agent_pk AS agent_id,
                  AG.agent_rev AS agent_revision
          FROM ( SELECT mlr.rf_fullname, mlr.rf_shortname, mlr.rf_spdx_id, mlr.rf_pk, license_file.fl_pk, license_file.agent_fk, license_file.pfile_fk, license_file.rf_match_pct
               FROM license_file JOIN $licenseJoin) as LFR
          INNER JOIN $uploadTreeTableName as UT ON UT.pfile_fk = LFR.pfile_fk
          INNER JOIN agent as AG ON AG.agent_pk = LFR.agent_fk
          WHERE AG.agent_enabled='true' and
           UT.upload_fk=$1 AND UT.lft BETWEEN $2 and $3
          ORDER BY license_shortname ASC, percent_match DESC");
    $result = $this->dbManager->execute($statementName, $params);
    $matches = array();
    while ($row = $this->dbManager->fetchArray($result)) {
      $licenseRef = new LicenseRef(intval($row['license_id']), $row['license_shortname'], $row['license_fullname'], $row['spdx_id']);
      $agentRef = new AgentRef(intval($row['agent_id']), $row['agent_name'], $row['agent_revision']);
      $matches[] = new LicenseMatch(intval($row['file_id']), $licenseRef, $agentRef, intval($row['license_file_id']), intval($row['percent_match']));
    }

    $this->dbManager->freeResult($result);
    return $matches;
  }


  /**
   * \brief get all the tried bulk recognitions for a single file or uploadtree (currently unused)
   *
   * @param ItemTreeBounds $itemTreeBounds
   * @return LicenseMatch[]
   */
  function getBulkFileLicenseMatches(ItemTreeBounds $itemTreeBounds)
  {
    $uploadTreeTableName = $itemTreeBounds->getUploadTreeTableName();
    $statementName = __METHOD__ . ".$uploadTreeTableName";

    $this->dbManager->prepare($statementName,
        "SELECT   LF.rf_shortname  AS license_shortname,
                  LF.rf_spdx_id AS spdx_id,
                  LF.rf_fullname AS license_fullname,
                  LF.rf_pk AS license_id,
                  LFB.lrb_pk AS license_file_id,
                  LSB.removing AS removing,
                  UT.pfile_fk as file_id
          FROM license_ref_bulk as LFB
          INNER JOIN license_set_bulk AS LSB ON LFB.lrb_pk = LSB.lrb_fk
          INNER JOIN license_ref as LF on LF.rf_pk = LSB.rf_fk
          INNER JOIN $uploadTreeTableName as UT ON UT.uploadtree_pk = LFB.uploadtree_fk
          WHERE UT.upload_fk=$1 AND UT.lft BETWEEN $2 and $3
          ORDER BY license_file_id ASC");

    $result = $this->dbManager->execute($statementName,
        array($itemTreeBounds->getUploadId(), $itemTreeBounds->getLeft(), $itemTreeBounds->getRight()));

    $matches = array();

    while ($row = $this->dbManager->fetchArray($result)) {
      $licenseRef = new LicenseRef($row['license_id'], $row['license_shortname'], $row['license_fullname'], $row['spdx_id']);
      if ($row['removing'] == 'f') {
        $agentID = 1;
        $agentName = "bulk addition";
      } else {
        $agentID = 2;
        $agentName = "bulk removal";
      }
      $agentRef = new AgentRef($agentID, $agentName, "empty");
      $matches[] = new LicenseMatch(intval($row['file_id']), $licenseRef, $agentRef, intval($row['license_file_id']));
    }

    $this->dbManager->freeResult($result);
    return $matches;
  }

  /**
   * @return LicenseRef[]
   */
  public function getLicenseRefs($search = null, $orderAscending = true)
  {
    if (isset($_SESSION) && array_key_exists('GroupId', $_SESSION)) {
      $rfTable = 'license_all';
      $options = array('columns' => array('rf_pk', 'rf_shortname', 'rf_fullname'), 'candidatePrefix' => $this->candidatePrefix);
      $licenseViewDao = new LicenseViewProxy($_SESSION['GroupId'], $options, $rfTable);
      $withCte = $licenseViewDao->asCTE();
    } else {
      $withCte = '';
      $rfTable = 'ONLY license_ref';
    }

    $searchCondition = $search ? "WHERE rf_shortname ilike $1" : "";

    $order = $orderAscending ? "ASC" : "DESC";
    $statementName = __METHOD__ . ($search ? ".search_" . $search : "") . ".order_$order";

    $this->dbManager->prepare($statementName,
        $sql = $withCte . " select rf_pk,rf_shortname,rf_spdx_id,rf_fullname from $rfTable $searchCondition order by LOWER(rf_shortname) $order");
    $result = $this->dbManager->execute($statementName, $search ? array('%' . strtolower($search) . '%') : array());
    $licenseRefs = array();
    while ($row = $this->dbManager->fetchArray($result)) {
      $licenseRefs[] = new LicenseRef(intval($row['rf_pk']), $row['rf_shortname'], $row['rf_fullname'], $row['rf_spdx_id']);
    }
    $this->dbManager->freeResult($result);
    return $licenseRefs;
  }


  /**
   * @return LicenseRef[]
   */
  public function getConclusionLicenseRefs($groupId, $search = null, $orderAscending = true, $exclude=array())
  {
    $rfTable = 'license_all';
    $options = array('columns' => array('rf_pk', 'rf_shortname', 'rf_fullname', 'rf_active', 'rf_spdx_id'),
                     'candidatePrefix' => $this->candidatePrefix);
    $licenseViewDao = new LicenseViewProxy($groupId, $options, $rfTable);
    $order = $orderAscending ? "ASC" : "DESC";
    $statementName = __METHOD__ . ".order_$order";
    $param = array();
    /* exclude license with parent, excluded child or selfexcluded */
    $sql = $licenseViewDao->asCTE()." SELECT rf_pk,rf_shortname,rf_spdx_id,rf_fullname FROM $rfTable
                  WHERE rf_active = 'yes' AND NOT EXISTS (select * from license_map WHERE rf_pk=rf_fk AND rf_fk!=rf_parent)";
    if ($search) {
      $param[] = '%' . $search . '%';
      $statementName .= '.search';
      $sql .=  " AND rf_shortname ilike $1";
    }
    if (count($exclude)>0) {
      // $param[] = $exclude;
      $tuple = implode(',', $exclude);
      $statementName .= '.exclude'.$tuple;
      $sql .=  " AND NOT EXISTS (select * from license_map WHERE rf_pk=rf_parent AND rf_fk IN ($tuple))
              AND rf_pk NOT IN($tuple)";
    }
    $this->dbManager->prepare($statementName, "$sql ORDER BY LOWER(rf_shortname) $order");
    $result = $this->dbManager->execute($statementName, $param);
    $licenseRefs = array();
    while ($row = $this->dbManager->fetchArray($result)) {
      $licenseRefs[] = new LicenseRef(intval($row['rf_pk']), $row['rf_shortname'], $row['rf_fullname'], $row['rf_spdx_id']);
    }
    $this->dbManager->freeResult($result);
    return $licenseRefs;
  }


  /**
   * @return array
   */
  public function getLicenseArray($groupId = null)
  {
    $statementName = __METHOD__;
    $rfTable = 'license_all';
    $options = array('columns' => array('rf_pk', 'rf_shortname', 'rf_fullname', 'rf_active'), 'candidatePrefix' => $this->candidatePrefix);
    if ($groupId === null) {
      $groupId = (isset($_SESSION) && array_key_exists('GroupId', $_SESSION)) ? $_SESSION['GroupId'] : 0;
    }
    $licenseViewDao = new LicenseViewProxy($groupId, $options, $rfTable);
    $withCte = $licenseViewDao->asCTE();

    $this->dbManager->prepare($statementName,
        $withCte . " select rf_pk id,rf_shortname shortname,rf_fullname fullname from $rfTable WHERE rf_active = 'yes' ORDER BY LOWER(rf_shortname)");
    $result = $this->dbManager->execute($statementName);
    $licenseRefs = $this->dbManager->fetchAll($result);
    $this->dbManager->freeResult($result);
    return $licenseRefs;
  }


  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param int $selectedAgentId
   * @param bool $includeSubfolders
   * @param array $nameRange
   * @return array
   */
  public function getLicenseIdPerPfileForAgentId(ItemTreeBounds $itemTreeBounds, $selectedAgentId, $includeSubfolders=true, $nameRange=array())
  {
    $uploadTreeTableName = $itemTreeBounds->getUploadTreeTableName();
    $statementName = __METHOD__ . '.' . $uploadTreeTableName;
    $param = array($selectedAgentId);

    if ($includeSubfolders) {
      $param[] = $itemTreeBounds->getLeft();
      $param[] = $itemTreeBounds->getRight();
      $condition = "lft BETWEEN $2 AND $3";
      $statementName .= ".subfolders";
      if (!empty($nameRange)) {
        $condition .= " AND ufile_name BETWEEN $4 and $5";
        $param[] = $nameRange[0];
        $param[] = $nameRange[1];
        $statementName .= ".nameRange";
      }
    } else {
      $param[] = $itemTreeBounds->getItemId();
      $condition = "realparent = $2";
    }

    if ('uploadtree_a' == $uploadTreeTableName) {
      $param[] = $itemTreeBounds->getUploadId();
      $condition .= " AND utree.upload_fk=$".count($param);
    }

    $sql = "SELECT utree.pfile_fk as pfile_id,
           license_ref.rf_pk as license_id,
           rf_match_pct as match_percentage,
           CAST($1 AS INT) AS agent_id,
           uploadtree_pk
         FROM license_file, license_ref, $uploadTreeTableName utree
         WHERE agent_fk = $1
           AND license_file.rf_fk = license_ref.rf_pk
           AND license_file.pfile_fk = utree.pfile_fk
           AND $condition
         ORDER BY match_percentage ASC";

    $this->dbManager->prepare($statementName, $sql);
    $result = $this->dbManager->execute($statementName, $param);
    $licensesPerFileId = array();
    while ($row = $this->dbManager->fetchArray($result)) {
      $licensesPerFileId[$row['pfile_id']][$row['license_id']] = $row;
    }

    $this->dbManager->freeResult($result);
    return $licensesPerFileId;
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param array(int) $selectedAgentIds
   * @param bool $includeSubFolders
   * @param String $excluding
   * @param bool $ignore ignore files without license
   * @param bool $includeTreeId Include tree id in the response array?
   * @return array
   */
  public function getLicensesPerFileNameForAgentId(ItemTreeBounds $itemTreeBounds,
                                                   $selectedAgentIds=null,
                                                   $includeSubfolders=true,
                                                   $excluding='',
                                                   $ignore=false,
                                                   &$clearingDecisionsForLicList = array(),
                                                   $includeTreeId=false)
  {
    $uploadTreeTableName = $itemTreeBounds->getUploadTreeTableName();
    $statementName = __METHOD__ . '.' . $uploadTreeTableName;
    $param = array();

    $condition = " (ufile_mode & (1<<28)) = 0";
    if ($includeSubfolders) {
      $param[] = $itemTreeBounds->getLeft();
      $param[] = $itemTreeBounds->getRight();
      $condition .= " AND lft BETWEEN $1 AND $2";
      $statementName .= ".subfolders";
    } else {
      $param[] = $itemTreeBounds->getItemId();
      $condition .= " AND realparent = $1";
    }

    if ('uploadtree_a' == $uploadTreeTableName) {
      $param[] = $itemTreeBounds->getUploadId();
      $condition .= " AND upload_fk=$".count($param);
    }

    $agentSelect = "";
    if ($selectedAgentIds !== null) {
      $statementName .= ".".count($selectedAgentIds)."agents";
      $agentSelect = "WHERE agent_fk IS NULL";
      foreach ($selectedAgentIds as $selectedAgentId) {
        $param[] = $selectedAgentId;
        $agentSelect .= " OR agent_fk = $".count($param);
      }
    }

    $sql = "
SELECT uploadtree_pk, ufile_name, lft, rgt, ufile_mode,
       rf_shortname, agent_fk
FROM (SELECT
        uploadtree_pk, ufile_name,
        lft, rgt, ufile_mode, pfile_fk
      FROM $uploadTreeTableName
      WHERE $condition) AS subselect1
LEFT JOIN (SELECT rf_shortname,pfile_fk,agent_fk
           FROM license_file, license_ref
           WHERE rf_fk = rf_pk) AS subselect2
  ON subselect1.pfile_fk = subselect2.pfile_fk
$agentSelect
ORDER BY lft asc
";

    $this->dbManager->prepare($statementName, $sql);
    $result = $this->dbManager->execute($statementName, $param);
    $licensesPerFileName = array();

    $row = $this->dbManager->fetchArray($result);
    $pathStack = array($row['ufile_name']);
    $rgtStack = array($row['rgt']);
    $lastLft = $row['lft'];
    $path = implode('/', $pathStack);
    $this->addToLicensesPerFileName($licensesPerFileName, $path, $row,
      $ignore, $clearingDecisionsForLicList, $includeTreeId);
    while ($row = $this->dbManager->fetchArray($result)) {
      if (!empty($excluding) && false!==strpos("/$row[ufile_name]/", $excluding)) {
        $lastLft = $row['rgt'] + 1;
        continue;
      }
      if ($row['lft'] < $lastLft) {
        continue;
      }

      $this->updateStackState($pathStack, $rgtStack, $lastLft, $row);
      $path = implode('/', $pathStack);
      $this->addToLicensesPerFileName($licensesPerFileName, $path, $row,
        $ignore, $clearingDecisionsForLicList, $includeTreeId);
    }
    $this->dbManager->freeResult($result);
    return array_reverse($licensesPerFileName);
  }

  private function updateStackState(&$pathStack, &$rgtStack, &$lastLft, $row)
  {
    if ($row['lft'] >= $lastLft) {
      while (count($rgtStack) > 0 && $row['lft'] > $rgtStack[count($rgtStack)-1]) {
        array_pop($pathStack);
        array_pop($rgtStack);
      }
      if ($row['lft'] > $lastLft) {
        $pathStack[] = $row['ufile_name'];
        $rgtStack[] = $row['rgt'];
        $lastLft = $row['lft'];
      }
    }
  }

  private function addToLicensesPerFileName(&$licensesPerFileName, $path, $row,
                                            $ignore,
                                            &$clearingDecisionsForLicList = array(),
                                            $includeTreeId=false)
  {
    if (($row['ufile_mode'] & (1 << 29)) == 0) {
      if ($row['rf_shortname']) {
        $licensesPerFileName[$path]['scanResults'][] = $row['rf_shortname'];
        if (array_key_exists($row['uploadtree_pk'], $clearingDecisionsForLicList)) {
          $licensesPerFileName[$path]['concludedResults'][] = $clearingDecisionsForLicList[$row['uploadtree_pk']];
        }
      }
    } else if (!$ignore) {
      $licensesPerFileName[$path] = false;
    }
    if ($includeTreeId) {
      $licensesPerFileName[$path]['uploadtree_pk'][] = $row['uploadtree_pk'];
    }
  }

  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param null|int|int[] $agentId
   * @return array
   */
  public function getLicenseHistogram(ItemTreeBounds $itemTreeBounds, $agentId=null)
  {
    $uploadTreeTableName = $itemTreeBounds->getUploadTreeTableName();
    $agentText = $agentId ? (is_array($agentId) ? implode(',', $agentId) : $agentId) : '-';
    $statementName = __METHOD__ . '.' . $uploadTreeTableName . ".$agentText";
    $param = array($itemTreeBounds->getUploadId(), $itemTreeBounds->getLeft(), $itemTreeBounds->getRight());
    $sql = "SELECT rf_shortname AS license_shortname, rf_spdx_id AS spdx_id, rf_pk, count(*) AS count, count(distinct pfile_ref.pfile_fk) as \"unique\"
         FROM ( SELECT license_ref.rf_shortname, license_ref.rf_spdx_id, license_ref.rf_pk, license_file.fl_pk, license_file.agent_fk, license_file.pfile_fk
             FROM license_file
             JOIN license_ref ON license_file.rf_fk = license_ref.rf_pk) AS pfile_ref
         RIGHT JOIN $uploadTreeTableName UT ON pfile_ref.pfile_fk = UT.pfile_fk";
    if (is_array($agentId)) {
      $sql .= ' AND agent_fk=ANY($4)';
      $param[] = '{' . implode(',', $agentId) . '}';
    } elseif (!empty($agentId)) {
      $sql .= ' AND agent_fk=$4';
      $param[] = $agentId;
    }
    $sql .= " WHERE (rf_shortname IS NULL OR rf_shortname NOT IN ('Void')) AND upload_fk=$1
             AND (UT.lft BETWEEN $2 AND $3) AND UT.ufile_mode&(3<<28)=0
          GROUP BY license_shortname, spdx_id, rf_pk";
    $this->dbManager->prepare($statementName, $sql);
    $result = $this->dbManager->execute($statementName, $param);
    $assocLicenseHist = array();
    while ($row = $this->dbManager->fetchArray($result)) {
      $shortname = empty($row['rf_pk']) ? self::NO_LICENSE_FOUND : $row['license_shortname'];
      $assocLicenseHist[$shortname] = array(
          'count' => intval($row['count']),
          'unique' => intval($row['unique']),
          'rf_pk' => intval($row['rf_pk']),
          'spdx_id' => LicenseRef::convertToSpdxId($shortname, $row['spdx_id'])
      );
    }
    $this->dbManager->freeResult($result);
    return $assocLicenseHist;
  }

  public function getLicenseShortnamesContained(ItemTreeBounds $itemTreeBounds, $latestSuccessfulAgentIds=null, $filterLicenses = array('VOID')) //'No_license_found',
  {
    $uploadTreeTableName = $itemTreeBounds->getUploadTreeTableName();

    $noLicenseFoundStmt = empty($filterLicenses) ? "" : " AND rf_shortname NOT IN ("
        . implode(", ", array_map(function ($name)
        {
          return "'" . $name . "'";
        }, $filterLicenses)) . ")";

    $statementName = __METHOD__ . '.' . $uploadTreeTableName;

    $agentFilter = '';
    if (is_array($latestSuccessfulAgentIds)) {
      $agentIdSet = "{" . implode(',', $latestSuccessfulAgentIds) . "}";
      $statementName .= ".$agentIdSet";
      $agentFilter = " AND agent_fk=ANY('$agentIdSet')";
    }

    $this->dbManager->prepare($statementName,
        "SELECT license_ref.rf_shortname
              FROM license_file JOIN license_ref ON license_file.rf_fk = license_ref.rf_pk
              INNER JOIN $uploadTreeTableName uploadTree ON uploadTree.pfile_fk=license_file.pfile_fk
              WHERE upload_fk=$1
                AND lft BETWEEN $2 AND $3
                $noLicenseFoundStmt $agentFilter
              GROUP BY rf_shortname
              ORDER BY rf_shortname ASC");
    $result = $this->dbManager->execute($statementName,
        array($itemTreeBounds->getUploadId(), $itemTreeBounds->getLeft(), $itemTreeBounds->getRight()));

    $licenses = array();
    while ($row = $this->dbManager->fetchArray($result)) {
      $licenses[] = $row['rf_shortname'];
    }
    $this->dbManager->freeResult($result);

    return $licenses;
  }

  /**
   * @param string $condition
   * @param array $param
   * @param $groupId
   * @return License|null
   */
  private function getLicenseByCondition($condition, $param, $groupId=null)
  {
    $extraCondition = "";
    $row = $this->dbManager->getSingleRow(
        "SELECT rf_pk, rf_shortname, rf_spdx_id, rf_fullname, rf_text, rf_url, rf_risk, rf_detector_type FROM ONLY license_ref WHERE $condition",
        $param, __METHOD__ . ".$condition.only");
    if (false === $row && isset($groupId)) {
      $userId = (isset($_SESSION) && array_key_exists('UserId', $_SESSION)) ? $_SESSION['UserId'] : 0;
      $statementName = __METHOD__ . ".$condition";
      if (!empty($userId)) {
        $param[] = $userId;
        $extraCondition = "AND group_fk IN (SELECT group_fk FROM group_user_member WHERE user_fk=$".count($param).")";
        $statementName .= ".userId";
      }
      if (is_int($groupId) && empty($userId)) {
        $param[] = $groupId;
        $extraCondition = "AND group_fk=$".count($param);
        $statementName .= ".groupId";
      }
      $row = $this->dbManager->getSingleRow(
        "SELECT rf_pk, rf_shortname, rf_spdx_id, rf_fullname, rf_text, rf_url, rf_risk, rf_detector_type FROM license_candidate WHERE $condition $extraCondition",
        $param, $statementName);
    }
    if (false === $row) {
      return null;
    }
    return new License(intval($row['rf_pk']), $row['rf_shortname'],
      $row['rf_fullname'], $row['rf_risk'], $row['rf_text'], $row['rf_url'],
      $row['rf_detector_type'], $row['rf_spdx_id']);
  }

  /**
   * @param string $licenseId
   * @param int|null $groupId
   * @return License|null
   */
  public function getLicenseById($licenseId, $groupId=null)
  {
    return $this->getLicenseByCondition('rf_pk=$1', array($licenseId), $groupId);
  }

  /**
   * @param string $licenseShortname
   * @param int|null $groupId
   * @return License|null
   */
  public function getLicenseByShortName($licenseShortname, $groupId=null)
  {
    return $this->getLicenseByCondition('rf_shortname=$1', array($licenseShortname), $groupId);
  }

  /**
   * @param string $licenseSpdxId License SPDX ID
   * @param int $groupId          Group ID
   * @return License|null         License, if found, null otherwise.
   */
  public function getLicenseBySpdxId($licenseSpdxId, $groupId=null)
  {
    return $this->getLicenseByCondition('rf_spdx_id=$1', array($licenseSpdxId), $groupId);
  }

  /**
   * @param int $userId
   * @param int $groupId
   * @param int $uploadTreeId
   * @param bool[] $licenseRemovals
   * @param string $refText
   * @param bool $ignoreIrrelevant Ignore irrelevant files while scanning
   * @param string $delimiters Delimiters for bulk scan,
   *                           null or "DEFAULT" for default values
   * @return int lrp_pk on success or -1 on fail
   */
  public function insertBulkLicense($userId, $groupId, $uploadTreeId, $licenseRemovals, $refText, $ignoreIrrelevant=true, $delimiters=null)
  {
    if (strcasecmp($delimiters, "DEFAULT") === 0) {
      $delimiters = null;
    } elseif ($delimiters !== null) {
      $delimiters = StringOperation::replaceUnicodeControlChar($delimiters);
    }
    $licenseRefBulkIdResult = $this->dbManager->getSingleRow(
        "INSERT INTO license_ref_bulk (user_fk, group_fk, uploadtree_fk, rf_text, ignore_irrelevant, bulk_delimiters)
      VALUES ($1,$2,$3,$4,$5,$6) RETURNING lrb_pk",
        array($userId, $groupId, $uploadTreeId,
          StringOperation::replaceUnicodeControlChar($refText),
          $this->dbManager->booleanToDb($ignoreIrrelevant),
          $delimiters),
        __METHOD__ . '.getLrb'
    );
    if ($licenseRefBulkIdResult === false) {
      return -1;
    }
    $bulkId = $licenseRefBulkIdResult['lrb_pk'];

    $stmt = __METHOD__ . '.insertAction';
    $this->dbManager->prepare($stmt, "INSERT INTO license_set_bulk (lrb_fk, rf_fk, removing, comment, reportinfo, acknowledgement) VALUES ($1,$2,$3,$4,$5,$6)");
    foreach ($licenseRemovals as $licenseId=>$removing) {
      $this->dbManager->execute($stmt, array($bulkId, $licenseId,
        $this->dbManager->booleanToDb($removing[0]),
        StringOperation::replaceUnicodeControlChar($removing[1]),
        StringOperation::replaceUnicodeControlChar($removing[2]),
        StringOperation::replaceUnicodeControlChar($removing[3])));
    }

    return $bulkId ;
  }

  /**
   * @param string $newShortname
   * @param int $groupId
   * @return bool
   */
  public function isNewLicense($newShortname, $groupId)
  {
    $licenceViewDao = new LicenseViewProxy($groupId, array('columns' => array('rf_shortname')));
    $sql = 'SELECT count(*) cnt FROM (' . $licenceViewDao->getDbViewQuery() . ') AS license_all WHERE rf_shortname=$1';
    $duplicatedRef = $this->dbManager->getSingleRow($sql, array($newShortname), __METHOD__.".$groupId" );
    return $duplicatedRef['cnt'] == 0;
  }

  /**
   * @param string $shortname
   * @param string $refText
   * @param string $spdxId
   * @return mixed
   */
  public function insertLicense($shortname, $refText, $spdxId = null)
  {
    $row = $this->dbManager->getSingleRow(
      "INSERT INTO license_ref (rf_shortname, rf_text, rf_detector_type, rf_spdx_id) VALUES ($1, $2, 2, $3) RETURNING rf_pk",
      array(StringOperation::replaceUnicodeControlChar($shortname),
        StringOperation::replaceUnicodeControlChar($refText),
        StringOperation::replaceUnicodeControlChar($spdxId)),
      __METHOD__.".addLicense" );
    return $row["rf_pk"];
  }

  /**
   * @param string $newShortname
   * @param string $refText
   * @return int Id of license candidate
   */
  public function insertUploadLicense($newShortname, $refText, $groupId, $userId)
  {
    $sql = 'INSERT INTO license_candidate (group_fk,rf_shortname,rf_fullname,rf_text,rf_md5,rf_detector_type,rf_user_fk_created) VALUES ($1,$2,$2,$3,md5($3),1,$4) RETURNING rf_pk';
    $refArray = $this->dbManager->getSingleRow($sql, array($groupId,
      StringOperation::replaceUnicodeControlChar($newShortname),
      StringOperation::replaceUnicodeControlChar($refText), $userId), __METHOD__);
    return $refArray['rf_pk'];
  }

  /**
   * @param array("License by Nomos.")
   * @return int count of license_ref
   */
  public function getLicenseCount()
  {
    $licenseRefTable = $this->dbManager->getSingleRow("SELECT COUNT(*) cnt FROM license_ref WHERE rf_text!=$1", array("License by Nomos."));
    return intval($licenseRefTable['cnt']);
  }

  /**
   * @param int $rf_pk
   * @param string $shortname
   * @param string $fullname
   * @param string $rfText, $rfNotes
   * @param string $readyformerge
   * @param int $riskLvl
   * @param string $spdxId
   */
  public function updateCandidate($rf_pk, $shortname, $fullname, $rfText, $url,
                                  $rfNotes, $lastmodified, $userIdmodified,
                                  $readyformerge, $riskLvl, $spdxId = null)
  {
    $marydone = $this->dbManager->booleanToDb($readyformerge);
    $sql = 'UPDATE license_candidate SET ' .
      'rf_shortname=$2, rf_fullname=$3, rf_text=$4, rf_url=$5, rf_notes=$6, ' .
      'rf_lastmodified=$7, rf_user_fk_modified=$8, marydone=$9, rf_risk=$10';
    $params = array($rf_pk, StringOperation::replaceUnicodeControlChar($shortname),
      StringOperation::replaceUnicodeControlChar($fullname),
      StringOperation::replaceUnicodeControlChar($rfText), $url,
      StringOperation::replaceUnicodeControlChar($rfNotes), $lastmodified,
      $userIdmodified, $marydone, $riskLvl);
    $statement = __METHOD__;
    if ($spdxId != null) {
      $params[] = StringOperation::replaceUnicodeControlChar($spdxId);
      $sql .= ', rf_spdx_id=$' . count($params);
      $statement .= ".spdxid";
    }
    $sql .= ' WHERE rf_pk=$1';
    $this->dbManager->getSingleRow($sql, $params, $statement);
  }

  /**
   * @param int $licenseId
   * @param int $groupId
   */
  public function getLicenseParentById($licenseId, $groupId=null)
  {
    return $this->getLicenseByCondition(" rf_pk=(SELECT rf_parent FROM license_map WHERE usage=$1 AND rf_fk=$2 AND rf_fk!=rf_parent)",
            array(LicenseMap::CONCLUSION,$licenseId), $groupId);
  }

  /**
   * Get obligations associated to the list of licenses sent.
   * @param array   $licenseLists List of licenses
   * @param boolean $candidate    Is candidate obligation map?
   * @return array
   */
  public function getLicenseObligations($licenseLists, $candidate = false)
  {
    if (!empty($licenseLists)) {
      $sql = "";
      $params = array();
      $params[] = '{' . implode(',', $licenseLists) . '}';
      if ($candidate) {
        $tableName='obligation_candidate_map';
        $sql = "SELECT ob_pk, ob_topic, ob_text, ob_active, rf_fk, " .
          "ob_type, ob_classification, ob_comment, " .
          "rf_shortname, rf_spdx_id FROM obligation_ref " .
          "JOIN $tableName ON $tableName.ob_fk = obligation_ref.ob_pk " .
          "JOIN license_ref ON $tableName.rf_fk = license_ref.rf_pk " .
          "WHERE ob_active='t' AND rf_fk = ANY($1::int[]);";
      } else {
        $tableName='obligation_map';
        $conclusionmapCte = LicenseMap::getMappedLicenseRefView('$2');
        $sql = "WITH conclusionmap AS (" . $conclusionmapCte . ") " .
          "SELECT ob_pk, ob_topic, ob_text, ob_active, rf_origin AS rf_fk, " .
          "ob_type, ob_classification, ob_comment, " .
          "lr.rf_shortname, lr.rf_spdx_id FROM obligation_ref " .
          "JOIN $tableName ON $tableName.ob_fk = obligation_ref.ob_pk " .
          "JOIN conclusionmap ON $tableName.rf_fk = conclusionmap.rf_pk " .
          "INNER JOIN license_ref lr ON conclusionmap.rf_origin = lr.rf_pk " .
          "WHERE ob_active='t' AND rf_origin = ANY($1::int[]);";
        $params[] = LicenseMap::CONCLUSION;
      }
      $statementName = __METHOD__.$tableName;
      $this->dbManager->prepare($statementName, $sql);
      $result = $this->dbManager->execute($statementName, $params);
      $ObligationRef = $this->dbManager->fetchAll($result);
      $this->dbManager->freeResult($result);
      return $ObligationRef;
    }
  }
}
