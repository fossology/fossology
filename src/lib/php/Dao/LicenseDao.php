<?php
/*
Copyright (C) 2014, Siemens AG
Author: Andreas Würl

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

use Fossology\Lib\Data\AgentRef;
use Fossology\Lib\Data\License;
use Fossology\Lib\Data\LicenseMatch;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\Tree\ItemTreeBounds;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\Object;
use Monolog\Logger;

class LicenseDao extends Object
{
  /** @var DbManager */
  private $dbManager;
  /** @var Logger */
  private $logger;


  function __construct(DbManager $dbManager)
  {
    $this->dbManager = $dbManager;
    $this->logger = new Logger(self::className());
  }

  /**
   * \brief get all the licenses for a single file or uploadtree
   *
   * @param ItemTreeBounds $itemTreeBounds
   * @return LicenseMatch[]
   */
  function getAgentFileLicenseMatches(ItemTreeBounds $itemTreeBounds)
  {
    $uploadTreeTableName = $itemTreeBounds->getUploadTreeTableName();
    $statementName = __METHOD__ . ".$uploadTreeTableName";

    $this->dbManager->prepare($statementName,
        "SELECT   LFR.rf_shortname AS license_shortname,
                  LFR.rf_fullname AS license_fullname,
                  LFR.rf_pk AS license_id,
                  LFR.fl_pk AS license_file_id,
                  LFR.pfile_fk as file_id,
                  AG.agent_name AS agent_name,
                  AG.agent_pk AS agent_id,
                  AG.agent_rev AS agent_revision,
                  LFR.rf_match_pct AS percent_match
          FROM ( SELECT license_ref.rf_fullname, license_ref.rf_shortname, license_ref.rf_pk, license_file.fl_pk, license_file.agent_fk, license_file.pfile_fk
               FROM license_file
               JOIN license_ref ON license_file.rf_fk = license_ref.rf_pk) as LFR
          INNER JOIN $uploadTreeTableName as UT ON UT.pfile_fk = LFR.pfile_fk
          INNER JOIN agent as AG ON AG.agent_pk = LFR.agent_fk
          WHERE AG.agent_enabled='true' and
           UT.upload_fk=$1 AND UT.lft BETWEEN $2 and $3
          ORDER BY license_shortname ASC, percent_match DESC");

    $result = $this->dbManager->execute($statementName,
        array($itemTreeBounds->getUploadId(), $itemTreeBounds->getLeft(), $itemTreeBounds->getRight()));

    $matches = array();

    while ($row = $this->dbManager->fetchArray($result))
    {
      $licenseRef = new LicenseRef(intval($row['license_id']), $row['license_shortname'], $row['license_fullname']);
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
                  LF.rf_fullname AS license_fullname,
                  LF.rf_pk AS license_id,
                  LFB.lrb_pk AS license_file_id,
                  LFB.removing AS removing,
                  UT.pfile_fk as file_id
          FROM license_ref_bulk as LFB
          INNER JOIN license_ref as LF on LF.rf_pk = LFB.rf_fk
          INNER JOIN $uploadTreeTableName as UT ON UT.uploadtree_pk = LFB.uploadtree_fk
          WHERE UT.upload_fk=$1 AND UT.lft BETWEEN $2 and $3
          ORDER BY license_file_id ASC");

    $result = $this->dbManager->execute($statementName,
        array($itemTreeBounds->getUploadId(), $itemTreeBounds->getLeft(), $itemTreeBounds->getRight()));

    $matches = array();

    while ($row = $this->dbManager->fetchArray($result))
    {
      $licenseRef = new LicenseRef($row['license_id'], $row['license_shortname'], $row['license_fullname']);
      if($row['removing'] == 'f') {
        $agentID=1;
        $agentName="bulk addition";
      }
      else
      {
        $agentID=2;
        $agentName="bulk removal";
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
    $searchCondition = $search ? "WHERE lower(rf_shortname) like($1)" : "";

    $order = $orderAscending ? "ASC" : "DESC";
    $statementName = __METHOD__ . ($search ? ".search_" . $search : "") . ".order_" . $order;

    $this->dbManager->prepare($statementName,
        "select rf_pk,rf_shortname,rf_fullname from license_ref $searchCondition order by rf_shortname $order");
    $result = $this->dbManager->execute($statementName, $search ? array('%' . strtolower($search) . '%') : array());

    $licenseRefs = array();
    while ($row = $this->dbManager->fetchArray($result))
    {
      $licenseRefs[] = new LicenseRef(intval($row['rf_pk']), $row['rf_shortname'], $row['rf_fullname']);
    }
    $this->dbManager->freeResult($result);
    return $licenseRefs;
  }

  /**
   * @return array 
   */
  public function getLicenseArray()
  {
    $statementName = __METHOD__;

    $this->dbManager->prepare($statementName,
        "select rf_pk id,rf_shortname shortname,rf_fullname fullname from license_ref order by rf_shortname");
    $result = $this->dbManager->execute($statementName);
    $licenseRefs = $this->dbManager->fetchAll($result);
    $this->dbManager->freeResult($result);
    return $licenseRefs;
  }
  
  /**
   * @param ItemTreeBounds $itemTreeBounds
   * @param $selectedAgentId
   * @return array
   */
  public function getTopLevelLicensesPerFileId(ItemTreeBounds $itemTreeBounds, $selectedAgentId = null, $filterLicenses = array('VOID'))
  {
    $uploadTreeTableName = $itemTreeBounds->getUploadTreeTableName();
    $statementName = __METHOD__ . '.' . $uploadTreeTableName . '.' . implode("",$filterLicenses);
    $param = array($itemTreeBounds->getLeft(),$itemTreeBounds->getRight());

    $noLicenseFoundStmt = empty($filterLicenses) ? "" : " AND rf_shortname NOT IN ('" . implode("', '", $filterLicenses) . "')";

    $sql_upload = "";
    if ('uploadtree_a' == $uploadTreeTableName) {
      $sql_upload = " AND utree.upload_fk=$3 ";
      $param[] = $itemTreeBounds->getUploadId();
    }

    $sql = "SELECT utree.pfile_fk as file_id,
           rf_shortname as license_shortname,
           rf_pk as license_id,
           agent_name,
           max(agent_pk) as agent_id,
           rf_match_pct as match_percentage
         FROM ( SELECT license_ref.rf_fullname, license_ref.rf_shortname, license_ref.rf_pk, license_file.rf_match_pct, license_file.fl_pk, license_file.agent_fk, license_file.pfile_fk
               FROM license_file
               JOIN license_ref ON license_file.rf_fk = license_ref.rf_pk) AS pfile_ref
         INNER JOIN $uploadTreeTableName utree ON pfile_ref.pfile_fk = utree.pfile_fk
         INNER JOIN agent ON agent_fk = agent_pk
         WHERE (lft BETWEEN $1 AND $2) $sql_upload
           $noLicenseFoundStmt";

    if (!empty($selectedAgentId)){
      $param[] = $selectedAgentId;
      $sql .= " AND agent_pk=$".count($param);
      $statementName .= '.agent';
    }
    $sql .= " GROUP BY file_id, license_shortname, license_id, agent_name, match_percentage
         ORDER BY match_percentage ASC, license_shortname ASC";
    
    $this->dbManager->prepare($statementName, $sql);
    $result = $this->dbManager->execute($statementName, $param);
    $licensesPerFileId = array();
    while ($row = $this->dbManager->fetchArray($result))
    {
      $licensesPerFileId[$row['file_id']][$row['license_shortname']][$row['agent_name']] = $row;
    }
    $this->dbManager->freeResult($result);
    return $licensesPerFileId;
  }


  public function getLicenseHistogram(ItemTreeBounds $itemTreeBounds, $orderStatement = "", $agentId=null)
  {
    $uploadTreeTableName = $itemTreeBounds->getUploadTreeTableName();
    $statementName = __METHOD__ . '.' . $uploadTreeTableName . ".$orderStatement.$agentId";
    $param = array($itemTreeBounds->getUploadId(), $itemTreeBounds->getLeft(), $itemTreeBounds->getRight());
    $sql = "SELECT rf_shortname AS license_shortname, count(*) AS count
         FROM ( SELECT license_ref.rf_shortname, license_ref.rf_pk, license_file.fl_pk, license_file.agent_fk, license_file.pfile_fk
             FROM license_file
             JOIN license_ref ON license_file.rf_fk = license_ref.rf_pk) AS pfile_ref
         RIGHT JOIN $uploadTreeTableName UT ON pfile_ref.pfile_fk = UT.pfile_fk
         WHERE rf_shortname NOT IN ('Void') AND upload_fk=$1 AND UT.lft BETWEEN $2 and $3";
    if (!empty($agentId))
    {
      $sql .= ' AND agent_fk=$4';
      $param[] = $agentId;
    }
    $sql .= " GROUP BY license_shortname";
    if ($orderStatement)
    {
      $sql .= $orderStatement;
    }
    $this->dbManager->prepare($statementName, $sql);
    $result = $this->dbManager->execute($statementName,$param);
    $assocLicenseHist = array();
    while ($res = $this->dbManager->fetchArray($result))
    {
      $assocLicenseHist[$res['license_shortname']] = $res['count'];
    }
    $this->dbManager->freeResult($result);
    return $assocLicenseHist;
  }

  public function getLicenseShortnamesContained(ItemTreeBounds $itemTreeBounds, $filterLicenses=array('VOID')) //'No_license_found',
  {
    $uploadTreeTableName = $itemTreeBounds->getUploadTreeTableName();

    $noLicenseFoundStmt = empty($filterLicenses) ? "" : " AND rf_shortname NOT IN ("
        . implode(", ", array_map(function($name) {return "'" . $name . "'";}, $filterLicenses)) . ")";

    $statementName = __METHOD__ . '.' . $uploadTreeTableName;
    $this->dbManager->prepare($statementName,
        "SELECT license_ref.rf_shortname
              FROM license_file JOIN license_ref ON license_file.rf_fk = license_ref.rf_pk
              INNER JOIN $uploadTreeTableName uploadTree ON uploadTree.pfile_fk=license_file.pfile_fk
              WHERE upload_fk=$1
                AND lft BETWEEN $2 AND $3
                $noLicenseFoundStmt
              GROUP BY rf_shortname
              ORDER BY rf_shortname ASC");
    $result = $this->dbManager->execute($statementName,
        array($itemTreeBounds->getUploadId(), $itemTreeBounds->getLeft(), $itemTreeBounds->getRight()));

    $licenses = array();
    while ($row = $this->dbManager->fetchArray($result))
    {
      $licenses[] = $row['rf_shortname'];
    }
    $this->dbManager->freeResult($result);

    return $licenses;
  }


  /**
   * @param string $licenseId
   * @return License|null
   */
  public function getLicenseById($licenseId)
  {
    $row = $this->dbManager->getSingleRow(
        "SELECT rf_pk, rf_shortname, rf_fullname, rf_text, rf_url FROM license_ref WHERE rf_pk=$1",
        array($licenseId));
    if (false === $row)
    {
      return null;
    }
    $license = new License(intval($row['rf_pk']), $row['rf_shortname'], $row['rf_fullname'], $row['rf_text'], $row['rf_url']);
    return $license;
  }

  /**
   * @param string $licenseShortname
   * @return License|null
   */
  public function getLicenseByShortName($licenseShortname)
  {
    $row = $this->dbManager->getSingleRow(
        "SELECT rf_pk, rf_shortname, rf_fullname, rf_text, rf_url FROM license_ref WHERE rf_shortname=$1",
        array($licenseShortname));
    if (false === $row)
    {
      return null;
    }
    $license = new License(intval($row['rf_pk']), $row['rf_shortname'], $row['rf_fullname'], $row['rf_text'], $row['rf_url']);
    return $license;
  }

  /**
   * @param int $userId
   * @param int $groupId
   * @param int $uploadTreeId
   * @param int $licenseId
   * @param bool $removing
   * @param string $refText
   * @param string|null $newShortname
   * @return int lrp_pk on success or -1 on fail
   */
  public function insertBulkLicense($userId, $groupId, $uploadTreeId, $licenseId, $removing, $refText, $newShortname=NULL)
  {
    $licenseRefBulkIdResult = $this->dbManager->getSingleRow(
      "INSERT INTO license_ref_bulk (user_fk, group_fk, uploadtree_fk, rf_fk, removing, rf_text, lrb_shortname)
      VALUES($1,$2,$3,$4,$5,$6,$7) RETURNING lrb_pk",
      array($userId, $groupId, $uploadTreeId, $licenseId, $this->dbManager->booleanToDb($removing), $refText, $newShortname),
      __METHOD__.'.getLrb'
    );

    if ($licenseRefBulkIdResult === false) {
      return -1;
    }
    
    if(!empty($newShortname))
    {
      $this->dbManager->prepare($stmt=__METHOD__.'.updLrb' , 'UPDATE license_ref_bulk SET rf_fk=lrb_pk WHERE lrb_pk=$1');
      $this->dbManager->execute($stmt,array($licenseRefBulkIdResult['lrb_pk']));
    }
    return $licenseRefBulkIdResult['lrb_pk'];
  }
  
  /**
   * @param string $newShortname
   * @param int $groupId
   * @return bool
   */
  public function isNewLicense($newShortname, $groupId)
  {
    $duplicatedRef = $this->dbManager->getSingleRow('SELECT rf_text FROM license_ref WHERE rf_shortname=$1',array($newShortname),__METHOD__.'.references');
    if ($duplicatedRef !== false)
    {
      return false;
    }
    $duplicatedCandidate = $this->dbManager->getSingleRow('SELECT rf_text FROM license_ref_bulk WHERE lrb_shortname=$1 AND group_fk=$2',array($newShortname,$groupId),__METHOD__.'.candidates');
    return ($duplicatedCandidate===false);
  }
  
}